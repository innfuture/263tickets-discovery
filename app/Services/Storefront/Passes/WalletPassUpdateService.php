<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\WalletPassUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;

/**
 * Queues live wallet-pass updates (Apple PassKit + Google Wallet) for
 * one ticket or an entire event. Updates land in `wallet_pass_updates`
 * as queued rows; the DispatchPendingWalletUpdatesJob fans them out
 * to the upstream APIs in batches.
 *
 * Outbox + retry instead of inline HTTP for the same reasons our
 * automation webhooks use the same pattern: vendor APIs degrade, we
 * mustn't lose updates if SES / Apple takes a 5xx.
 */
class WalletPassUpdateService
{
    public function queueDoorChange(Event $event, string $newDoor, ?string $oldDoor = null): int
    {
        $payload = [
            'old_door' => $oldDoor,
            'new_door' => $newDoor,
            'human_message' => "Door change: please use {$newDoor}.",
        ];

        return $this->queueForEvent($event, WalletPassUpdate::TYPE_DOOR_CHANGE, $payload);
    }

    public function queueTimeShift(Event $event, CarbonImmutable $newStart, ?CarbonImmutable $oldStart = null): int
    {
        $payload = [
            'old_start' => $oldStart?->toIso8601String(),
            'new_start' => $newStart->toIso8601String(),
            'human_message' => 'Event start time updated. Check the latest pass for details.',
        ];

        return $this->queueForEvent($event, WalletPassUpdate::TYPE_TIME_SHIFT, $payload);
    }

    public function queueCancellation(Event $event, string $reason): int
    {
        return $this->queueForEvent($event, WalletPassUpdate::TYPE_CANCELLATION, [
            'reason' => $reason,
            'human_message' => 'This event has been cancelled. See the pass for refund instructions.',
        ]);
    }

    public function queueVoidedForTicket(OfflineTicket $ticket, string $reason): WalletPassUpdate
    {
        return WalletPassUpdate::create([
            'offline_ticket_id' => $ticket->id,
            'ticket_uuid' => (string) $ticket->uuid,
            'update_type' => WalletPassUpdate::TYPE_VOIDED,
            'payload' => ['reason' => $reason],
            'dispatch_status' => WalletPassUpdate::STATUS_QUEUED,
            'queued_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function queueForEvent(Event $event, string $type, array $payload): int
    {
        $now = CarbonImmutable::now();
        $count = 0;

        OfflineTicket::query()
            ->where('event_id', $event->id)
            ->where('is_voided', false)
            ->select(['id', 'uuid'])
            ->orderBy('id')
            ->chunk(500, function ($tickets) use ($type, $payload, $now, &$count): void {
                $rows = [];
                foreach ($tickets as $t) {
                    $rows[] = [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'offline_ticket_id' => $t->id,
                        'ticket_uuid' => (string) $t->uuid,
                        'update_type' => $type,
                        'payload' => json_encode($payload),
                        'dispatch_status' => WalletPassUpdate::STATUS_QUEUED,
                        'queued_at' => $now,
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                WalletPassUpdate::insert($rows);
                $count += count($rows);
            });

        return $count;
    }
}
