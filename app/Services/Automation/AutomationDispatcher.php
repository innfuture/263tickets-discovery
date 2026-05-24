<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Jobs\Automation\DeliverWebhookJob;
use App\Models\Organization;
use App\Models\OrganizationWebhook;
use App\Models\WebhookOutboxEntry;

/**
 * The single fan-out point from domain events to every subscribed
 * webhook. Listeners (one per published event type) call this; we
 * filter the org's webhook subscriptions by event type and queue
 * delivery jobs for each match.
 *
 * Why a separate service over Laravel's event system directly:
 *   - Subscriptions are stored in the DB (organization_webhooks),
 *     not registered at boot, so we need a runtime filter step.
 *   - Each delivery has its own retry envelope (5 attempts, 1s →
 *     30min backoff) — easier as a dedicated job than via Laravel
 *     listener retry config.
 *   - Same shape works for *every* event type, including future
 *     ones we haven't enumerated yet.
 */
class AutomationDispatcher
{
    public function dispatch(string $eventType, Organization $org, array $payload): void
    {
        // Always-on transactional outbox path — the drain job decides
        // whether to expand into per-hook DeliverWebhookJobs. Writing
        // here inside the caller's DB transaction is what makes the
        // at-least-once guarantee hold against queue outages.
        if (config('automation.use_outbox', true)) {
            WebhookOutboxEntry::create([
                'event_type' => $eventType,
                'organization_id' => $org->id,
                'payload' => $payload,
                'status' => WebhookOutboxEntry::STATUS_PENDING,
                'available_at' => now(),
            ]);

            return;
        }

        $this->fanOut($eventType, $org, $payload);
    }

    /**
     * Direct fan-out — bypasses the outbox. Called by
     * DispatchPendingOutboxWebhooksJob (draining the outbox) and as
     * the legacy path when `automation.use_outbox=false`.
     */
    public function fanOut(string $eventType, Organization $org, array $payload): void
    {
        $hooks = OrganizationWebhook::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->get()
            // JSON-column whereIn is driver-specific — filter in PHP.
            ->filter(function (OrganizationWebhook $hook) use ($eventType) {
                $events = (array) ($hook->events ?? []);

                return in_array('*', $events, true) || in_array($eventType, $events, true);
            });

        foreach ($hooks as $hook) {
            DeliverWebhookJob::dispatch(
                webhookId: (int) $hook->id,
                eventType: $eventType,
                payload: $payload,
            )->onQueue((string) config('automation.queue', 'automations'));
        }
    }
}
