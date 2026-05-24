<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\Enums\PaymentStatus;
use App\Services\Payments\AbstractGateway;
use App\Services\Payments\Contracts\HandlesWebhooks;
use App\Services\Payments\Contracts\PollsTransactionStatus;
use App\Services\Payments\Contracts\ProvidesWebhookTimestamp;
use App\Services\Payments\Contracts\RefundsTransactions;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Data\StatusResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Exceptions\GatewayResponseException;
use App\Services\Payments\Exceptions\WebhookSignatureException;
use Illuminate\Http\Request;

/**
 * Stripe driver. Talks to the REST API directly rather than the
 * stripe-php SDK so we keep the dependency count low and the wire
 * format reviewable in one place.
 *
 * Webhooks carry `Stripe-Signature: t=<unix>,v1=<hex>[,v0=<hex>]` —
 * we verify against the v1 scheme (HMAC-SHA256 of "t.rawBody" against
 * the endpoint secret). v0 (deprecated) is ignored.
 *
 * Idempotency: every charge POST carries an `Idempotency-Key` header
 * derived from the caller's reference, so retried network failures
 * collapse to the same upstream charge.
 */
class StripeGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, ProvidesWebhookTimestamp, RefundsTransactions
{
    protected string $identifier = 'stripe';

    protected string $baseUrl = 'https://api.stripe.com/v1';

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        $payload = [
            'amount' => $request->amount->minor(),
            'currency' => strtolower($request->amount->currency()),
            'description' => $request->description,
            'receipt_email' => $request->customer->email ?? null,
            'metadata' => array_merge(
                ['platform_reference' => $request->reference],
                array_map(fn ($v) => (string) $v, $request->metadata),
            ),
            'automatic_payment_methods' => ['enabled' => 'true'],
        ];

        $payload = array_filter($payload, fn ($v) => $v !== null);

        $response = $this->httpClient()
            ->asForm()
            ->withHeaders([
                'Idempotency-Key' => 'pi-'.hash('sha256', $request->reference),
                'Authorization' => 'Bearer '.$this->secretKey(),
            ])
            ->post("{$this->baseUrl}/payment_intents", $this->flattenForStripe($payload));

        $body = $response->json();
        if ($response->failed() || ! is_array($body) || ! isset($body['id'])) {
            throw new GatewayResponseException(
                'Stripe payment_intent.create failed: '.(string) ($body['error']['message'] ?? $response->status()),
                is_array($body) ? $body : [],
                gatewayCode: (string) ($body['error']['code'] ?? $response->status()),
            );
        }

