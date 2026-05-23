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
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Paynow Zimbabwe — covers two flows:
 *
 *   • Web (hosted page): POST {base}/interface/initiatetransaction
 *     → returns `browserurl` for the payer + `pollurl` for status checks.
 *
 *   • Express mobile money: POST {base}/interface/remotetransaction
 *     → triggers a USSD/STK push on EcoCash/OneMoney/InnBucks.
 *
 * Authentication is a SHA-512 `hash` of every other field concatenated
 * with the integration key. Same hash scheme is used both ways and on
 * the inbound IPN callback.
 *
 * The driver auto-selects Express when the ChargeRequest supplies a
 * `method` in metadata (e.g. metadata['method'] => 'ecocash').
 */
class PaynowGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, RefundsTransactions
{
    protected string $identifier = 'paynow';

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        $method = $request->metadata['method'] ?? null;
        $useExpress = is_string($method) && $method !== '' && $method !== 'web';

        if ($useExpress) {
            return $this->initiateExpress($request, $method);
        }

        return $this->initiateWeb($request);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        // Paynow exposes refunds only to specific merchant accounts via
        // back-office tooling; the public API does not include a refund
        // endpoint. We surface that explicitly rather than silently
        // marking the row REFUNDED.
        return new RefundResult(
            status: PaymentStatus::FAILED,
            reference: $request->reference,
            gatewayReference: $request->gatewayReference,
            raw: [
                'message' => 'Paynow refunds must be issued via the merchant portal.',
                'recommended_action' => 'manual_portal_refund',
            ],
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        $pollUrl = (string) ($this->config('_poll_url') ?? $gatewayReference ?? '');
        if ($pollUrl === '') {
            throw new GatewayResponseException('Paynow poll URL is required for status lookup.');
        }

        $response = $this->httpClient()
            ->asForm()
            ->withHeaders(['Accept' => 'text/plain'])
            ->post($pollUrl);

        $body = $this->parseFormResponse($response);

        return new StatusResult(
            status: $this->mapStatus((string) ($body['status'] ?? '')),
            reference: $reference,
            gatewayReference: (string) ($body['paynowreference'] ?? $gatewayReference ?? ''),
            message: (string) ($body['status'] ?? ''),
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $fields = $request->all();
        if (! is_array($fields) || ! isset($fields['hash'])) {
            throw new WebhookSignatureException('Paynow callback missing hash.');
        }

        $expected = $this->hash($fields, (string) $this->config('integration_key', '', required: true));
        $received = strtoupper((string) $fields['hash']);

        if (! hash_equals($expected, $received)) {
            throw new WebhookSignatureException('Paynow callback hash mismatch.');
        }

        return true;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $request->all();
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $status = $this->mapStatus((string) ($payload['status'] ?? ''));
        $reference = (string) ($payload['reference'] ?? '');
        $gatewayRef = (string) ($payload['paynowreference'] ?? '');

        $amount = null;
        if (isset($payload['amount']) && is_numeric($payload['amount'])) {
            $amount = Money::ofMajor((string) $payload['amount'], (string) ($payload['currency'] ?? 'USD'));
        }

        return new WebhookEvent(
            status: $status,
            reference: $reference !== '' ? $reference : null,
            gatewayReference: $gatewayRef !== '' ? $gatewayRef : null,
            eventType: (string) ($payload['status'] ?? 'unknown'),
            amount: $amount,
            raw: $payload,
        );
    }

    /* ────────────────────── internals ────────────────────── */

    protected function initiateWeb(ChargeRequest $request): ChargeResult
    {
        $integrationId = (string) $this->config('integration_id', '', required: true);
        $integrationKey = (string) $this->config('integration_key', '', required: true);

        $fields = [
            'id' => $integrationId,
            'reference' => $request->reference,
            'amount' => $request->amount->major(),
            'additionalinfo' => $request->description,
            'returnurl' => $request->returnUrl ?? config('app.url'),
            'resulturl' => $request->resultUrl ?? $this->webhookUrl(),
            'authemail' => $request->customer->email ?? '',
            'status' => 'Message',
        ];
        $fields['hash'] = $this->hash($fields, $integrationKey);

        $response = $this->httpClient()
            ->asForm()
            ->post($this->endpoint('/interface/initiatetransaction'), $fields);

        $body = $this->parseFormResponse($response);

        if (($body['status'] ?? '') === 'Error') {
            throw new GatewayResponseException(
                (string) ($body['error'] ?? 'Paynow initiation failed.'),
                $body,
                gatewayCode: (string) ($body['status'] ?? ''),
            );
        }

        return new ChargeResult(
            status: PaymentStatus::PENDING,
            reference: $request->reference,
            gatewayReference: (string) ($body['pollurl'] ?? ''),
            redirectUrl: (string) ($body['browserurl'] ?? '') ?: null,
            pollUrl: (string) ($body['pollurl'] ?? '') ?: null,
            instructions: null,
            raw: $body,
        );
    }

    protected function initiateExpress(ChargeRequest $request, string $method): ChargeResult
    {
        $integrationId = (string) $this->config('express.integration_id', '', required: true);
        $integrationKey = (string) $this->config('express.integration_key', '', required: true);

        $fields = [
            'id' => $integrationId,
            'reference' => $request->reference,
            'amount' => $request->amount->major(),
            'additionalinfo' => $request->description,
            'returnurl' => $request->returnUrl ?? config('app.url'),
            'resulturl' => $request->resultUrl ?? $this->webhookUrl(),
            'authemail' => $request->customer->email ?? 'noreply@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'phone' => $request->customer->normalisedMsisdn() ?? '',
            'method' => strtolower($method),
            'status' => 'Message',
        ];
        $fields['hash'] = $this->hash($fields, $integrationKey);

        $response = $this->httpClient()
            ->asForm()
            ->post($this->endpoint('/interface/remotetransaction'), $fields);

        $body = $this->parseFormResponse($response);

        if (($body['status'] ?? '') === 'Error') {
            throw new GatewayResponseException(
                (string) ($body['error'] ?? 'Paynow Express initiation failed.'),
                $body,
                gatewayCode: (string) ($body['status'] ?? ''),
            );
        }

        return new ChargeResult(
            status: PaymentStatus::PENDING,
            reference: $request->reference,
            gatewayReference: (string) ($body['pollurl'] ?? ''),
            redirectUrl: null,
            pollUrl: (string) ($body['pollurl'] ?? '') ?: null,
            instructions: __('Approve the prompt on your phone to confirm the payment.'),
            raw: $body,
        );
    }

    /**
     * Paynow's hash is sha512(concat(all-field-values, integration_key))
     * in upper case. Field order is the order in which they appear in
     * the request body — preserve insertion order in PHP arrays.
     *
     * @param  array<string, scalar>  $fields
     */
    protected function hash(array $fields, string $integrationKey): string
    {
        $unhashed = '';
        foreach ($fields as $key => $value) {
            if (strtolower((string) $key) === 'hash') {
                continue;
            }
            $unhashed .= (string) $value;
        }
        $unhashed .= $integrationKey;

        return strtoupper(hash('sha512', $unhashed));
    }

    /**
     * Paynow responds with URL-encoded key/value pairs separated by `&`.
     *
     * @return array<string, string>
     */
    protected function parseFormResponse(Response $response): array
    {
        $parsed = [];
        parse_str($response->body(), $parsed);

        /** @var array<string, string> $parsed */
        return $parsed;
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) $this->config('base_url', '', required: true), '/').$path;
    }

    protected function mapStatus(string $providerStatus): PaymentStatus
    {
        return match (strtolower(trim($providerStatus))) {
            'paid', 'awaiting delivery', 'delivered' => PaymentStatus::CAPTURED,
            'created', 'sent', 'ok', 'message' => PaymentStatus::PENDING,
            'cancelled', 'cancelled by payer' => PaymentStatus::CANCELLED,
            'refunded' => PaymentStatus::REFUNDED,
            'failed', 'disputed' => PaymentStatus::FAILED,
            default => PaymentStatus::PENDING,
        };
    }
}
