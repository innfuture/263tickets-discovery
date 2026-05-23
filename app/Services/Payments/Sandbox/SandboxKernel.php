<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\PaymentStatus;
use App\Enums\SandboxScenario;
use App\Enums\SandboxState;
use App\Models\SandboxDispute;
use App\Models\SandboxIdempotencyKey;
use App\Models\SandboxMerchant;
use App\Models\SandboxPaymentMethod;
use App\Models\SandboxRefund;
use App\Models\SandboxTransaction;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Data\StatusResult;
use App\Services\Payments\Sandbox\Exceptions\IllegalTransitionException;
use App\Services\Payments\Sandbox\Exceptions\SandboxProductionGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The sandbox's main entry point. Receives provider-agnostic requests
 * from SandboxGateway, applies the resolved Outcome to a transaction
 * row, schedules webhooks via the dispatcher, and returns a result the
 * driver translates back to ChargeResult / RefundResult / StatusResult.
 *
 * Production guard runs in the constructor (§9).
 */
class SandboxKernel
{
    public function __construct(
        protected SandboxClock $clock,
        protected StateMachine $machine,
        protected ScenarioResolver $resolver,
        protected MagicValues $magic,
        protected WebhookDispatcher $webhooks,
        protected BalanceLedger $ledger,
        protected SavedMethods $savedMethods,
        protected WalletTokenSimulator $wallets,
    ) {
        if (app()->environment('production') && ! (bool) config('payments.sandbox.allow_production')) {
            throw new SandboxProductionGuardException(
                'SandboxKernel cannot run in production unless payments.sandbox.allow_production=true.',
            );
        }
    }

    public function charge(SandboxMerchant $merchant, ChargeRequest $request): SandboxTransaction
    {
        // Idempotency-Key (§3.5) — if present and matches a recent
        // request, return the cached transaction directly.
        $idemKey = (string) ($request->metadata['idempotency_key'] ?? '');
        $cached = $idemKey !== '' ? $this->lookupIdempotent($merchant, $idemKey) : null;
        if ($cached !== null) {
            return $cached;
        }

        // Hydrate any saved-method / wallet metadata so the resolver
        // and instrument snapshot can treat them like inline values.
        [$request, $savedMethod] = $this->preprocessRequest($merchant, $request);

        // Off-session SCA check (§5.1). Reject before creating the row.
        $offSessionReject = $this->savedMethods->offSessionRejectionFor($request, $savedMethod);
        if ($offSessionReject !== null) {
            return $this->buildFailedTransaction($merchant, $request, $offSessionReject);
        }

        $outcome = $this->resolver->resolve($request);
        $scenario = $this->resolver->resolveScenario($request);

        $transaction = $this->createTransaction($merchant, $request, $scenario);

        // Stamp network_transaction_id on first successful auth so the
        // saved method becomes off-session-eligible later.
        if ($savedMethod !== null && $outcome->immediateState !== SandboxState::FAILED) {
            $this->savedMethods->markAuthenticated($savedMethod);
        }

        // Synchronously land on the immediate state. Going through the
        // machine (not a direct UPDATE) so the state log is populated.
        try {
            $this->machine->transition(
                transaction: $transaction,
                to: $outcome->immediateState,
                actor: 'scenario',
                reason: $scenario->value,
                context: ['outcome' => 'immediate'],
            );
        } catch (IllegalTransitionException) {
            // Only happens if INITIATED → outcome state is disallowed,
            // which the machine permits for everything in TRANSITIONS.
            // Defensive — should be unreachable.
            throw new \LogicException("Scenario {$scenario->value} produced unreachable transition.");
        }

        // Stamp scenario metadata onto the row.
        $transaction->reason_code = $outcome->reasonCode;
        $transaction->redirect_url = $outcome->redirectUrl;
        $transaction->response_body = $this->buildResponseBody($transaction, $outcome);
        $transaction->expires_at = $outcome->immediateState === SandboxState::AUTHORIZED
            ? $this->clock->now($merchant)->addSeconds(
                (int) config('payments.sandbox.auth_expiry_seconds', 604800), // 7d default (§15 #7)
            )
            : $transaction->expires_at;
        $transaction->save();

        // Schedule webhooks per outcome. The dispatcher persists rows
        // to sandbox_webhook_outbox; a worker (or flushSync in tests)
        // delivers them.
        $this->scheduleWebhooks($transaction, $outcome);

        if ($idemKey !== '') {
            $this->storeIdempotent($merchant, $idemKey, $transaction, $request);
        }

        return $transaction->fresh();
    }

