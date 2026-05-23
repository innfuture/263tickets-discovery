<?php

namespace App\Http\Controllers\Settings\Operations;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationWebhook;
use App\Models\WebhookDelivery;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational webhooks — endpoint URLs that receive HTTP callbacks on
 * platform events. Each endpoint owns a signing secret callers verify
 * against the `X-Signature` header (HMAC-SHA256 over the raw body).
 */
class WebhookController extends SettingsController
{
    public const EVENT_TYPES = [
        'ticket.sold', 'ticket.refunded', 'ticket.scanned',
        'event.published', 'event.cancelled',
        'inventory.low', 'payout.received', 'invoice.paid',
    ];

    private const CHANNEL = 'operations';

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'webhook.manage');

        $webhooks = OrganizationWebhook::query()
            ->where('organization_id', $org->id)
            ->where('channel', self::CHANNEL)
            ->withCount('deliveries')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationWebhook $w) => $this->shape($w));

        $recentDeliveries = WebhookDelivery::query()
            ->whereIn('organization_webhook_id', $webhooks->pluck('id'))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'webhook_id' => $d->organization_webhook_id,
                'event' => $d->event,
                'status_code' => $d->status_code,
                'duration_ms' => $d->duration_ms,
                'error' => $d->error,
                'attempt' => $d->attempt,
                'created_at' => $d->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/operations/webhooks', [
            'webhooks' => $webhooks,
            'recentDeliveries' => $recentDeliveries,
            'eventTypes' => self::EVENT_TYPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Webhooks', 'href' => '/settings/operations/webhooks'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['in:'.implode(',', self::EVENT_TYPES)],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $webhook = OrganizationWebhook::create([
            ...$data,
            'organization_id' => $org->id,
            'channel' => self::CHANNEL,
            'signing_secret' => 'whsec_'.Str::random(48),
            'is_active' => true,
        ]);

        $audit->record('webhook.created', $org, $request->user(), 'organization_webhook', (string) $webhook->id, after: ['label' => $webhook->label]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook created. Use the signing secret to verify request bodies.')]);

        return back();
    }

    public function update(Request $request, OrganizationWebhook $webhook, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');
        abort_if($webhook->organization_id !== $org->id, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['in:'.implode(',', self::EVENT_TYPES)],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
            'is_active' => ['required', 'boolean'],
        ]);

        $before = $webhook->only(['label', 'url', 'events', 'retry_limit', 'is_active']);
        $webhook->update($data);

        $audit->record('webhook.updated', $org, $request->user(), 'organization_webhook', (string) $webhook->id, before: $before, after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook saved.')]);

        return back();
    }

    public function rotateSecret(Request $request, OrganizationWebhook $webhook): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');
        abort_if($webhook->organization_id !== $org->id, 403);

        $webhook->update(['signing_secret' => 'whsec_'.Str::random(48)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signing secret rotated.')]);

        return back();
    }

    public function destroy(Request $request, OrganizationWebhook $webhook, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');
        abort_if($webhook->organization_id !== $org->id, 403);

        $audit->record('webhook.deleted', $org, $request->user(), 'organization_webhook', (string) $webhook->id, before: ['label' => $webhook->label]);
        $webhook->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook deleted.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(OrganizationWebhook $w): array
    {
        return [
            'id' => $w->id,
            'label' => $w->label,
            'url' => $w->url,
            'events' => $w->events,
            'signing_secret' => $w->signing_secret,
            'is_active' => $w->is_active,
            'retry_limit' => $w->retry_limit,
            'deliveries_count' => $w->deliveries_count ?? 0,
            'last_delivered_at' => $w->last_delivered_at?->toIso8601String(),
            'created_at' => $w->created_at->toIso8601String(),
        ];
    }
}
