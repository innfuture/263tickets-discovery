<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Allow an automation flow to escalate / approve / reject refund
 * requests. Scope: `refunds.write`.
 *
 *   POST /api/v1/automations/orders/{reference}/refund-requests
 *      → create a new refund_request (server-issued, no buyer match)
 *   PATCH /api/v1/automations/refund-requests/{uuid}
 *      → update status (approved|rejected)
 *
 * The actual gateway-side refund is still the organizer's
 * PaymentRefund pipeline — this endpoint moves the workflow ticket
 * forward, not the money.
 */
class AutomationRefundController extends Controller
{
    public function store(Request $request, string $reference): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $validated = $request->validate([
            'reason_code' => ['required', 'in:duplicate_purchase,event_cancelled,date_change,not_attending,fraud,other'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = Order::query()
            ->where('organisation_id', $org->uuid)
            ->where('reference', $reference)
            ->first();
        if (! $order) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $entry = RefundRequest::create([
            'order_id' => $order->id,
            'organisation_id' => $org->uuid,
            'reason_code' => $validated['reason_code'],
            'notes' => $validated['notes'] ?? null,
            'contact_email' => $order->buyer_email,
            'status' => RefundRequest::STATUS_PENDING,
            'ip_address' => $request->ip(),
        ]);

        AuditLog::create([
            'organization_id' => $org->id,
            'actor_type' => 'api',
            'action' => 'refund.requested.via_automation',
            'resource_type' => RefundRequest::class,
            'resource_id' => (string) $entry->id,
            'after' => ['order_reference' => $order->reference],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => ['uuid' => $entry->uuid, 'status' => $entry->status],
        ], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $org = $request->attributes->get('automation_org');

        $entry = RefundRequest::query()
            ->where('uuid', $uuid)
            ->where('organisation_id', $org->uuid)
            ->first();
        if (! $entry) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = ['status' => $entry->status];

        $entry->forceFill([
            'status' => $validated['status'] === 'approved'
                ? RefundRequest::STATUS_APPROVED
                : RefundRequest::STATUS_REJECTED,
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_at' => now(),
        ])->save();

        AuditLog::create([
            'organization_id' => $org->id,
            'actor_type' => 'api',
            'action' => 'refund.'.$validated['status'].'.via_automation',
            'resource_type' => RefundRequest::class,
            'resource_id' => (string) $entry->id,
            'before' => $before,
            'after' => ['status' => $entry->status],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => ['uuid' => $entry->uuid, 'status' => $entry->status],
        ]);
    }
}
