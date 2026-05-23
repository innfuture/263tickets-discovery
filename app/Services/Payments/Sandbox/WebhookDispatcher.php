<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\SandboxState;
use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Sandbox\Signers\SignerRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbox + dispatcher (§3.4). Two responsibilities:
 *
 *   1. Persist a queued event for every scenario step. Webhooks become
 *      data — replayable, modifiable, asserted on in tests.
 *   2. Deliver due events to merchant.webhook_endpoint with the right
 *      signature for the emulated provider.
 *
 * `flushDue()` is called both by the scheduled worker (real time) and
 * by the test trait after `SandboxClock::advance()` so virtual-time
 * advances collapse to a synchronous delivery.
 */
class WebhookDispatcher
{
    /** @var array<int, int>  webhook id → forced fail attempts remaining */
    protected array $forceFail = [];

    public function __construct(
        protected SignerRegistry $signers,
        protected SandboxClock $clock,
        protected StateMachine $machine,
        protected BalanceLedger $ledger,
    ) {}

    public function enqueue(
        SandboxMerchant $merchant,
        SandboxTransaction $transaction,
        string $type,
        int $scheduledDelaySeconds = 0,
        int $failNextAttempts = 0,
        ?SandboxState $followUpStateOnDelivery = null,
    ): SandboxWebhookOutbox {
        $payload = $this->payloadFor($transaction, $type, $followUpStateOnDelivery);

        $event = SandboxWebhookOutbox::create([
            'sandbox_merchant_id' => $merchant->id,
            'sandbox_transaction_id' => $transaction->id,
            'type' => $type,
            'emulate' => $transaction->emulate,
            'payload' => $payload,
            'scheduled_for' => now()->addSeconds(max(0, $scheduledDelaySeconds)),
            'status' => 'queued',
        ]);

        if ($failNextAttempts > 0) {
            $this->forceFail[$event->id] = $failNextAttempts;
        }

        return $event;
    }

    /**
     * Deliver every event whose scheduled time has passed. Returns the
     * delivered events. Safe to call repeatedly.
     *
     * @return array<int, SandboxWebhookOutbox>
     */
    public function flushDue(?SandboxMerchant $merchant = null): array
    {
        $query = SandboxWebhookOutbox::query()
            ->where('status', 'queued')
            ->where('scheduled_for', '<=', now());

        if ($merchant !== null) {
            $query->where('sandbox_merchant_id', $merchant->id);
        }

        $delivered = [];
        foreach ($query->orderBy('scheduled_for')->orderBy('id')->get() as $event) {
            try {
                $this->deliver($event);
            } catch (Throwable $e) {
                Log::warning('sandbox.webhook.deliver.failed', [
                    'event_id' => $event->event_id,
                    'message' => $e->getMessage(),
                ]);
            }
            $delivered[] = $event->refresh();
        }

        return $delivered;
    }

