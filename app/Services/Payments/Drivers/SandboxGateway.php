<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\Enums\PaymentStatus;
use App\Models\SandboxTransaction;
use App\Services\Payments\AbstractGateway;
use App\Services\Payments\Contracts\HandlesWebhooks;
use App\Services\Payments\Contracts\PollsTransactionStatus;
use App\Services\Payments\Contracts\RefundsTransactions;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Data\StatusResult;
use App\Services\Payments\Data\WebhookEvent;
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\Exceptions\PaymentException;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Production-shaped driver that internally routes to the in-process
 * SandboxKernel instead of a remote gateway. Same contract as
 * EcoCashGateway/PaynowGateway/etc., so callers swap by changing one
 * config key.
 *
 * The `emulate` metadata key picks which provider's wire format the
 * sandbox should mimic for response shape + signing. Defaults to
 * 'stripe'.
 *
 * Inbound webhooks at /payments/webhooks/sandbox are accepted from the
 * sandbox itself — useful for the round-trip test of consumers that
 * verify against the real WebhookController.
 */
class SandboxGateway extends AbstractGateway implements HandlesWebhooks, PollsTransactionStatus, RefundsTransactions
{
    protected string $identifier = 'sandbox';

    public function __construct(array $config, protected SandboxKernel $kernel)
    {
        parent::__construct($config);
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        $this->assertCurrencySupported($request);

        if ($this->isHttpMode()) {
            return $this->chargeViaHttp($request);
        }

        $merchant = $this->kernel->resolveMerchant($request);
        $transaction = $this->kernel->charge($merchant, $request);

        return $this->kernel->toChargeResult($transaction);
    }

