<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\Enums\PaymentStatus;
use App\Services\Payments\AbstractGateway;
use App\Services\Payments\Contracts\HandlesWebhooks;
use App\Services\Payments\Contracts\PollsTransactionStatus;
use App\Services\Payments\Contracts\RefundsTransactions;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Data\StatusResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Exceptions\GatewayResponseException;
use App\Services\Payments\Exceptions\WebhookSignatureException;
use Illuminate\Http\Request;

/**
 * Zimswitch — Open Payment Platform (OPPWA) integration. OPP is a
 * hosted-checkout pattern: we POST a "checkout" to /checkouts and
 * embed the returned `id` in the COPYandPAY widget. The payer
 * completes card entry against the widget; we then GET the payment
 * status at /checkouts/{id}/payment.
 *
 * Refunds are direct API calls against the captured payment ID.
 *
 * Authentication is `Authorization: Bearer {access_token}` plus a
 * required `entityId` in every request body.
 *
 * Webhooks are AES-256-GCM-encrypted; decryption proves origin.
 */
class ZimswitchGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, RefundsTransactions
{
    protected string $identifier = 'zimswitch';

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        $payload = [
            'entityId' => (string) $this->config('entity_id', '', required: true),
            'amount' => $request->amount->major(),
            'currency' => $request->amount->currency,
            'paymentType' => 'DB',          // Debit (auth + capture)
            'merchantTransactionId' => $request->reference,
            'customer.email' => $request->customer->email ?? '',
            'customer.givenName' => $request->customer->name ?? '',
            'billing.country' => 'ZW',
            'notificationUrl' => $request->resultUrl ?? $this->webhookUrl(),
            'shopperResultUrl' => $request->returnUrl ?? config('app.url'),
        ];

        $response = $this->httpClient()
            ->asForm()
            ->withToken((string) $this->config('access_token', '', required: true))
            ->post($this->endpoint('/checkouts'), $payload);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $code = (string) data_get($body, 'result.code', '');
        if (! $this->isSuccessfulCode($code)) {
            throw new GatewayResponseException(
                (string) data_get($body, 'result.description', 'Zimswitch checkout failed.'),
                $body,
                gatewayCode: $code,
            );
        }

        $checkoutId = (string) ($body['id'] ?? '');
        $redirectUrl = $this->buildPaymentWidgetUrl($checkoutId);

