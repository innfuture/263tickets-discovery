<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-bust the storefront discovery responses whenever an event
 * changes meaningfully. Coarse-grained — we tag the whole index +
 * featured cache — but the TTL is short anyway so this is just
 * insurance for "sold out" propagating without a 30s delay.
 */
class EventObserver
{
    public function saved(Event $event): void
    {
        $this->bust();
    }

    public function deleted(Event $event): void
    {
        $this->bust();
    }

    protected function bust(): void
    {
        try {
            Cache::tags(['storefront-events'])->flush();
        } catch (\Throwable) {
            // Tag-based busting requires Redis / memcached. On file/db
            // drivers it's a no-op — the TTL will catch it.
        }
    }
}
