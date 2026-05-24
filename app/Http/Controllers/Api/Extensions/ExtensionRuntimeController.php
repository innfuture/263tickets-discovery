<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Extensions;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ExtensionAuditLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Runtime API surface for installed extensions. Authenticated via
 * `Authorization: Bearer ext_…` (EnsureExtensionInstallation
 * middleware) with per-route permission scoping.
 *
 *   GET  /api/v1/extensions/me
 *   GET  /api/v1/extensions/events           (events.read)
 *   GET  /api/v1/extensions/orders           (orders.read)
 *   GET  /api/v1/extensions/orders/{ref}     (orders.read)
 *
 * Mirrors the automation API shape — extensions are essentially
 * "n8n built in" — same retry envelope, same permission model.
 */
class ExtensionRuntimeController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $install = $request->attributes->get('extension_installation');
        $org = $request->attributes->get('extension_organization');

        return response()->json(['data' => [
            'installation_uuid' => $install->uuid,
            'extension' => [
                'slug' => $install->extension->slug,
                'name' => $install->extension->name,
                'version' => $install->version->version,
            ],
            'organization' => [
                'uuid' => $org->uuid,
                'name' => $org->name,
            ],
            'granted_permissions' => $install->granted_permissions,
        ]]);
    }

    public function events(Request $request): JsonResponse
    {
        $install = $request->attributes->get('extension_installation');
        $org = $request->attributes->get('extension_organization');

        $rows = Event::query()
            ->where('organisation_id', $org->uuid)
            ->orderByDesc('starts_at')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get(['id', 'event_id', 'slug', 'name', 'status', 'starts_at', 'capacity', 'tickets_sold_count']);

        $this->audit($install, 'events.list', null, null, ['count' => $rows->count()]);

        return response()->json(['data' => $rows]);
    }

    public function orders(Request $request): JsonResponse
    {
        $install = $request->attributes->get('extension_installation');
        $org = $request->attributes->get('extension_organization');

        $rows = Order::query()
            ->where('organisation_id', $org->uuid)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('placed_at')
            ->limit(min(500, (int) $request->integer('limit', 100)))
            ->get(['id', 'uuid', 'reference', 'event_id', 'buyer_name', 'buyer_email', 'total_cents', 'currency', 'status', 'placed_at']);

        $this->audit($install, 'orders.list', null, null, ['count' => $rows->count()]);

        return response()->json(['data' => $rows]);
    }

    public function order(Request $request, string $reference): JsonResponse
    {
        $install = $request->attributes->get('extension_installation');
        $org = $request->attributes->get('extension_organization');

        $order = Order::query()
            ->where('organisation_id', $org->uuid)
            ->where('reference', $reference)
            ->with('items', 'event')
            ->first();

        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $this->audit($install, 'orders.read', Order::class, (string) $order->id);

        return response()->json(['data' => $order]);
    }

    protected function audit($install, string $action, ?string $resType = null, ?string $resId = null, array $context = []): void
    {
        ExtensionAuditLog::create([
            'extension_installation_id' => $install->id,
            'action' => $action,
            'resource_type' => $resType,
            'resource_id' => $resId,
            'context' => $context,
        ]);
    }
}
