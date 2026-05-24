<?php

declare(strict_types=1);

namespace App\Services\Buyers;

use App\Models\Buyer;
use App\Models\OfflineTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RefundRequest;
use App\Models\TicketCategory;
use App\Services\Storefront\RefundPolicyEvaluator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Buyer-initiated ticket actions — upgrade, downgrade, void.
 * Transfer is a separate flow (TicketTransferService) since it
 * involves a counterparty.
 *
 *   upgrade()   change ticket_category to a higher-priced tier and
 *               return the price difference; caller charges the diff
 *               via the existing payment flow.
 *
 *   downgrade() change to a lower tier; emits a refund request
 *               for the difference scoped to the per-event refund
 *               policy.
 *
 *   void()      cancel the ticket; emits a refund request for the
 *               full ticket price (refund-policy gated).
 *
 * Voids and downgrades go through the existing organizer-reviewed
 * RefundRequest pipeline — we don't auto-refund on the buyer's word.
 */
class SelfServiceTicketService
{
    public function __construct(protected RefundPolicyEvaluator $policy) {}

    /**
     * Returns [old_price_cents, new_price_cents, price_diff_cents].
     * Pure-data — caller is responsible for charging the diff via
     * the payment manager.
     */
    public function upgrade(Buyer $buyer, OrderItem $item, TicketCategory $newTier): array
    {
        $this->assertOwned($item, $buyer);

        $oldPrice = (int) $item->unit_price_cents;
        $newPrice = (int) round(((float) $newTier->base_price) * 100);
        if ($newPrice <= $oldPrice) {
            throw new RuntimeException('Target tier is not an upgrade.');
        }

        return DB::transaction(function () use ($item, $newTier, $oldPrice, $newPrice) {
            $item->forceFill([
                'ticket_category_id' => $newTier->id,
                'ticket_type' => (string) $newTier->name,
                'unit_price_cents' => $newPrice,
            ])->save();

            // Update the underlying OfflineTicket's tier so the
            // scanner pipeline sees the new admission_type / pass_type.
            if ($item->offline_ticket_id) {
                OfflineTicket::query()->where('id', $item->offline_ticket_id)->update([
                    'ticket_category_id' => $newTier->id,
                    'admission_type' => $newTier->admission_type,
                    'pass_type' => $newTier->pass_type,
                ]);
            }

            return [
                'old_price_cents' => $oldPrice,
                'new_price_cents' => $newPrice,
                'price_diff_cents' => $newPrice - $oldPrice,
            ];
        });
    }

    /**
     * Downgrade — apply the new tier + create a RefundRequest for
     * the difference, subject to the event's refund policy.
     */
    public function downgrade(Buyer $buyer, OrderItem $item, TicketCategory $newTier): RefundRequest
    {
        $this->assertOwned($item, $buyer);

        $oldPrice = (int) $item->unit_price_cents;
        $newPrice = (int) round(((float) $newTier->base_price) * 100);
        if ($newPrice >= $oldPrice) {
            throw new RuntimeException('Target tier is not a downgrade.');
        }

        $verdict = $this->policy->evaluate($item->order, $item->order->event);
        if (! $verdict['is_refundable']) {
            throw new RuntimeException('Refund policy does not allow a downgrade now.');
        }

        return DB::transaction(function () use ($item, $newTier, $oldPrice, $newPrice, $verdict) {
            $diff = $oldPrice - $newPrice;

            $item->forceFill([
                'ticket_category_id' => $newTier->id,
                'ticket_type' => (string) $newTier->name,
                'unit_price_cents' => $newPrice,
            ])->save();

            return RefundRequest::create([
                'order_id' => $item->order_id,
                'organisation_id' => $item->order->organisation_id,
                'reason_code' => 'date_change',
                'notes' => "Downgrade refund: tier changed; diff {$diff} cents.",
                'contact_email' => $item->order->buyer_email,
                'status' => RefundRequest::STATUS_PENDING,
                'review_notes' => json_encode([
                    'policy_snapshot' => $verdict,
                    'kind' => 'downgrade_diff',
                    'diff_cents' => $diff,
                ]),
            ]);
        });
    }

    /**
     * Void a single line — voids the underlying ticket so the gate
     * denies, creates a refund request for organizer review.
     */
    public function void(Buyer $buyer, OrderItem $item, string $reason = 'not_attending'): RefundRequest
    {
        $this->assertOwned($item, $buyer);

        $verdict = $this->policy->evaluate($item->order, $item->order->event);

        return DB::transaction(function () use ($item, $reason, $verdict) {
            if ($item->offline_ticket_id) {
                OfflineTicket::query()->where('id', $item->offline_ticket_id)->update([
                    'is_voided' => true,
                    'voided_at' => now(),
                    'void_reason' => 'buyer_self_void',
                ]);

                $ticket = OfflineTicket::query()->find($item->offline_ticket_id);
                if ($ticket) {
                    \App\Events\TicketVoided::dispatch($ticket, 'buyer_self_void');
                }
            }

            return RefundRequest::create([
                'order_id' => $item->order_id,
                'organisation_id' => $item->order->organisation_id,
                'reason_code' => $reason,
                'notes' => 'Buyer self-voided this ticket.',
                'contact_email' => $item->order->buyer_email,
                'status' => RefundRequest::STATUS_PENDING,
                'review_notes' => json_encode([
                    'policy_snapshot' => $verdict,
                    'kind' => 'void_single_ticket',
                    'order_item_id' => $item->id,
                ]),
            ]);
        });
    }

    protected function assertOwned(OrderItem $item, Buyer $buyer): void
    {
        $item->loadMissing('order');
        if (! $item->order || $item->order->buyer_id !== $buyer->id) {
            throw new RuntimeException('Ticket not owned by this buyer.');
        }
    }
}
