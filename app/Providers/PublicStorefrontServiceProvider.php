<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\EventInventoryChanged;
use App\Events\OrderPaid;
use App\Events\TicketScanned;
use App\Listeners\Automation\PublishEventInventoryChanged;
use App\Listeners\Automation\PublishOrderPaid;
use App\Listeners\Automation\PublishTicketScanned;
use App\Listeners\Buyer\NotifyBuyerOnOrderPaid;
use App\Listeners\Storefront\BroadcastEventInventory;
use App\Listeners\Storefront\CreditReferrerOnOrderPaid;
use App\Listeners\Storefront\QueueOrderConfirmation;
use App\Models\Event as EventModel;
use App\Models\OfflineTicket;
use App\Models\PaymentTransaction;
use App\Observers\EventObserver;
use App\Observers\OfflineTicketVoidObserver;
use App\Observers\PaymentTransactionObserver;
use App\Services\Ai\ClaudeAssistant;
use App\Services\Ai\Contracts\AiAssistant;
use App\Services\Ai\Contracts\EmbeddingClient;
use App\Services\Ai\OpenAiEmbeddingClient;
use App\Services\Ai\StubAiAssistant;
use App\Services\Ai\StubEmbeddingClient;
use App\Services\Cloudflare\CloudflareKv;
use App\Services\EventBus\Contracts\DomainBus;
use App\Services\EventBus\LaravelEventBus;
use App\Services\EventBus\NatsBus;
use App\Services\EventBus\NullBus;
use App\Services\EventBus\RedisStreamBus;
use App\Services\Storefront\Captcha\NullCaptchaProvider;
use App\Services\Storefront\Captcha\TurnstileCaptchaProvider;
use App\Services\Storefront\Contracts\CaptchaProvider;
use App\Services\Storefront\Contracts\DiscountResolver;
use App\Services\Storefront\Passes\ApplePassKitGenerator;
use App\Services\Storefront\Passes\GoogleWalletPassGenerator;
use App\Services\Storefront\PromoCodeValidator;
use App\Services\Scanning\Nfc\AppleVasProvider;
use App\Services\Scanning\Nfc\GoogleSmartTapProvider;
use App\Services\Storefront\Search\Contracts\RecommendationStrategy;
use App\Services\Storefront\Search\Contracts\SearchProvider;
use App\Services\Storefront\Search\DatabaseSearchProvider;
use App\Services\Storefront\Search\MeilisearchSearchProvider;
use App\Services\Storefront\Search\RelatedEventsRecommendationStrategy;
use App\Services\Telemetry\Contracts\Telemetry;
use App\Services\Telemetry\LogTelemetry;
use App\Services\Telemetry\NullTelemetry;
use App\Services\Telemetry\SentryTelemetry;
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
        // Redis Streams / NATS when EVENTBUS_DRIVER points there.
        $this->app->singleton(DomainBus::class, function () {
            return match ((string) config('eventbus.driver', 'laravel')) {
                'redis_streams' => new RedisStreamBus(
                    connection: (string) config('eventbus.redis_streams.connection', 'default'),
                    maxLen: (int) config('eventbus.redis_streams.max_len', 100_000),
                ),
                'nats' => new NatsBus(
                    host: (string) config('eventbus.nats.host', '127.0.0.1'),
                    port: (int) config('eventbus.nats.port', 4222),
                    token: config('eventbus.nats.token'),
                    streamPrefix: (string) config('eventbus.nats.stream_prefix', 'domain'),
                ),
                'null' => new NullBus,
                default => new LaravelEventBus,
            };
        });

        // Search provider — DB-backed LIKE by default; Meilisearch
        // once an index is configured. Wraps Meili in a fallback to
        // the DB impl so a search outage doesn't break discovery.
        $this->app->singleton(SearchProvider::class, function ($app) {
            $driver = (string) config('storefront.search.driver', 'database');
            $db = $app->make(DatabaseSearchProvider::class);

            if ($driver === 'meilisearch') {
                return new MeilisearchSearchProvider(
                    url: (string) config('storefront.search.meilisearch.url'),
                    masterKey: config('storefront.search.meilisearch.master_key'),
                    indexName: (string) config('storefront.search.meilisearch.index_name', 'events'),
                    fallback: $db,
                );
            }

            return $db;
        });

        // Recommendations — default rules-based; swap in a downstream
        // provider for Algolia Recommend / collaborative filtering.
        $this->app->bind(RecommendationStrategy::class, RelatedEventsRecommendationStrategy::class);

        // Telemetry — log-structured by default, Sentry when the
        // package is present and the driver flips.
        $this->app->singleton(Telemetry::class, function () {
            return match ((string) config('telemetry.driver', 'log')) {
                'sentry' => new SentryTelemetry,
                'null' => new NullTelemetry,
                default => new LogTelemetry((string) config('telemetry.log.channel', 'stack')),
            };
        });

        // Cloudflare KV client — used by OfflineTicketVoidObserver to
        // push voided UUIDs to the edge worker. No-ops gracefully when
        // CF isn't configured.
        $this->app->singleton(CloudflareKv::class, fn () => new CloudflareKv(
            accountId: config('storefront.cloudflare.account_id'),
            apiToken: config('storefront.cloudflare.api_token'),
        ));

        // AI: embedding client + assistant. Stubs by default so dev /
        // sandbox runs work without API keys.
        $this->app->singleton(EmbeddingClient::class, function () {
            return match ((string) config('ai.embeddings.driver', 'stub')) {
                'openai' => new OpenAiEmbeddingClient(
                    apiKey: (string) config('ai.embeddings.openai.api_key'),
                    model: (string) config('ai.embeddings.openai.model'),
                    dims: (int) config('ai.embeddings.openai.dimensions', 1536),
                ),
                default => new StubEmbeddingClient,
            };
        });

        $this->app->singleton(AiAssistant::class, function () {
            return match ((string) config('ai.assistant.driver', 'stub')) {
                'claude' => new ClaudeAssistant(
                    apiKey: (string) config('ai.assistant.claude.api_key'),
                    model: (string) config('ai.assistant.claude.model'),
                ),
                default => new StubAiAssistant,
            };
        });

        // NFC providers — Apple VAS / Google Smart Tap. Stub is plain-
        // class instantiable; these two need credentials wired in.
        $this->app->bind(AppleVasProvider::class, fn () => new AppleVasProvider(
            merchantId: config('scanning.nfc.apple_vas.merchant_id'),
            certPath: config('scanning.nfc.apple_vas.cert_path'),
            certPassphrase: config('scanning.nfc.apple_vas.cert_passphrase'),
        ));
        $this->app->bind(GoogleSmartTapProvider::class, fn () => new GoogleSmartTapProvider(
            issuerId: config('scanning.nfc.google_smart_tap.issuer_id'),
            smartTapKeyPath: config('scanning.nfc.google_smart_tap.key_path'),
        ));
    }

    public function boot(): void
    {
        PaymentTransaction::observe(PaymentTransactionObserver::class);
        EventModel::observe(EventObserver::class);
        OfflineTicket::observe(OfflineTicketVoidObserver::class);

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

        // Buyer dashboard — drop a notification into the buyer's bell
        // for every paid order on a linked Buyer account.
        Event::listen(OrderPaid::class, NotifyBuyerOnOrderPaid::class);

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

        // Automation: per-token bucket. Keyed on the resolved token id
        // (set by EnsureAutomationToken middleware) so n8n bursts from
        // one host get their own bucket rather than sharing the IP one.
        $perTokenLimit = max(1, (int) config('automation.token_rate_limit_per_minute', 300));
        RateLimiter::for('automation-token', function (Request $request) use ($perTokenLimit) {
            $token = $request->attributes->get('automation_token');
            $key = $token?->id ? 'token:'.$token->id : 'ip:'.$request->ip();

            return Limit::perMinute($perTokenLimit)->by($key);
        });

        // Scanner pairing: per-IP bucket. Pairing is one-time-code
        // exchange; legitimate clients call this at most a handful of
        // times. Tight cap blunts pairing-code brute force.
        RateLimiter::for('scanner-pair', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->ip() ?? 'anon')));

        // Developer API: per-key bucket honouring the key's tier
        // (Free 60/min, Basic 120, Enterprise 1000, Premium 5000).
        RateLimiter::for('developer-key', function (Request $request) {
            $key = $request->attributes->get('developer_api_key');
            if (! $key) {
                return Limit::perMinute(20)->by('ip:'.$request->ip());
            }
            $policy = \App\Services\Developer\DeveloperTierPolicy::for((string) $key->tier);
            $perMin = (int) ($policy['requests_per_minute'] ?? 60);

            return Limit::perMinute($perMin)->by('dvkey:'.$key->id);
        });
    }
}
