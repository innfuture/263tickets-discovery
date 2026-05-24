<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Mail\TicketTransferOfferedMail;
use App\Models\AuditLog;
use App\Models\OfflineTicket;
use App\Models\OrderItem;
use App\Models\TicketTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Buyer-to-buyer ticket handoff. Lifecycle:
 *
 *   offer():    original buyer creates a pending transfer; mailable
 *               sends `claim_token` to the recipient.
 *   claim():    recipient opens the link → marks claimed_at.
 *   accept():   recipient confirms acceptance → we issue a new
 *               OfflineTicket UUID (so the old QR can be physically
 *               invalidated), void the original, link the new
 *               OrderItem to the recipient's email.
 *   decline()/revoke()/expire(): terminal failures; original ticket
 *               stays valid.
 *
 * Anti-scalper note: at offer-time we capture an optional
 * `sale_price_cents` for receipts, but money handling is out of
 * scope here — payment between buyers happens off-platform.
 */
class TicketTransferService
{
    public function offer(
        OrderItem $sourceItem,
        string $toEmail,
        ?string $toName = null,
        ?string $message = null,
        ?int $salePriceCents = null,
        ?string $currency = null,
        int $expiresInHours = 168,
    ): TicketTransfer {
        if ($sourceItem->offline_ticket_id === null) {
            throw new RuntimeException('Source item has no underlying ticket.');
        }
        if (! $sourceItem->order || ! $sourceItem->order->buyer_email) {
            throw new RuntimeException('Source order is missing buyer email.');
        }

        // Block double-listing: only one outstanding offer per ticket.
        $existing = TicketTransfer::query()
            ->where('offline_ticket_id', $sourceItem->offline_ticket_id)
            ->whereIn('status', [
                TicketTransfer::STATUS_OFFERED,
                TicketTransfer::STATUS_CLAIMED,
            ])
            ->exists();

        if ($existing) {
            throw new RuntimeException('This ticket already has an open transfer.');
        }

        $transfer = TicketTransfer::create([
            'offline_ticket_id' => $sourceItem->offline_ticket_id,
            'source_order_item_id' => $sourceItem->id,
            'from_email' => strtolower((string) $sourceItem->order->buyer_email),
            'to_email' => strtolower($toEmail),
            'to_name' => $toName,
            'message' => $message,
            'status' => TicketTransfer::STATUS_OFFERED,
            'sale_price_cents' => $salePriceCents,
            'currency' => $salePriceCents ? ($currency ?? $sourceItem->currency) : null,
            'expires_at' => Carbon::now()->addHours($expiresInHours),
        ]);

        // Best-effort delivery — failure should never block the offer
        // from being recorded. The organizer can re-send from the
        // dashboard if delivery bounces.
        try {
            $transfer->load('offlineTicket.event');
            Mail::to($transfer->to_email)->send(new TicketTransferOfferedMail($transfer));
        } catch (\Throwable $e) {
            Log::warning('ticket_transfer.mail_failed', [
                'transfer_uuid' => $transfer->uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return $transfer;
    }

    public function claim(TicketTransfer $transfer): TicketTransfer
    {
        $this->assertActive($transfer);
        $transfer->forceFill([
            'status' => TicketTransfer::STATUS_CLAIMED,
            'claimed_at' => Carbon::now(),
        ])->save();

        return $transfer;
    }

    public function accept(TicketTransfer $transfer): TicketTransfer
    {
        $this->assertActive($transfer);

        return DB::transaction(function () use ($transfer) {
            $source = OrderItem::query()->findOrFail($transfer->source_order_item_id);
            $ticket = OfflineTicket::query()->findOrFail($transfer->offline_ticket_id);

            // Mint a fresh QR for the new holder — the old QR is voided
            // so any existing screenshots stop working.
            $newUuid = (string) Str::uuid();
            $secret = (string) config('app.key');
            $hmac = substr(hash_hmac('sha256', $newUuid, $secret), 0, 24);
            $newPayload = $newUuid.'.'.$hmac;

            $newTicket = OfflineTicket::create([
                'uuid' => $newUuid,
                'ticket_category_id' => $ticket->ticket_category_id,
                'event_id' => $ticket->event_id,
                'organisation_id' => $ticket->organisation_id,
                'ticket_number' => strtoupper(Str::random(10)),
                'qr_payload' => $newPayload,
                'pass_type' => $ticket->pass_type,
                'admission_type' => $ticket->admission_type,
                'serial' => Str::random(16),
                'scan_count' => 0,
                'log_count' => 0,
                'is_voided' => false,
            ]);

            $ticket->forceFill([
                'is_voided' => true,
                'voided_at' => Carbon::now(),
                'void_reason' => 'transferred:'.$transfer->uuid,
            ])->save();

            // Clone the OrderItem so the recipient has a row tied to
            // their email; original keeps history for the sender.
            $newItem = $source->replicate([])->fill([
                'offline_ticket_id' => $newTicket->id,
                'qr_payload' => $newTicket->qr_payload,
                'attendee_name' => $transfer->to_name ?? $source->attendee_name,
                'attendee_email' => $transfer->to_email,
                'attendee_phone' => null,
            ]);
            $newItem->save();

            $transfer->forceFill([
                'status' => TicketTransfer::STATUS_COMPLETED,
                'completed_at' => Carbon::now(),
                'new_order_item_id' => $newItem->id,
            ])->save();

            AuditLog::create([
                'organization_id' => null,
                'actor_type' => 'system',
                'action' => 'ticket.transferred',
                'resource_type' => TicketTransfer::class,
                'resource_id' => (string) $transfer->id,
                'after' => [
                    'from_email' => $transfer->from_email,
                    'to_email' => $transfer->to_email,
                    'old_ticket_uuid' => $ticket->uuid,
                    'new_ticket_uuid' => $newTicket->uuid,
                ],
            ]);

            return $transfer->refresh();
        });
    }

    public function decline(TicketTransfer $transfer): TicketTransfer
    {
        $transfer->forceFill(['status' => TicketTransfer::STATUS_DECLINED])->save();

        return $transfer;
    }

    public function revoke(TicketTransfer $transfer): TicketTransfer
    {
        $transfer->forceFill(['status' => TicketTransfer::STATUS_REVOKED])->save();

        return $transfer;
    }

    protected function assertActive(TicketTransfer $transfer): void
    {
        if (! in_array($transfer->status, [
            TicketTransfer::STATUS_OFFERED,
            TicketTransfer::STATUS_CLAIMED,
        ], true)) {
            throw new RuntimeException('Transfer is no longer active.');
        }
        if ($transfer->expires_at && $transfer->expires_at->isPast()) {
            throw new RuntimeException('Transfer offer has expired.');
        }
    }
}
