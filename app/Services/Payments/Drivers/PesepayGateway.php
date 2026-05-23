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
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\Exceptions\GatewayResponseException;
use App\Services\Payments\Exceptions\WebhookSignatureException;
use Illuminate\Http\Request;

/**
 * Pesepay — payload is symmetrically encrypted with the merchant's
 * 32-byte encryption key (AES-256-CBC, IV = first 16 chars of the key,
 * payload base64-encoded). The integration key goes in the `authorization`
 * header in cleartext; encryption guards the body.
 *
 * Endpoints used:
 *   POST {base}/v2/payments                Hosted checkout (returns redirectUrl)
 *   POST {base}/v1/payments/initiate       Direct (seamless) payment
 *   GET  {base}/v1/payments/check-payment  Status by referenceNumber
 *
 * Webhook callbacks are encrypted with the same key.
 */
class PesepayGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, RefundsTransactions
{
    protected string $identifier = 'pesepay';

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        $payload = [
            'amountDetails' => [
                'amount' => (float) $request->amount->major(),
                'currencyCode' => $request->amount->currency,
            ],
            'merchantReference' => $request->reference,
            'reasonForPayment' => $request->description,
            'resultUrl' => $request->resultUrl ?? $this->webhookUrl(),
            'returnUrl' => $request->returnUrl ?? config('app.url'),
            'customer' => [
                'email' => $request->customer->email ?? '',
                'phoneNumber' => $request->customer->normalisedMsisdn() ?? '',
                'name' => $request->customer->name ?? '',
            ],
            'metadata' => $request->metadata,
        ];

        $encrypted = $this->encrypt($payload);

        $response = $this->httpClient()
            ->withHeaders([
                'Authorization' => (string) $this->config('integration_key', '', required: true),
            ])
            ->post($this->endpoint('/v2/payments'), ['payload' => $encrypted]);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if (! isset($body['payload'])) {
            throw new GatewayResponseException(
                (string) ($body['message'] ?? 'Pesepay initiation failed.'),
                $body,
            );
        }

        $decoded = $this->decrypt((string) $body['payload']);

        if (! isset($decoded['redirectUrl'], $decoded['referenceNumber'])) {
            throw new GatewayResponseException('Pesepay response missing required fields.', $decoded);
        }

        return new ChargeResult(
            status: PaymentStatus::PENDING,
            reference: $request->reference,
            gatewayReference: (string) $decoded['referenceNumber'],
            redirectUrl: (string) $decoded['redirectUrl'],
            pollUrl: null,
            instructions: null,
            raw: $decoded,
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        // Pesepay refunds go through the merchant dashboard; the public
        // API surfaces a refund endpoint only for whitelisted accounts.
        // Surface that explicitly.
        return new RefundResult(
            status: PaymentStatus::FAILED,
            reference: $request->reference,
            gatewayReference: $request->gatewayReference,
            raw: [
                'message' => 'Pesepay refunds require dashboard action or whitelisted API access.',
                'recommended_action' => 'manual_dashboard_refund',
            ],
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        $ref = $gatewayReference ?? $reference;

        $response = $this->httpClient()
            ->withHeaders([
                'Authorization' => (string) $this->config('integration_key', '', required: true),
            ])
            ->get($this->endpoint('/v1/payments/check-payment'), ['referenceNumber' => $ref]);

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return new StatusResult(
            status: $this->mapStatus((string) ($body['transactionStatus'] ?? '')),
            reference: $reference,
            gatewayReference: $ref,
            message: (string) ($body['transactionStatusDescription'] ?? ''),
            raw: $body,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        // Verification IS the decryption — any payload that decrypts
        // cleanly with our key proves the sender holds it. We rethrow
        // as WebhookSignatureException so the controller logs and 401s.
        try {
            $this->decryptWebhookBody($request);

            return true;
        } catch (\Throwable $e) {
            throw new WebhookSignatureException('Pesepay payload failed decryption: '.$e->getMessage());
        }
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $payload = $this->decryptWebhookBody($request);

        $status = $this->mapStatus((string) ($payload['transactionStatus'] ?? ''));
        $reference = (string) ($payload['merchantReference'] ?? '');
        $gatewayRef = (string) ($payload['referenceNumber'] ?? '');

        $amount = null;
        if (isset($payload['amount'], $payload['currencyCode']) && is_numeric($payload['amount'])) {
            $amount = Money::ofMajor((string) $payload['amount'], (string) $payload['currencyCode']);
        }

        return new WebhookEvent(
            status: $status,
            reference: $reference !== '' ? $reference : null,
            gatewayReference: $gatewayRef !== '' ? $gatewayRef : null,
            eventType: (string) ($payload['transactionStatus'] ?? 'unknown'),
            amount: $amount,
            raw: $payload,
        );
    }

    /* ────────────────────── crypto ────────────────────── */

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function encrypt(array $payload): string
    {
        $key = $this->encryptionKey();
        $iv = substr($key, 0, 16);

        $encrypted = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        if ($encrypted === false) {
            throw new GatewayResponseException('Pesepay payload encryption failed.');
        }

        return base64_encode($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decrypt(string $encoded): array
    {
        $key = $this->encryptionKey();
        $iv = substr($key, 0, 16);

        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new GatewayResponseException('Pesepay payload base64 decode failed.');
        }

        $plain = openssl_decrypt($raw, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new GatewayResponseException('Pesepay payload decryption failed.');
        }

        $decoded = json_decode($plain, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decryptWebhookBody(Request $request): array
    {
        $body = $request->json()->all();
        if (! is_array($body) || ! isset($body['payload'])) {
            throw new WebhookSignatureException('Pesepay webhook missing payload.');
        }

        return $this->decrypt((string) $body['payload']);
    }

    protected function encryptionKey(): string
    {
        $key = (string) $this->config('encryption_key', '', required: true);
        if (strlen($key) !== 32) {
            throw new GatewayNotConfiguredException('Pesepay encryption_key must be exactly 32 chars.');
        }

        return $key;
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) $this->config('base_url', '', required: true), '/').$path;
    }

    protected function mapStatus(string $providerStatus): PaymentStatus
    {
        return match (strtoupper(trim($providerStatus))) {
            'SUCCESS', 'COMPLETED', 'PAID' => PaymentStatus::CAPTURED,
            'PENDING', 'INITIATED', 'PROCESSING' => PaymentStatus::PENDING,
            'CANCELLED', 'CANCELED' => PaymentStatus::CANCELLED,
            'FAILED', 'ERROR', 'TIMEOUT' => PaymentStatus::FAILED,
            'REFUNDED' => PaymentStatus::REFUNDED,
            default => PaymentStatus::PENDING,
        };
    }
}
