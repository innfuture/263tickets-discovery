<?php

namespace App\Providers;

use App\Events\TicketScanned;
use App\Listeners\Scanning\QueueScanWebhook;
use App\Services\Scanning\Contracts\BiometricProvider;
use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\EloquentScanHistory;
use App\Services\Scanning\StubBiometricProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scanner fraud pipeline — bound to interfaces so tests / SaaS
        // integrators can swap implementations without editing rules.
        $this->app->bind(ScanHistory::class, EloquentScanHistory::class);
        $this->app->bind(BiometricProvider::class, function () {
            $configured = (string) config('scanning.biometric_provider', StubBiometricProvider::class);

            return $this->app->make($configured);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerEventListeners();
    }

    /**
     * Domain event → listener bindings. Skinny set today; co-located
     * here while it's small. Once the listener count climbs, split out
     * a dedicated EventServiceProvider.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(TicketScanned::class, QueueScanWebhook::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