    public function capture(SandboxTransaction $transaction, ?int $amountMinor = null): SandboxTransaction
    {
        $captureAmount = $amountMinor ?? $transaction->amount_minor;

        if ($captureAmount > $transaction->amount_minor) {
            throw new \InvalidArgumentException('Capture amount exceeds authorised amount.');
        }

        $this->machine->transition(
            transaction: $transaction,
            to: SandboxState::CAPTURED,
            actor: 'user',
            reason: 'capture',
            context: ['amount_minor' => $captureAmount],
        );

        $transaction->amount_captured_minor = $captureAmount;
        $transaction->save();

        $this->ledger->recordCapture($transaction, $captureAmount);

        $this->webhooks->enqueue(
            merchant: $transaction->merchant,
            transaction: $transaction,
            type: 'payment.captured',
            scheduledDelaySeconds: 1,
        );

        return $transaction->fresh();
    }

    public function void(SandboxTransaction $transaction): SandboxTransaction
    {
        $this->machine->transition(
            transaction: $transaction,
            to: SandboxState::VOIDED,
            actor: 'user',
            reason: 'void_requested',
        );

        $this->webhooks->enqueue(
            merchant: $transaction->merchant,
            transaction: $transaction,
            type: 'payment.voided',
            scheduledDelaySeconds: 1,
        );

        return $transaction->fresh();
    }

    public function refund(SandboxTransaction $transaction, RefundRequest $request): SandboxRefund
    {
        return DB::transaction(function () use ($transaction, $request) {
            $amount = $request->amount?->amountMinor ?? $transaction->amount_minor;
            $alreadyRefunded = (int) $transaction->amount_refunded_minor;
            $remaining = $transaction->amount_minor - $alreadyRefunded;

            if ($amount > $remaining) {
                throw new \InvalidArgumentException(
                    "Refund of {$amount} exceeds remaining capturable {$remaining}.",
                );
            }

            $refund = SandboxRefund::create([
                'sandbox_transaction_id' => $transaction->id,
                'amount_minor' => $amount,
                'currency' => $transaction->currency,
                'state' => 'succeeded',
                'reason' => $request->reason,
                'response_body' => ['simulated' => true, 'reference' => $request->reference],
            ]);

            $transaction->amount_refunded_minor = $alreadyRefunded + $amount;
            $newState = $transaction->amount_refunded_minor >= $transaction->amount_minor
                ? SandboxState::REFUNDED
                : SandboxState::PART_REFUNDED;

            $this->machine->transition(
                transaction: $transaction,
                to: $newState,
                actor: 'user',
                reason: 'refund',
                context: ['refund_uuid' => $refund->uuid, 'amount_minor' => $amount],
            );

            $this->ledger->recordRefund($refund);

            $this->webhooks->enqueue(
                merchant: $transaction->merchant,
                transaction: $transaction,
                type: $newState === SandboxState::REFUNDED ? 'refund.succeeded' : 'refund.partial_succeeded',
                scheduledDelaySeconds: 1,
            );

            return $refund;
        });
    }

    public function dispute(SandboxTransaction $transaction, string $reasonCode, ?int $amountMinor = null): SandboxDispute
    {
        return DB::transaction(function () use ($transaction, $reasonCode, $amountMinor) {
            $this->machine->transition(
                transaction: $transaction,
                to: SandboxState::DISPUTED,
                actor: 'user',
                reason: "dispute_opened: {$reasonCode}",
            );

            $dispute = SandboxDispute::create([
                'sandbox_transaction_id' => $transaction->id,
                'amount_minor' => $amountMinor ?? $transaction->amount_minor,
                'currency' => $transaction->currency,
                'reason_code' => $reasonCode,
                'status' => 'open',
                'evidence_due_at' => $this->clock->now($transaction->merchant)->addDays(7),
            ]);

            $this->ledger->recordChargeback($dispute);

            $this->webhooks->enqueue(
                merchant: $transaction->merchant,
                transaction: $transaction,
                type: 'charge.disputed',
                scheduledDelaySeconds: 1,
            );

            return $dispute;
        });
    }

