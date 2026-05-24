<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Automation;

use App\Http\Controllers\Controller;
use App\Jobs\Automation\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Organizer-facing CRUD for outbound webhook subscriptions
 * (`organization_webhooks` rows). Also exposes:
 *
 *   POST /webhooks/{id}/test-fire   — dispatch a synthetic event
 *   POST /webhooks/{id}/rotate-key  — mint a fresh signing_secret
 */
class AutomationWebhookController extends Controller
{
    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => OrganizationWebhook::query()
                ->where('organization_id', $currentOrganization->id)
                ->orderByDesc('id')
                ->get([
                    'id', 'label', 'url', 'events', 'channel',
                    'retry_limit', 'is_active', 'last_delivered_at',
                ]),
        ]);
    }

    public function store(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:80'],
            'channel' => ['nullable', 'in:operations,automation,marketing'],
            'retry_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $hook = OrganizationWebhook::create([
            'organization_id' => $currentOrganization->id,
            'label' => $validated['label'],
            'url' => $validated['url'],
            'events' => array_values(array_unique($validated['events'])),
            'channel' => $validated['channel'] ?? 'automation',
            'retry_limit' => (int) ($validated['retry_limit'] ?? 5),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            // Plaintext signing secret — only the destination needs to
            // know it, and we keep it stored for re-display in the
            // dashboard (operators routinely forget to copy on create).
            'signing_secret' => Str::random(48),
        ]);

        return response()->json([
            'data' => [
                'id' => $hook->id,
                'label' => $hook->label,
                'url' => $hook->url,
                'events' => $hook->events,
                'signing_secret' => $hook->signing_secret,
            ],
        ], 201);
    }

    public function update(Organization $currentOrganization, OrganizationWebhook $webhook, Request $request): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:2000'],
            'events' => ['nullable', 'array', 'min:1'],
            'events.*' => ['string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'retry_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $webhook->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['data' => $webhook->fresh()]);
    }

    public function destroy(Organization $currentOrganization, OrganizationWebhook $webhook): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);
        $webhook->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Synthetic delivery so the operator can verify their n8n /
     * external endpoint is wired correctly without waiting for a
     * real domain event to fire.
     */
    public function testFire(Organization $currentOrganization, OrganizationWebhook $webhook): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);

        DeliverWebhookJob::dispatch(
            webhookId: (int) $webhook->id,
            eventType: 'webhook.test',
            payload: [
                'test' => true,
                'fired_at' => now()->toIso8601String(),
                'note' => 'Synthetic delivery from the dashboard test-fire button.',
            ],
        )->onQueue((string) config('automation.queue', 'automations'));

        return response()->json(['data' => ['queued' => true]]);
    }

    /**
     * Mint a new signing secret + invalidate the old. Returns the
     * fresh plaintext so the operator can update their downstream
     * verifier in the same UI flow.
     */
    public function rotateKey(Organization $currentOrganization, OrganizationWebhook $webhook, Request $request): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);

        $new = Str::random(48);
        $webhook->forceFill(['signing_secret' => $new])->save();

        AuditLog::create([
            'organization_id' => $currentOrganization->id,
            'user_id' => $request->user()?->id,
            'actor_type' => 'user',
            'action' => 'webhook.signing_key.rotated',
            'resource_type' => OrganizationWebhook::class,
            'resource_id' => (string) $webhook->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => [
            'signing_secret' => $new,
            'rotated_at' => now()->toIso8601String(),
        ]]);
    }
}
