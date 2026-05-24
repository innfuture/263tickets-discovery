<?php

declare(strict_types=1);

namespace App\Services\Buyers;

use App\Models\Buyer;
use App\Models\BuyerNotification;

/**
 * Writes BuyerNotification rows. Domain listeners (OrderPaid,
 * EventChanged, TransferReceived, etc.) call `record()` so the
 * buyer's bell icon updates without each listener re-implementing
 * the persistence detail.
 */
class BuyerNotificationService
{
    public function record(
        Buyer $buyer,
        string $type,
        string $title,
        ?string $body = null,
        ?array $data = null,
        ?string $actionUrl = null,
    ): BuyerNotification {
        return BuyerNotification::create([
            'buyer_id' => $buyer->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);
    }

    public function markRead(BuyerNotification $notification): BuyerNotification
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    public function markAllRead(Buyer $buyer): int
    {
        return (int) BuyerNotification::query()
            ->where('buyer_id', $buyer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