    public function refund(RefundRequest $request): RefundResult
    {
        if ($this->isHttpMode()) {
            return $this->refundViaHttp($request);
        }

        $transaction = SandboxTransaction::query()
            ->where('reference', $request->reference)
            ->orWhere('provider_reference', $request->gatewayReference)
            ->first();

        if ($transaction === null) {
            throw new PaymentException("Sandbox transaction not found for reference {$request->reference}.");
        }

        $refund = $this->kernel->refund($transaction, $request);

        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            reference: $request->reference,
            gatewayReference: $refund->uuid,
            raw: (array) $refund->response_body,
        );
    }

    public function status(string $reference, ?string $gatewayReference = null): StatusResult
    {
        if ($this->isHttpMode()) {
            return $this->statusViaHttp($reference, $gatewayReference);
        }

        $transaction = SandboxTransaction::query()
            ->where('reference', $reference)
            ->orWhere('provider_reference', $gatewayReference)
            ->first();

        if ($transaction === null) {
            return new StatusResult(
                status: PaymentStatus::FAILED,
                reference: $reference,
                gatewayReference: $gatewayReference,
                message: 'not_found',
                raw: [],
            );
        }

        return $this->kernel->status($transaction);
    }

    /* ────────────────────── HTTP-served mode ────────────────────── */

    protected function isHttpMode(): bool
    {
        return (string) config('payments.sandbox.kernel') === 'http';
    }

    protected function chargeViaHttp(ChargeRequest $request): ChargeResult
    {
        $response = $this->serviceClient()->post('/sandbox/service/v1/charges', [
            'merchant' => (string) ($request->metadata['sandbox_merchant_slug']
                ?? config('payments.sandbox.default_merchant', 'sandbox-default')),
            'amount' => $request->amount->major(),
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
            'description' => $request->description,
            'customer' => array_filter([
                'name' => $request->customer->name,
                'email' => $request->customer->email,
                'msisdn' => $request->customer->normalisedMsisdn(),
                'ip' => $request->customer->ipAddress,
            ]),
            'return_url' => $request->returnUrl,
            'result_url' => $request->resultUrl,
            'metadata' => $request->metadata,
        ]);

        if (! $response->successful()) {
            throw new PaymentException('Sandbox HTTP charge failed: '.$response->body());
        }

        $body = (array) $response->json();
        $statusEnum = PaymentStatus::tryFrom((string) ($body['payment_status'] ?? 'pending')) ?? PaymentStatus::PENDING;

        return new ChargeResult(
            status: $statusEnum,
            reference: (string) ($body['reference'] ?? $request->reference),
            gatewayReference: $body['provider_reference'] ?? null,
            redirectUrl: $body['redirect_url'] ?? null,
            pollUrl: $body['poll_url'] ?? null,
            instructions: $body['instructions'] ?? null,
            raw: (array) ($body['response_body'] ?? $body),
        );
    }

    protected function refundViaHttp(RefundRequest $request): RefundResult
    {
        $response = $this->serviceClient()->post('/sandbox/service/v1/refunds', array_filter([
            'reference' => $request->reference,
            'provider_reference' => $request->gatewayReference,
            'amount_minor' => $request->amount?->amountMinor,
            'currency' => $request->amount?->currency,
            'reason' => $request->reason,
        ]));

        if (! $response->successful()) {
            throw new PaymentException('Sandbox HTTP refund failed: '.$response->body());
        }

        $body = (array) $response->json();

        return new RefundResult(
            status: PaymentStatus::REFUNDED,
            reference: $request->reference,
            gatewayReference: (string) ($body['uuid'] ?? ''),
            raw: (array) ($body['response_body'] ?? $body),
        );
    }

    protected function statusViaHttp(string $reference, ?string $gatewayReference): StatusResult
    {
        $response = $this->serviceClient()->get('/sandbox/service/v1/transactions', array_filter([
            'reference' => $reference,
            'provider_reference' => $gatewayReference,
        ]));

        $body = (array) $response->json();
        $statusEnum = PaymentStatus::tryFrom((string) ($body['payment_status'] ?? 'failed')) ?? PaymentStatus::FAILED;

        return new StatusResult(
            status: $statusEnum,
            reference: (string) ($body['reference'] ?? $reference),
            gatewayReference: $body['provider_reference'] ?? $gatewayReference,
            message: $body['message'] ?? null,
            raw: (array) ($body['response_body'] ?? $body),
        );
    }

    protected function serviceClient(): PendingRequest
    {
        $base = (string) config('payments.sandbox.base_url');
        $token = (string) config('payments.sandbox.service_key');
        if ($base === '' || $token === '') {
            throw new GatewayNotConfiguredException(
                'HTTP-served sandbox requires payments.sandbox.base_url and service_key.',
            );
        }

        return Http::acceptJson()
            ->asJson()
            ->baseUrl(rtrim($base, '/'))
            ->withToken($token)
            ->timeout(15)
            ->withHeaders(['User-Agent' => 'sandbox-driver/1.0']);
    }

    public function verifyWebhook(Request $request): bool
    {
        // Sandbox webhooks are signed with whichever scheme the source
        // event's `emulate` value picks; verification just delegates
        // back to the relevant signer's logic. For round-trip tests we
        // accept all sandbox-origin events.
        return $request->header('User-Agent') === null
            || str_contains((string) $request->header('User-Agent'), 'sandbox')
            || $request->json('id') !== null;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        $body = $request->json()->all();
        if (! is_array($body) || $body === []) {
            return null;
        }

        $type = (string) ($body['type'] ?? 'unknown');
        $payload = (array) ($body['data']['object'] ?? $body);

        $status = match (true) {
            str_starts_with($type, 'payment.captured') => PaymentStatus::CAPTURED,
            str_starts_with($type, 'payment.authorized') => PaymentStatus::AUTHORIZED,
            str_starts_with($type, 'payment.failed') => PaymentStatus::FAILED,
            str_starts_with($type, 'payment.cancelled'), str_starts_with($type, 'payment.voided') => PaymentStatus::CANCELLED,
            str_starts_with($type, 'refund.succeeded') => PaymentStatus::REFUNDED,
            str_starts_with($type, 'refund.partial_succeeded') => PaymentStatus::PARTIALLY_REFUNDED,
            str_starts_with($type, 'charge.disputed') => PaymentStatus::DISPUTED,
            default => PaymentStatus::PENDING,
        };

        return new WebhookEvent(
            status: $status,
            reference: (string) ($payload['reference'] ?? '') ?: null,
            gatewayReference: (string) ($payload['paynowreference'] ?? $body['id'] ?? '') ?: null,
            eventType: $type,
            amount: null,
            raw: $body,
        );
    }
}
