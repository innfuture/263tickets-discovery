<?php

declare(strict_types=1);

namespace App\Listeners\Scanning;

use App\Events\TicketScanned;
use App\Jobs\Scanning\DeliverScanWebhookJob;

/**
 * Listens for TicketScanned and queues a webhook delivery if the
 * scanner profile has a webhook_url configured. Thin by design —
 * the actual HTTP + retry logic lives in the job.
 */
class QueueScanWebhook
{
    public function handle(TicketScanned $event): void
    {
        $scan = $event->scanEvent;
        $profile = $scan->profile;

        if ($profile === null || empty($profile->webhook_url)) {
            return;
        }

        DeliverScanWebhookJob::dispatch($scan->id);
    }
}
