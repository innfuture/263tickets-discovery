<?php

namespace App\Providers;

use App\Events\CapacityReleased;
use App\Events\TicketActivated;
use App\Events\TicketScanned;
use App\Events\TicketVoided;
use App\Listeners\Scanning\PushActivatedTicketToEdgeListener;
use App\Listeners\Scanning\PushVoidedTicketToEdgeListener;
use App\Listeners\Scanning\QueueScanWebhook;
use App\Listeners\Storefront\NotifyWaitlistOnRelease;
use App\Models\Organization;
use App\Services\Scanning\Contracts\BiometricProvider;
use App\Services\Scanning\Contracts\ScanHistory;
use App\Services\Scanning\EloquentScanHistory;
use App\Services\Scanning\StubBiometricProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
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

        // SMS provider — config-driven binding. NullSmsProvider is the
        // safe default for dev/CI; production flips to twilio (etc.)
        // via SMS_DRIVER env.
        $this->app->bind(\App\Services\Sms\Contracts\SmsProvider::class, function () {
            return match ((string) config('sms.driver', 'null')) {
                'twilio' => new \App\Services\Sms\TwilioSmsProvider(
                    accountSid: (string) config('sms.twilio.account_sid', ''),
                    authToken: (string) config('sms.twilio.auth_token', ''),
                    fromNumber: (string) config('sms.twilio.from_number', ''),
                    timeoutSeconds: (int) config('sms.twilio.timeout_seconds', 10),
                ),
                default => new \App\Services\Sms\NullSmsProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerEventListeners();

        // `{current_organization}` in the URL is a slug — resolve it
        // to an Organization model so controllers can type-hint
        // `Organization $currentOrganization` and skip a lookup.
        // EnsureOrganizationMembership still runs and authorises.
        Route::bind('current_organization', function (string $slug) {
            return Organization::query()->where('slug', $slug)->firstOrFail();
        });
    }

    /**
     * Domain event → listener bindings. Skinny set today; co-located
     * here while it's small. Once the listener count climbs, split out
     * a dedicated EventServiceProvider.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(TicketScanned::class, QueueScanWebhook::class);
        Event::listen(TicketVoided::class, PushVoidedTicketToEdgeListener::class);
        Event::listen(TicketActivated::class, PushActivatedTicketToEdgeListener::class);
        Event::listen(CapacityReleased::class, NotifyWaitlistOnRelease::class);
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