    /**
     * Read-only ledger accessor — exposed so the dashboard controller
     * and tests don't have to take a separate dependency.
     */
    public function ledger(): BalanceLedger
    {
        return $this->ledger;
    }

    /**
     * Inflate `payment_method_token` and `wallet` metadata into the
     * inline `pan`/instrument fields the resolver + snapshot expect.
     * Mutates a copy of the request and returns it alongside the
     * saved-method record (or null) for downstream side effects.
     *
     * @return array{0: ChargeRequest, 1: ?SandboxPaymentMethod}
     */
    protected function preprocessRequest(SandboxMerchant $merchant, ChargeRequest $request): array
    {
        $metadata = $request->metadata;
        $saved = $this->savedMethods->hydrate($request);

        if ($saved !== null) {
            // Reconstruct enough of a magic-PAN to drive the resolver.
            // We don't keep the original digits — use the per-merchant
            // metadata stamped at save() time, falling back to a brand-
            // typical success PAN.
            $metadata['pan'] = $metadata['pan'] ?? $this->panForSavedMethod($saved);
            $metadata['method'] = $metadata['method'] ?? 'card';
        }

        // Wallet tokens become instrument metadata + a deterministic PAN
        // pulled from MagicValues so the scenario flow still works.
        $wallet = $metadata['wallet'] ?? null;
        if (is_array($wallet) && isset($wallet['provider'])) {
            $decoded = $this->wallets->decode((string) $wallet['provider'], $wallet);
            $metadata['wallet_decoded'] = $decoded;
            $metadata['method'] = 'wallet';
            // A wallet always implies 3DS-style liability shift, so map
            // to the 3DS frictionless scenario unless caller already set
            // one explicitly.
            $metadata['scenario'] = $metadata['scenario'] ?? SandboxScenario::THREE_DS_FRICTIONLESS->value;
            $metadata['pan'] = $metadata['pan'] ?? '4242424242424242';
        }

        $hydrated = new ChargeRequest(
            amount: $request->amount,
            customer: $request->customer,
            reference: $request->reference,
            description: $request->description,
            returnUrl: $request->returnUrl,
            resultUrl: $request->resultUrl,
            metadata: $metadata,
        );

        return [$hydrated, $saved];
    }

    protected function panForSavedMethod(SandboxPaymentMethod $method): string
    {
        return match ($method->brand) {
            'mastercard' => '5555555555554444',
            'amex' => '378282246310005',
            'discover' => '6011111111111117',
            default => '4242424242424242',
        };
    }

    /**
     * Short-circuit charge path for guard-rail rejections (off-session
     * without NTI, currency mismatch handled in driver). Persists a
     * FAILED row and emits a single payment.failed webhook so the host
     * app observes the same shape as a real decline.
     */
    protected function buildFailedTransaction(
        SandboxMerchant $merchant,
        ChargeRequest $request,
        string $reasonCode,
    ): SandboxTransaction {
        $transaction = $this->createTransaction($merchant, $request, SandboxScenario::DECLINE_DO_NOT_HONOR);

        $this->machine->transition(
            transaction: $transaction,
            to: SandboxState::FAILED,
            actor: 'scenario',
            reason: $reasonCode,
        );

        $transaction->reason_code = $reasonCode;
        $transaction->response_body = ['simulated' => true, 'reason_code' => $reasonCode];
        $transaction->save();

        $this->webhooks->enqueue(
            merchant: $merchant,
            transaction: $transaction,
            type: 'payment.failed',
            scheduledDelaySeconds: 1,
        );

        return $transaction->fresh();
    }

    public function status(SandboxTransaction $transaction): StatusResult
    {
        return new StatusResult(
            status: $transaction->stateEnum->toPaymentStatus(),
            reference: $transaction->reference ?? $transaction->uuid,
            gatewayReference: $transaction->provider_reference,
            message: $transaction->reason_code,
            raw: (array) $transaction->response_body,
        );
    }

