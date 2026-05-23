<?php

declare(strict_types=1);

namespace App\Jobs\Payments\Sandbox;

use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued delivery of one outbox event. Dispatched by the scheduled
 * worker (see SandboxFlushWebhooksCommand). Test code typically goes
 * through `WebhookDispatcher::flushDue()` directly instead of the
 * queue.
 */
class DispatchSandboxWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 14;

    public function __construct(public int $webhookEventId) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $event = SandboxWebhookOutbox::find($this->webhookEventId);
        if ($event === null || $event->status === 'delivered' || $event->status === 'dropped') {
            return;
        }

        $dispatcher->deliver($event);
    }
}
