<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetOrganizationUrlDefaults;
use App\Http\Middleware\TrackUserSession;
use App\Listeners\CreatePersonalOrganization;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Event;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Payment surface — public webhook endpoints + authenticated
            // checkout/status routes. Loaded outside the main web group
            // so we can opt selected URLs out of CSRF below.
            require __DIR__.'/../routes/payments.php';

            // Public scanner API for third-party mobile / kiosk apps.
            // Stateless, bearer-token authenticated, CSRF exempt.
            require __DIR__.'/../routes/api-scanner.php';

            // Public storefront — unauthenticated discovery + checkout.
            // CSRF-exempt below for the JSON POST endpoints.
            require __DIR__.'/../routes/public.php';

            // Automation API (n8n / Zapier / Make). Bearer-token auth,
            // CSRF-exempt below.
            require __DIR__.'/../routes/api-automation.php';

            // Authenticated buyer surface — magic-link sessions.
            require __DIR__.'/../routes/api-buyer.php';

            // Extension marketplace + runtime + developer-portal.
            require __DIR__.'/../routes/api-extensions.php';

            // Public Developer API (tiered) + developer portal.
            require __DIR__.'/../routes/api-developer.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Provider callbacks arrive without a CSRF token; signature
        // verification inside WebhookController is the actual integrity
        // check.
        $middleware->validateCsrfTokens(except: [
            'payments/webhooks/*',
            'sandbox/payments/*',
            'sandbox/service/*',
            'api/v1/scanning/*',
            'api/v1/public/*',
            'api/v1/automations/*',
            'api/v1/buyer/*',
            'api/v1/marketplace/*',
            'api/v1/extensions/*',
            'api/developer/*',
            'widget/v1/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetOrganizationUrlDefaults::class,
            TrackUserSession::class,
        ]);
    })
    ->booted(function (): void {
        Event::listen(Registered::class, CreatePersonalOrganization::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
