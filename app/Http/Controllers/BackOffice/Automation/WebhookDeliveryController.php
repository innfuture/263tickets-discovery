<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Automation;

use App\Http\Controllers\Controller;
use App\Jobs\Automation\DeliverWebhookJob;
use App\Models\Organization;
use App\Models\OrganizationWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Delivery log viewer + replay tool. Each row in
 * `organization_webhook_deliveries` carries the original payload —
 * replay just re-dispatches a fresh DeliverWebhookJob with that
 * payload (gets a brand-new attempt counter, new delivery row).
 */
class WebhookDeliveryController extends Controller
{
    public function index(Organization $currentOrganization, OrganizationWebhook $webhook, Request $request): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);

        $rows = DB::table('organization_webhook_deliveries')
            ->where('organization_webhook_id', $webhook->id)
            ->when($request->query('status_code'), fn ($q, $code) => $q->where('status_code', $code))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function replay(Organization $currentOrganization, OrganizationWebhook $webhook, int $deliveryId): JsonResponse
    {
        abort_if($webhook->organization_id !== $currentOrganization->id, 404);

        $row = DB::table('organization_webhook_deliveries')
            ->where('id', $deliveryId)
            ->where('organization_webhook_id', $webhook->id)
            ->first();
        abort_if(! $row, 404);

        $payload = json_decode((string) $row->payload, true);
        $data = is_array($payload) ? ($payload['data'] ?? []) : [];

        DeliverWebhookJob::dispatch(
            webhookId: (int) $webhook->id,
            eventType: (string) $row->event,
            payload: is_array($data) ? $data : [],
        )->onQueue((string) config('automation.queue', 'automations'));

        return response()->json(['data' => ['queued' => true, 'original_delivery_id' => $deliveryId]]);
    }
}
