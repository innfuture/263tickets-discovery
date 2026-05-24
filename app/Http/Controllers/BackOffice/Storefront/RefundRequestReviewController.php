<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\RefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundRequestReviewController extends Controller
{
    public function index(Organization $currentOrganization, Request $request): JsonResponse
    {
        return response()->json([
            'data' => RefundRequest::query()
                ->where('organisation_id', $currentOrganization->uuid)
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->with('order:id,reference,buyer_email,total_cents,currency,event_id')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(),
        ]);
    }

    public function approve(Organization $currentOrganization, RefundRequest $refundRequest, Request $request): JsonResponse
    {
        abort_if($refundRequest->organisation_id !== $currentOrganization->uuid, 404);
        $this->transition($refundRequest, RefundRequest::STATUS_APPROVED, $request);

        return response()->json(['data' => ['uuid' => $refundRequest->uuid, 'status' => $refundRequest->status]]);
    }

    public function reject(Organization $currentOrganization, RefundRequest $refundRequest, Request $request): JsonResponse
    {
        abort_if($refundRequest->organisation_id !== $currentOrganization->uuid, 404);
        $this->transition($refundRequest, RefundRequest::STATUS_REJECTED, $request);

        return response()->json(['data' => ['uuid' => $refundRequest->uuid, 'status' => $refundRequest->status]]);
    }

    protected function transition(RefundRequest $entry, string $status, Request $request): void
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = ['status' => $entry->status];

        $entry->forceFill([
            'status' => $status,
            'review_notes' => $validated['review_notes'] ?? $entry->review_notes,
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ])->save();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'actor_type' => 'user',
            'action' => 'refund_request.'.$status,
            'resource_type' => RefundRequest::class,
            'resource_id' => (string) $entry->id,
            'before' => $before,
            'after' => ['status' => $entry->status],
            'ip_address' => $request->ip(),
        ]);
    }
}
