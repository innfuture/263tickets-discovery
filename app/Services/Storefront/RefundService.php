<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Http\Request;

/**
 * Public buyer-side refund intake. Creates the workflow ticket; the
 * organizer dashboard owns the approve/reject flow + invoking the
 * gateway via the existing payments subsystem.
 *
 * Idempotent per (order, contact_email) — a buyer mashing submit twice
 * doesn't create duplicate review tickets.
 */
class RefundService
{
    public function __construct(protected RefundPolicyEvaluator $policy) {}

    public function submit(Order $order, array $input, Request $request): RefundRequest
    {
        $contactEmail = strtolower((string) ($input['contact_email'] ?? $order->buyer_email));

        $existing = RefundRequest::query()
            ->where('order_id', $order->id)
            ->where('contact_email', $contactEmail)
            ->whereIn('status', [
                RefundRequest::STATUS_PENDING,
                RefundRequest::STATUS_APPROVED,
            ])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Snapshot the policy verdict at submission time so the
        // organizer's reviewer sees what the buyer was promised when
        // they hit "request refund" — even if the policy changes later.
        $verdict = $this->policy->evaluate($order);

        $entry = RefundRequest::create([
            'order_id' => $order->id,
            'organisation_id' => $order->organisation_id,
            'reason_code' => (string) ($input['reason_code'] ?? 'other'),
            'notes' => $input['notes'] ?? null,
            'contact_email' => $contactEmail,
            'status' => RefundRequest::STATUS_PENDING,
            'ip_address' => $request->ip(),
        ]);

        // Store the policy snapshot in review_notes JSON-prefix so the
        // organizer's dashboard can render it without a schema change.
        $entry->forceFill([
            'review_notes' => json_encode([
                'policy_snapshot' => $verdict,
                'submitted_via' => 'public_storefront',
            ]),
        ])->save();

        AuditLog::create([
            'organization_id' => null, // org's PK lookup; logged via UUID below.
            'actor_type' => 'system',
            'action' => 'refund.requested',
            'resource_type' => RefundRequest::class,
            'resource_id' => (string) $entry->id,
            'after' => [
                'order_reference' => $order->reference,
                'organisation_uuid' => $order->organisation_id,
                'policy_verdict' => $verdict,
            ],
            'ip_address' => $request->ip(),
        ]);

        return $entry;
    }

    /** @return array{is_refundable: bool, percent: int, refundable_cents: int, reason: string} */
    public function preview(Order $order): array
    {
        return $this->policy->evaluate($order);
    }
}
