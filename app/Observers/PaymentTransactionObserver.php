<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\CheckoutSessionStatus;
use App\Enums\PaymentStatus;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Storefront\CheckoutSessionManager;
use App\Services\Storefront\OrderFulfillment;

/**
 * Bridges the existing payment pipeline → storefront fulfilment.
 *
 * When a PaymentTransaction transitions to a successful state and was
 * created by the checkout flow (i.e. its metadata carries a
 * `checkout_session_uuid`), we hand off to OrderFulfillment which
 * issues tickets + emits OrderPaid.
 *
 * Why an observer not a listener: the existing ProcessWebhookEventJob
 * calls `$transaction->markStatus(…)` which fires Eloquent's `updated`
 * hook. Hooking here means we react to ALL paths that mark a
 * transaction settled (webhook, manual reconcile, dev "mark paid"
 * button) without each one having to remember to fire an event.
 *
 * Idempotency lives in OrderFulfillment::fulfill — repeated invocations
 * for the same session return the same order.
 */
class PaymentTransactionObserver
{
    public function updated(PaymentTransaction $transaction): void
    {
        if (! $transaction->wasChanged('status')) {
            return;
        }

        $status = PaymentStatus::tryFrom((string) $transaction->status);
        if (! $status) {
            return;
        }

        $uuid = (string) (($transaction->metadata['checkout_session_uuid'] ?? '') ?: '');
        if ($uuid === '') {
            return;
        }

        $session = CheckoutSession::query()->where('uuid', $uuid)->first();
        if (! $session) {
            return;
        }

        if ($status->isSuccessful() && ! $session->order_id) {
            /** @var OrderFulfillment $fulfilment */
            $fulfilment = app(OrderFulfillment::class);
            $order = $fulfilment->fulfill($session, $transaction->reference, $transaction->gateway);

            /** @var CheckoutSessionManager $sessions */
            $sessions = app(CheckoutSessionManager::class);
            $sessions->complete($session->refresh());

            // Repoint the polymorphic payable from the session to the
            // permanent Order so reconciliation queries follow a
            // single, durable owner.
            $transaction->forceFill([
                'payable_type' => Order::class,
                'payable_id' => $order->id,
            ])->saveQuietly();

            return;
        }

        // Failure / cancellation — release the holds so the buyer can
        // retry from a clean session and the inventory frees up.
        if (in_array($status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::REVERSED], true)) {
            if ($session->status === CheckoutSessionStatus::Paying) {
                /** @var CheckoutSessionManager $sessions */
                $sessions = app(CheckoutSessionManager::class);
                $sessions->cancel($session, reason: 'payment_'.$status->value);
            }
        }
    }
}