    /**
     * Translate a sandbox transaction row to the driver-facing
     * ChargeResult shape. Centralised so PaymentStatus + URLs map the
     * same way whether returned by charge() or status().
     */
    public function toChargeResult(SandboxTransaction $transaction): ChargeResult
    {
        return new ChargeResult(
            status: $this->mapPaymentStatus($transaction->stateEnum),
            reference: $transaction->reference ?? $transaction->uuid,
            gatewayReference: $transaction->provider_reference,
            redirectUrl: $transaction->redirect_url,
            pollUrl: route('payments.sandbox.transaction.show', ['transaction' => $transaction->uuid]),
            instructions: data_get($transaction->response_body, 'instructions'),
            raw: (array) $transaction->response_body,
        );
    }

    protected function mapPaymentStatus(SandboxState $state): PaymentStatus
    {
        return $state->toPaymentStatus();
    }

    protected function createTransaction(
        SandboxMerchant $merchant,
        ChargeRequest $request,
        SandboxScenario $scenario,
    ): SandboxTransaction {
        $instrument = $this->instrumentSnapshot($request);

        return SandboxTransaction::create([
            'sandbox_merchant_id' => $merchant->id,
            'emulate' => (string) ($request->metadata['emulate'] ?? $merchant->emulate_default),
            'method' => (string) ($request->metadata['method'] ?? 'card'),
            'state' => SandboxState::INITIATED->value,
            'scenario' => $scenario->value,
            'amount_minor' => $request->amount->amountMinor,
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
            'idempotency_key' => $request->metadata['idempotency_key'] ?? null,
            'external_id' => $request->metadata['external_id'] ?? null,
            'provider_reference' => $this->generateProviderReference((string) ($request->metadata['emulate'] ?? 'stripe')),
            'instrument_brand' => $instrument['brand'] ?? null,
            'instrument_last4' => $instrument['last4'] ?? null,
            'instrument_country' => $instrument['country'] ?? null,
            'instrument_meta' => $instrument['meta'] ?? null,
            'customer_email' => $request->customer->email,
            'customer_msisdn' => $request->customer->normalisedMsisdn(),
            'customer_name' => $request->customer->name,
            'customer_ip' => $request->customer->ipAddress,
            'return_url' => $request->returnUrl,
            'result_url' => $request->resultUrl,
            'request_body' => $this->snapshotRequest($request),
            'metadata' => $request->metadata,
        ]);
    }