        return new ChargeResult(
            status: $this->mapStripeStatus((string) ($body['status'] ?? 'requires_action')),
            reference: $request->reference,
            gatewayReference: (string) $body['id'],
            redirectUrl: (string) ($body['next_action']['redirect_to_url']['url'] ?? '') ?: null,
            pollUrl: null,
            instructions: (string) ($body['next_action']['type'] ?? '') ?: null,
            raw: $body,
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        $payload = [
            'payment_intent' => $request->gatewayReference,
            'amount' => $request->amount?->minor(),
            'reason' => 'requested_by_customer',
            'metadata' => ['platform_reference' => $request->reference],
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null);

        $response = $this->httpClient()
            ->asForm()
            ->withHeaders([
                'Idempotency-Key' => 're-'.hash('sha256', $request->reference.'|'.($request->gatewayReference ?? '')),
                'Authorization' => 'Bearer '.$this->secretKey(),
            ])
            ->post("{$this->baseUrl}/refunds", $this->flattenForStripe($payload));

        $body = $response->json();
        if ($response->failed() || ! is_array($body) || ! isset($body['id'])) {
            return new RefundResult(
                status: PaymentStatus::FAILED,
                reference: $request->reference,
                gatewayReference: $request->gatewayReference,
                raw: is_array($body) ? $body : ['error' => 'stripe_refund_failed'],
            );
        }

        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            reference: $request->reference,
            gatewayReference: (string) $body['id'],
            raw: $body,
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        if ($gatewayReference === null || $gatewayReference === '') {
            throw new GatewayResponseException('Stripe status lookup requires the payment_intent id.');
        }

        $response = $this->httpClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->secretKey()])
            ->get("{$this->baseUrl}/payment_intents/".rawurlencode($gatewayReference));

        $body = $response->json();
        $status = is_array($body) ? (string) ($body['status'] ?? 'requires_action') : 'requires_action';

        return new StatusResult(
            status: $this->mapStripeStatus($status),
            reference: $reference,
            gatewayReference: $gatewayReference,
            message: $status,
            raw: is_array($body) ? $body : [],
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) $this->config('webhook_secret', '', required: true);
        $header = (string) $request->header('Stripe-Signature', '');
        if ($header === '') {
            throw new WebhookSignatureException('Stripe webhook missing Stripe-Signature header.');
        }

        [$timestamp, $v1Sig] = $this->parseSignatureHeader($header);
        if ($timestamp === null || $v1Sig === '') {
            throw new WebhookSignatureException('Stripe webhook signature header malformed.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $v1Sig)) {
            throw new WebhookSignatureException('Stripe webhook v1 signature mismatch.');
        }

        return true;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $body = $request->json()->all();
        if (! is_array($body) || ! isset($body['type'])) {
            return null;
        }

        $type = (string) $body['type'];
        $object = (array) ($body['data']['object'] ?? []);

        $status = match (true) {
            str_starts_with($type, 'payment_intent.succeeded') => PaymentStatus::CAPTURED,
            str_starts_with($type, 'payment_intent.processing') => PaymentStatus::PENDING,
            str_starts_with($type, 'payment_intent.requires_action') => PaymentStatus::PENDING,
            str_starts_with($type, 'payment_intent.payment_failed') => PaymentStatus::FAILED,
            str_starts_with($type, 'payment_intent.canceled') => PaymentStatus::CANCELLED,
            str_starts_with($type, 'charge.refunded') => PaymentStatus::REFUNDED,
            str_starts_with($type, 'charge.dispute.created') => PaymentStatus::DISPUTED,
            default => PaymentStatus::PENDING,
        };

        return new WebhookEvent(
            status: $status,
            reference: (string) ($object['metadata']['platform_reference'] ?? '') ?: null,
            gatewayReference: (string) ($object['id'] ?? '') ?: null,
            eventType: $type,
            amount: null,
            raw: $body,
        );
    }

    public function webhookTimestamp(Request $request): ?int
    {
        $header = (string) $request->header('Stripe-Signature', '');
        if ($header === '') {
            return null;
        }
        [$ts] = $this->parseSignatureHeader($header);

        return $ts === null ? null : (int) $ts;
    }

    public function webhookReplayToleranceSeconds(): int
    {
        return (int) $this->config('replay_tolerance_seconds', 300);
    }

    /* ─────────────────────── internals ─────────────────────── */

    protected function secretKey(): string
    {
        return (string) $this->config('secret_key', '', required: true);
    }

    /**
     * Stripe expects nested params as `metadata[key]=value` form fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function flattenForStripe(array $payload, string $prefix = ''): array
    {
        $out = [];
        foreach ($payload as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}[{$k}]";
            if (is_array($v)) {
                $out = array_merge($out, $this->flattenForStripe($v, $key));
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    protected function mapStripeStatus(string $stripeStatus): PaymentStatus
    {
        return match ($stripeStatus) {
            'succeeded' => PaymentStatus::CAPTURED,
            'requires_payment_method', 'requires_action', 'requires_confirmation', 'processing' => PaymentStatus::PENDING,
            'requires_capture' => PaymentStatus::AUTHORIZED,
            'canceled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * @return array{0: ?string, 1: string}  [timestamp, v1 hex sig]
     */
    protected function parseSignatureHeader(string $header): array
    {
        $ts = null;
        $v1 = '';
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($k === 't') {
                $ts = $v;
            } elseif ($k === 'v1' && $v1 === '') {
                $v1 = $v;
            }
        }

        return [$ts, $v1];
    }
}
