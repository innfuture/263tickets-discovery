<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\DeveloperAccount;
use App\Models\Event;
use App\Models\OfflineTicketBatch;
use App\Models\Order;
use App\Models\ScannerProfile;
use App\Policies\DeveloperAccountPolicy;
use App\Policies\EventPolicy;
use App\Policies\OfflineTicketBatchPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ScannerProfilePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

/**
 * Maps model → policy so `$user->can('update', $event)` and similar
 * checks across the codebase resolve to the right policy class. The
 * existing `permission:<key>` route middleware remains; policies are
 * an additional layer for direct model access.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Event::class => EventPolicy::class,
        Order::class => OrderPolicy::class,
        DeveloperAccount::class => DeveloperAccountPolicy::class,
        ScannerProfile::class => ScannerProfilePolicy::class,
        OfflineTicketBatch::class => OfflineTicketBatchPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
