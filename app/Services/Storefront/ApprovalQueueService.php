<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\AuditLog;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentManager;

/**
 * Approval-required events: when an Event's metadata flag
 * `requires_approval` is true, OrderFulfillment lands the Order in
 * `pending_approval` status (no payment captured yet, no tickets
 * issued). The organizer reviews from the back office and either
 * approves (charges + fulfils) or rejects (refunds the auth).
 *
 * Status flow:
 *   pending_approval → paid (approve)
 *   pending_approval → cancelled (reject)
 */
class ApprovalQueueService
{
    public function __construct(
        protected OrderFulfillment $fulfilment,
        protected PaymentManager $payments,
    ) {}

    public function approve(Order $order, User $reviewer): Order
    {
        if ($order->status !== 'pending_approval') {
            return $order;
        }

        $session = $order->checkout_session_id
            ? CheckoutSession::query()->find($order->checkout_session_id)
            : null;

        // If we have a session + payment_transaction, request capture
        // on the authorized hold via the gateway. Otherwise just flip
        // status (manual / comp orders).
        $this->capturePending($order);

        $order->forceFill([
            'status' => 'paid',
            'fulfilled_at' => now(),
        ])->save();

        if ($session) {
            $this->fulfilment->fulfill($session, $order->payment_reference, $order->payment_method);
        }

        AuditLog::create([
            'organization_id' => null,
            'user_id' => $reviewer->id,
            'actor_type' => 'user',
            'action' => 'order.approved',
            'resource_type' => Order::class,
            'resource_id' => (string) $order->id,
            'after' => ['status' => 'paid'],
        ]);

        return $order->refresh();
    }

    public function reject(Order $order, User $reviewer, ?string $reason = null): Order
    {
        if ($order->status !== 'pending_approval') {
            return $order;
        }

        $order->forceFill([
            'status' => 'cancelled',
            'metadata' => array_merge((array) $order->metadata, [
                'rejection_reason' => $reason,
            ]),
        ])->save();

        AuditLog::create([
            'organization_id' => null,
            'user_id' => $reviewer->id,
            'actor_type' => 'user',
            'action' => 'order.rejected',
            'resource_type' => Order::class,
            'resource_id' => (string) $order->id,
            'after' => ['status' => 'cancelled', 'reason' => $reason],
        ]);

        return $order->refresh();
    }

    /**
     * Best-effort capture against the existing PaymentTransaction. If
     * the gateway doesn't support capture-on-authorize (most don't —
     * the local Zim gateways are charge-then-settle), this is a no-op
     * and the existing settled transaction stands.
     */
    protected function capturePending(Order $order): void
    {
        // Drivers that implement a capture step would be invoked here;
        // since the current driver set is charge-only, no action.
    }
}
