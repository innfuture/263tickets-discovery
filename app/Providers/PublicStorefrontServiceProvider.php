<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\EventInventoryChanged;
use App\Events\OrderPaid;
use App\Events\TicketScanned;
use App\Listeners\Automation\PublishEventInventoryChanged;
use App\Listeners\Automation\PublishOrderPaid;
use App\Listeners\Automation\PublishTicketScanned;
use App\Listeners\Storefront\BroadcastEventInventory;
use App\Listeners\Storefront\CreditReferrerOnOrderPaid;
use App\Listeners\Storefront\QueueOrderConfirmation;
use App\Models\Event as EventModel;
use App\Models\PaymentTransaction;
use App\Observers\EventObserver;
use App\Observers\PaymentTransactionObserver;
use App\Services\EventBus\Contracts\DomainBus;
use App\Services\EventBus\LaravelEventBus;
use App\Services\EventBus\NullBus;
use App\Services\EventBus\RedisStreamBus;
use App\Services\Storefront\Captcha\NullCaptchaProvider;
use App\Services\Storefront\Captcha\TurnstileCaptchaProvider;
use App\Services\Storefront\Contracts\CaptchaProvider;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Passes\ApplePassKitGenerator;
use App\Services\Storefront\Passes\GoogleWalletPassGenerator;
use App\Services\Storefront\PromoCodeValidator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires everything storefront-related into the container:
 *
 *   - DiscountResolver binding (default: PromoCodeValidator)
 *   - PaymentTransaction observer → storefront fulfilment
 *   - OrderPaid → QueueOrderConfirmation listener
 *   - Named rate limiters used by routes/public.php
 *
 * Like the rest of the SaaS, every choice is a bind: an integrator
 * can register their own DiscountResolver / fee rule / tax rule in
 * a downstream provider and swap behaviour without editing core.
 */
class PublicStorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DiscountResolver::class, PromoCodeValidator::class);

        // Captcha provider — null by default (dev/sandbox), Turnstile
        // when configured. Add additional providers by binding here
        // and switching on the env var.
        $this->app->singleton(CaptchaProvider::class, function () {
            $provider = (string) config('storefront.captcha.provider', 'null');

            if ($provider === 'turnstile') {
                $site = (string) config('storefront.captcha.turnstile.site_key');
                $secret = (string) config('storefront.captcha.turnstile.secret_key');
                if ($site === '' || $secret === '') {
                    return new NullCaptchaProvider;
                }

                return new TurnstileCaptchaProvider($secret, $site);
            }

            return new NullCaptchaProvider;
        });

        // Wallet pass generators — configured via container so the
        // WalletPassService can `make()` them without leaking config
        // into the service classes.
        $this->app->bind(ApplePassKitGenerator::class, fn () => new ApplePassKitGenerator(
            certPath: (string) config('storefront.wallet.apple.cert_path'),
            wwdrPath: (string) config('storefront.wallet.apple.wwdr_path'),
            passphrase: (string) config('storefront.wallet.apple.cert_passphrase', ''),
            teamId: (string) config('storefront.wallet.apple.team_id'),
            passTypeId: (string) config('storefront.wallet.apple.pass_type_id'),
            iconPath: (string) config('storefront.wallet.apple.icon_path'),
        ));
        $this->app->bind(GoogleWalletPassGenerator::class, fn () => new GoogleWalletPassGenerator(
            serviceAccountPath: (string) config('storefront.wallet.google.service_account_path'),
            issuerId: (string) config('storefront.wallet.google.issuer_id'),
            classId: (string) config('storefront.wallet.google.class_id'),
        ));

        // Domain event bus — Laravel events in-process by default,
        // Redis Streams when EVENTBUS_DRIVER=redis_streams.
        $this->app->singleton(DomainBus::class, function () {
            return match ((string) config('eventbus.driver', 'laravel')) {
                'redis_streams' => new RedisStreamBus(
                    connection: (string) config('eventbus.redis_streams.connection', 'default'),
                    maxLen: (int) config('eventbus.redis_streams.max_len', 100_000),
                ),
                'null' => new NullBus,
                default => new LaravelEventBus,
            };
        });
    }

    public function boot(): void
    {
        PaymentTransaction::observe(PaymentTransactionObserver::class);
        EventModel::observe(EventObserver::class);

        Event::listen(OrderPaid::class, QueueOrderConfirmation::class);
        Event::listen(OrderPaid::class, BroadcastEventInventory::class);

        // Automation fan-out — bridge each published domain event
        // into the OrganizationWebhook delivery pipeline.
        Event::listen(OrderPaid::class, PublishOrderPaid::class);
        Event::listen(TicketScanned::class, PublishTicketScanned::class);
        Event::listen(EventInventoryChanged::class, PublishEventInventoryChanged::class);

        // Referral rewards — credits the referrer's gift card balance
        // for any order that carried their code in metadata.
        Event::listen(OrderPaid::class, CreditReferrerOnOrderPaid::class);

        $this->configureRateLimiters();
    }

    /**
     * Per-IP limits for the public surface. Values come from
     * config('storefront.rate_limits.*') as "max,minutes" pairs so
     * env can dial them per-environment.
     */
    protected function configureRateLimiters(): void
    {
        foreach ([
            'storefront-discovery' => (string) config('storefront.rate_limits.discovery', '120,1'),
            'storefront-checkout' => (string) config('storefront.rate_limits.checkout_write', '30,1'),
            'storefront-lookup' => (string) config('storefront.rate_limits.order_lookup', '10,1'),
            'storefront-waitlist' => (string) config('storefront.rate_limits.waitlist', '6,1'),
        ] as $name => $spec) {
            [$max, $minutes] = array_pad(array_map('intval', explode(',', $spec)), 2, 1);
            RateLimiter::for($name, fn (Request $request) => Limit::perMinutes(
                max(1, $minutes),
                max(1, $max),
            )->by((string) ($request->ip() ?? 'anon')));
        }
    }
}