        return new ChargeResult(
            status: PaymentStatus::PENDING,
            reference: $request->reference,
            gatewayReference: $checkoutId,
            redirectUrl: $redirectUrl,
            pollUrl: $this->endpoint('/checkouts/'.$checkoutId.'/payment'),
            instructions: null,
            raw: $body,
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $paymentId = $request->gatewayReference;
        if ($paymentId === null || $paymentId === '') {
            throw new GatewayResponseException('Zimswitch refund requires the original paymentId.');
        }

        $payload = [
            'entityId' => (string) $this->config('entity_id', '', required: true),
            'amount' => $request->amount?->major() ?? '0.00',
            'currency' => $request->amount?->currency ?? 'USD',
            'paymentType' => 'RF',          // Refund
        ];

        $response = $this->httpClient()
            ->asForm()
            ->withToken((string) $this->config('access_token', '', required: true))
            ->post($this->endpoint('/payments/'.$paymentId), $payload);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $code = (string) data_get($body, 'result.code', '');

        return new RefundResult(
            status: $this->isSuccessfulCode($code) ? PaymentStatus::REFUNDED : PaymentStatus::FAILED,
            reference: $request->reference,
            gatewayReference: (string) ($body['id'] ?? $paymentId),
            raw: $body,
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        if ($gatewayReference === null || $gatewayReference === '') {
            throw new GatewayResponseException('Zimswitch status lookup requires the checkout id.');
        }

        $response = $this->httpClient()
            ->withToken((string) $this->config('access_token', '', required: true))
            ->get($this->endpoint('/checkouts/'.$gatewayReference.'/payment'), [
                'entityId' => (string) $this->config('entity_id', '', required: true),
            ]);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $code = (string) data_get($body, 'result.code', '');

        return new StatusResult(
            status: $this->mapStatusFromCode($code),
            reference: $reference,
            gatewayReference: (string) ($body['id'] ?? $gatewayReference),
            message: (string) data_get($body, 'result.description', ''),
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        // OPP encrypts notifications with the merchant's webhook key
        // (AES-256-GCM). Decryption proves authenticity.
        try {
            $this->decryptWebhookBody($request);

            return true;
        } catch (\Throwable $e) {
            throw new WebhookSignatureException('Zimswitch webhook decryption failed: '.$e->getMessage());
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $this->decryptWebhookBody($request);

        $payment = (array) ($payload['payload'] ?? $payload);
        $code = (string) data_get($payment, 'result.code', '');

        $reference = (string) ($payment['merchantTransactionId'] ?? '');
        $gatewayRef = (string) ($payment['id'] ?? '');

        $amount = null;
        if (isset($payment['amount'], $payment['currency']) && is_numeric($payment['amount'])) {
            $amount = Money::ofMajor((string) $payment['amount'], (string) $payment['currency']);
        }

        return new WebhookEvent(
            status: $this->mapStatusFromCode($code),
            reference: $reference !== '' ? $reference : null,
            gatewayReference: $gatewayRef !== '' ? $gatewayRef : null,
            eventType: (string) ($payload['type'] ?? 'PAYMENT'),
            amount: $amount,
            raw: $payload,
        );
    }

    /* ────────────────────── internals ────────────────────── */

    protected function buildPaymentWidgetUrl(string $checkoutId): string
    {
        // The COPYandPAY widget is loaded client-side; our app embeds
        // the script at /v1/paymentWidgets.js?checkoutId={id}. The
        // redirectUrl points to our own /payments/zimswitch/complete
        // page which serves that widget.
        return rtrim((string) config('app.url'), '/')
            .'/payments/zimswitch/complete?checkoutId='.rawurlencode($checkoutId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decryptWebhookBody(Request $request): array
    {
        $ivHex = $request->header('X-Initialization-Vector');
        $authTagHex = $request->header('X-Authentication-Tag');
        $cipherHex = $request->getContent();

        if (! is_string($ivHex) || ! is_string($authTagHex) || $cipherHex === '') {
            throw new WebhookSignatureException('Zimswitch webhook missing required headers.');
        }

        $key = hex2bin((string) $this->config('webhook_decryption_key', '', required: true));
        $iv = hex2bin($ivHex);
        $tag = hex2bin($authTagHex);
        $cipher = hex2bin($cipherHex);

        if ($key === false || $iv === false || $tag === false || $cipher === false) {
            throw new WebhookSignatureException('Zimswitch webhook hex inputs malformed.');
        }

        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plain === false) {
            throw new WebhookSignatureException('Zimswitch webhook AES-GCM decryption failed.');
        }

        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) $this->config('base_url', '', required: true), '/').$path;
    }

    /**
     * OPP success patterns documented at
     * https://zimswitch.docs.oppwa.com/reference/resultCodes.
     *
     *   000.000.000  – Successful (production)
     *   000.100.1*   – Successful integrator-test
     *   000.[36]00.* – Successful (request received, pending review)
     */
    protected function isSuccessfulCode(string $code): bool
    {
        return (bool) preg_match(
            '/^(000\.000\.|000\.100\.1|000\.[36]00\.)/',
            $code,
        );
    }

    protected function mapStatusFromCode(string $code): PaymentStatus
    {
        if ($code === '') {
            return PaymentStatus::PENDING;
        }
        if ($this->isSuccessfulCode($code)) {
            return PaymentStatus::CAPTURED;
        }
        // Soft declines (timeouts, fraud-review) stay pending so the
        // reconciler retries; explicit declines are terminal.
        if (preg_match('/^(000\.200\.|800\.400\.5|100\.396\.)/', $code) === 1) {
            return PaymentStatus::PENDING;
        }

        return PaymentStatus::FAILED;
    }
}
