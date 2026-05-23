<?php

namespace App\Http\Controllers\Settings\Developer;

use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\Operations\WebhookController as OperationsWebhookController;
use App\Models\OrganizationWebhook;
use App\Models\WebhookDelivery;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Developer-tier webhooks. Same plumbing as the operational ones but
 * the page exposes the full delivery log (last 1000) and per-delivery
 * replay. Channel column on the row distinguishes the two streams.
 */
class WebhookController extends SettingsController
{
    private const CHANNEL = 'developer';

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'webhook.manage');

        $webhooks = OrganizationWebhook::query()
            ->where('organization_id', $org->id)
            ->where('channel', self::CHANNEL)
            ->withCount('deliveries')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationWebhook $w) => [
                'id' => $w->id,
                'label' => $w->label,
                'url' => $w->url,
                'events' => $w->events,
                'signing_secret' => $w->signing_secret,
                'is_active' => $w->is_active,
                'retry_limit' => $w->retry_limit,
                'deliveries_count' => $w->deliveries_count ?? 0,
                'last_delivered_at' => $w->last_delivered_at?->toIso8601String(),
            ]);

        $deliveries = WebhookDelivery::query()
            ->whereIn('organization_webhook_id', $webhooks->pluck('id'))
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id' => $d->id,
                'webhook_id' => $d->organization_webhook_id,
                'event' => $d->event,
                'status_code' => $d->status_code,
                'duration_ms' => $d->duration_ms,
                'attempt' => $d->attempt,
                'error' => $d->error,
                'response_body' => $d->response_body,
                'payload' => $d->payload,
                'created_at' => $d->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/developer/webhooks', [
            'webhooks' => $webhooks,
            'deliveries' => $deliveries,
            'eventTypes' => OperationsWebhookController::EVENT_TYPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Webhooks', 'href' => '/settings/developer/webhooks'],
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
            'events.*' => ['in:'.implode(',', OperationsWebhookController::EVENT_TYPES)],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $webhook = OrganizationWebhook::create([
            ...$data,
            'organization_id' => $org->id,
            'channel' => self::CHANNEL,
            'signing_secret' => 'whsec_'.Str::random(48),
            'is_active' => true,
        ]);

        $audit->record('developer_webhook.created', $org, $request->user(), 'organization_webhook', (string) $webhook->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook created.')]);

        return back();
    }

    public function destroy(Request $request, OrganizationWebhook $webhook, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');
        abort_if($webhook->organization_id !== $org->id, 403);

        $audit->record('developer_webhook.deleted', $org, $request->user(), 'organization_webhook', (string) $webhook->id);
        $webhook->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Webhook deleted.')]);

        return back();
    }

    /**
     * Replays a stored delivery — useful when the receiver was down at
     * the time of the original attempt. Records a fresh delivery row
     * with `attempt = previous+1`.
     */
    public function replay(Request $request, WebhookDelivery $delivery): RedirectResponse
    {
        $org = $this->org($request, 'webhook.manage');
        abort_if($delivery->webhook->organization_id !== $org->id, 403);

        $webhook = $delivery->webhook;
        $start = microtime(true);

        try {
            $signature = hash_hmac('sha256', json_encode($delivery->payload), $webhook->signing_secret);
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Signature' => $signature,
                    'X-Event' => $delivery->event,
                    'X-Delivery-Id' => (string) $delivery->id,
                    'Content-Type' => 'application/json',
                ])
                ->post($webhook->url, $delivery->payload);

            WebhookDelivery::create([
                'organization_webhook_id' => $webhook->id,
                'event' => $delivery->event,
                'attempt' => $delivery->attempt + 1,
                'status_code' => $response->status(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'payload' => $delivery->payload,
                'response_body' => substr((string) $response->body(), 0, 4096),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if ($response->successful()) {
                $webhook->update(['last_delivered_at' => now()]);
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery replayed (HTTP :s).', ['s' => $response->status()])]);
        } catch (\Throwable $e) {
            WebhookDelivery::create([
                'organization_webhook_id' => $webhook->id,
                'event' => $delivery->event,
                'attempt' => $delivery->attempt + 1,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'payload' => $delivery->payload,
                'error' => substr($e->getMessage(), 0, 255),
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Replay failed: :m', ['m' => $e->getMessage()])]);
        }

        return back();
    }
}