    /**
     * @return array{brand?: string, last4?: string, country?: string, meta?: array<string, mixed>}
     */
    protected function instrumentSnapshot(ChargeRequest $request): array
    {
        $pan = $request->metadata['pan'] ?? $request->metadata['instrument'] ?? null;
        if (! is_string($pan) || $pan === '') {
            return [];
        }

        $card = $this->magic->lookupCard($pan);
        if ($card !== null) {
            $digits = preg_replace('/\D+/', '', $pan) ?? '';

            return [
                'brand' => $card['brand'],
                'last4' => substr($digits, -4),
                'country' => $card['country'],
                'meta' => ['bin' => substr($digits, 0, 6)],
            ];
        }

        // PCI guard (§9): don't store unknown PANs. Hash and drop.
        $digits = preg_replace('/\D+/', '', $pan) ?? '';

        return [
            'last4' => $digits === '' ? null : substr($digits, -4),
            'meta' => ['fingerprint' => hash('sha256', $digits)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotRequest(ChargeRequest $request): array
    {
        return [
            'amount' => $request->amount->major(),
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
            'description' => $request->description,
            'customer' => array_filter([
                'name' => $request->customer->name,
                'email' => $request->customer->email,
                'msisdn' => $request->customer->normalisedMsisdn(),
            ]),
            'return_url' => $request->returnUrl,
            'metadata_keys' => array_keys($request->metadata),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildResponseBody(SandboxTransaction $transaction, Outcome $outcome): array
    {
        return [
            'simulated' => true,
            'gateway' => $transaction->emulate,
            'state' => $transaction->state,
            'reason_code' => $outcome->reasonCode,
            'redirect_url' => $outcome->redirectUrl,
            'instructions' => $outcome->instructions,
            'webhook_events' => $outcome->webhookEvents,
        ];
    }

    protected function generateProviderReference(string $emulate): string
    {
        $prefix = match ($emulate) {
            'stripe' => 'pi_',
            'adyen' => 'PSP_',
            'paynow', 'pesepay' => 'sbx_',
            'ecocash' => 'EC_',
            'zimswitch' => 'opp_',
            default => 'sbx_',
        };

        return $prefix.bin2hex(random_bytes(10));
    }

    /**
     * Schedule the webhook chain a scenario calls for. Honors:
     *   webhookDelaySeconds   timing
     *   duplicateCount        same event N times (idempotency tests)
     *   outOfOrder            second event before first (consumer-recovery tests)
     *   failNextWebhooks      first N HTTP 500s before delivery succeeds
     */
    protected function scheduleWebhooks(SandboxTransaction $transaction, Outcome $outcome): void
    {
        $events = $outcome->webhookEvents;
        if ($events === []) {
            return;
        }

        if ($outcome->outOfOrder && count($events) >= 2) {
            [$events[0], $events[1]] = [$events[1], $events[0]];
        }

        foreach ($events as $i => $type) {
            $delay = $outcome->webhookDelaySeconds * max(1, $i + 1);
            for ($d = 0; $d < $outcome->duplicateCount; $d++) {
                $this->webhooks->enqueue(
                    merchant: $transaction->merchant,
                    transaction: $transaction,
                    type: $type,
                    scheduledDelaySeconds: $delay + $d,
                    failNextAttempts: $i === 0 ? $outcome->failNextWebhooks : 0,
                    followUpStateOnDelivery: $type === end($events) ? $outcome->followUpState : null,
                );
            }
        }
    }

    /* ────────────────────── idempotency ────────────────────── */

    protected function lookupIdempotent(SandboxMerchant $merchant, string $key): ?SandboxTransaction
    {
        $row = SandboxIdempotencyKey::query()
            ->where('sandbox_merchant_id', $merchant->id)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        $transactionId = (int) ($row->response_body['transaction_id'] ?? 0);
        if ($transactionId === 0) {
            return null;
        }

        return SandboxTransaction::find($transactionId);
    }

    protected function storeIdempotent(
        SandboxMerchant $merchant,
        string $key,
        SandboxTransaction $transaction,
        ChargeRequest $request,
    ): void {
        $ttl = (int) config('payments.sandbox.idempotency_ttl_seconds', 86400); // 24h (§15 #8)

        SandboxIdempotencyKey::updateOrCreate(
            ['sandbox_merchant_id' => $merchant->id, 'key' => $key],
            [
                'request_hash' => hash('sha256', json_encode($this->snapshotRequest($request))),
                'response_status' => 200,
                'response_body' => ['transaction_id' => $transaction->id, 'transaction_uuid' => $transaction->uuid],
                'expires_at' => now()->addSeconds($ttl),
            ],
        );
    }

    /* ────────────────────── helpers ────────────────────── */

    /**
     * Resolve the merchant to use for a charge. Order of precedence:
     *   1. Explicit `sandbox_merchant_slug` in request metadata.
     *   2. `payments.sandbox.default_merchant` config value.
     *   3. The first merchant in the database (test convenience).
     */
    public function resolveMerchant(ChargeRequest $request): SandboxMerchant
    {
        $slug = $request->metadata['sandbox_merchant_slug']
            ?? config('payments.sandbox.default_merchant')
            ?? null;

        if (is_string($slug) && $slug !== '') {
            $found = SandboxMerchant::query()->where('slug', $slug)->first();
            if ($found !== null) {
                return $found;
            }
        }

        return SandboxMerchant::query()->firstOrCreate(
            ['slug' => 'sandbox-default'],
            [
                'name' => 'Sandbox default merchant',
                'emulate_default' => 'stripe',
                'default_currency' => 'USD',
                'environment' => 'test',
            ],
        );
    }

    public function clock(): SandboxClock
    {
        return $this->clock;
    }

    public function webhooks(): WebhookDispatcher
    {
        return $this->webhooks;
    }

    /**
     * Generate a sandbox idempotency key prefix that won't collide
     * with caller-supplied ones. Helpful in tests.
     */
    public static function generateIdempotencyKey(): string
    {
        return 'sbx_idem_'.(string) Str::ulid();
    }
}