    /**
     * Deliver one event. Mutates the row with attempt count + result.
     */
    public function deliver(SandboxWebhookOutbox $event): void
    {
        $event->update([
            'status' => 'delivering',
            'attempts' => $event->attempts + 1,
            'last_attempt_at' => now(),
        ]);

        // Forced-failure override (failNextAttempts).
        if (($this->forceFail[$event->id] ?? 0) > 0) {
            $this->forceFail[$event->id]--;
            $event->update([
                'status' => 'queued',
                'scheduled_for' => now()->addSeconds(min(60, 2 ** $event->attempts)),
                'response_status' => 503,
                'response_body' => 'sandbox: forced failure',
            ]);

            return;
        }

        $merchant = $event->merchant;
        if (! is_string($merchant->webhook_endpoint) || $merchant->webhook_endpoint === '') {
            // No endpoint configured: mark delivered (test mode where
            // consumers read the outbox directly via assertions).
            $event->update(['status' => 'delivered', 'response_status' => 0]);
            $this->applyFollowUpIfAny($event);

            return;
        }

        $signer = $this->signers->for($event->emulate);
        $body = $signer->body($event);
        $headers = $signer->headers($event, $merchant);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->withBody($body, $headers['Content-Type'] ?? 'application/json')
                ->post($merchant->webhook_endpoint);

            $event->update([
                'status' => $response->successful() ? 'delivered' : 'queued',
                'response_status' => $response->status(),
                'response_body' => substr((string) $response->body(), 0, 1000),
                'signature' => $headers['Stripe-Signature'] ?? null,
                'headers' => $headers,
                'scheduled_for' => $response->successful() ? $event->scheduled_for : $this->nextAttemptAt($event),
            ]);

            if ($response->successful()) {
                $this->applyFollowUpIfAny($event);
            }
        } catch (Throwable $e) {
            $event->update([
                'status' => $event->attempts >= 14 ? 'failed' : 'queued',
                'response_status' => 0,
                'response_body' => $e->getMessage(),
                'scheduled_for' => $this->nextAttemptAt($event),
            ]);
        }
    }

    /**
     * Replay an existing event — same payload, fresh delivery attempt.
     */
    public function replay(SandboxWebhookOutbox $event): void
    {
        $event->update(['status' => 'queued', 'scheduled_for' => now()]);
        $this->deliver($event->refresh());
    }

    public function drop(SandboxWebhookOutbox $event): void
    {
        $event->update(['status' => 'dropped']);
    }

    /**
     * Inject a hand-crafted event — bypasses scenario flow. Useful for
     * testing consumer's handling of unexpected event types.
     *
     * @param  array<string, mixed>  $payload
     */
    public function inject(SandboxMerchant $merchant, string $type, array $payload, ?SandboxTransaction $transaction = null): SandboxWebhookOutbox
    {
        return SandboxWebhookOutbox::create([
            'sandbox_merchant_id' => $merchant->id,
            'sandbox_transaction_id' => $transaction?->id,
            'type' => $type,
            'emulate' => $transaction?->emulate ?? $merchant->emulate_default,
            'payload' => $payload,
            'scheduled_for' => now(),
            'status' => 'queued',
        ]);
    }

    /**
     * After a successful delivery, advance the transaction's state to
     * the scenario's follow-up. Mirrors the production gateway timing
     * where the upstream's webhook is what flips us from PENDING.
     */
    protected function applyFollowUpIfAny(SandboxWebhookOutbox $event): void
    {
        $followUp = data_get($event->payload, '_follow_up_state');
        if (! is_string($followUp)) {
            return;
        }

        $state = SandboxState::tryFrom($followUp);
        if ($state === null || $event->transaction === null) {
            return;
        }

        try {
            $this->machine->transition(
                transaction: $event->transaction,
                to: $state,
                actor: 'webhook',
                reason: "webhook:{$event->type}",
            );

            // Credit the merchant ledger when a webhook lands us in
            // CAPTURED — mirrors production where funds move on the
            // capture notification, not synchronously at charge time.
            if ($state === SandboxState::CAPTURED && (int) $event->transaction->amount_captured_minor === 0) {
                $event->transaction->amount_captured_minor = $event->transaction->amount_minor;
                $event->transaction->save();
                $this->ledger->recordCapture($event->transaction);
            }
        } catch (Throwable) {
            // Already there (idempotent) or illegal — both are benign.
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFor(SandboxTransaction $transaction, string $type, ?SandboxState $followUp): array
    {
        return [
            'reference' => $transaction->reference,
            'paynowreference' => $transaction->provider_reference,
            'pollurl' => null,
            'amount' => (int) $transaction->amount_minor,
            'currency' => $transaction->currency,
            'state' => $transaction->state,
            'instrument' => [
                'brand' => $transaction->instrument_brand,
                'last4' => $transaction->instrument_last4,
            ],
            // Drives applyFollowUpIfAny(). Hidden from signer body by
            // each signer's payload shape — they consume only public
            // fields and ignore underscore-prefixed keys.
            '_follow_up_state' => $followUp?->value,
        ];
    }

    protected function nextAttemptAt(SandboxWebhookOutbox $event): CarbonInterface
    {
        // Exponential backoff: 1s, 5s, 30s, 5m, 30m, 2h, 12h, 24h.
        $delays = [1, 5, 30, 300, 1800, 7200, 43_200, 86_400];
        $idx = min(count($delays) - 1, max(0, $event->attempts - 1));

        return now()->addSeconds($delays[$idx]);
    }
}
