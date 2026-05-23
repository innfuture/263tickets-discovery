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
        health: '/up',
        then: function (): void {
            // Payment surface — public webhook endpoints + authenticated
            // checkout/status routes. Loaded outside the main web group
            // so we can opt selected URLs out of CSRF below.
            require __DIR__.'/../routes/payments.php';
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
