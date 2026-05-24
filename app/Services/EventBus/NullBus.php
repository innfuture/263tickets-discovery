<?php

declare(strict_types=1);

namespace App\Services\EventBus;

use App\Services\EventBus\Contracts\DomainBus;

/**
 * No-op bus. Bound when EVENTBUS_DRIVER=null so tests + dev runs
 * have a guaranteed-quiet bus without disabling in-process listeners.
 */
class NullBus implements DomainBus
{
    public function publish(string $eventType, string $organisationUuid, array $payload): void
    {
        // Intentionally empty.
    }
}
