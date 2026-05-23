<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Payments\PaymentManager;
use App\Services\Payments\Sandbox\BalanceLedger;
use App\Services\Payments\Sandbox\ContractValidator;
use App\Services\Payments\Sandbox\MagicValues;
use App\Services\Payments\Sandbox\Recorder;
use App\Services\Payments\Sandbox\SandboxClock;
use App\Services\Payments\Sandbox\SandboxKernel;
use App\Services\Payments\Sandbox\SavedMethods;
use App\Services\Payments\Sandbox\ScenarioResolver;
use App\Services\Payments\Sandbox\Signers\SignerRegistry;
use App\Services\Payments\Sandbox\StateMachine;
use App\Services\Payments\Sandbox\WalletTokenSimulator;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payments.php', 'payments');

        $this->app->singleton(PaymentManager::class, fn (Container $app) => new PaymentManager($app));
        $this->app->alias(PaymentManager::class, 'payments');

        // Sandbox kernel — singletons so virtual-clock state and the
        // forced-failure map persist across calls in the same request /
        // test boot.
        $this->app->singleton(MagicValues::class);
        $this->app->singleton(SandboxClock::class);
        $this->app->singleton(SignerRegistry::class);
        $this->app->singleton(StateMachine::class);
        $this->app->singleton(ScenarioResolver::class);
        $this->app->singleton(BalanceLedger::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(SandboxKernel::class);
        $this->app->singleton(SavedMethods::class);
        $this->app->singleton(WalletTokenSimulator::class);
        $this->app->singleton(Recorder::class);
        $this->app->singleton(ContractValidator::class);
        $this->app->alias(SandboxKernel::class, 'payments.sandbox');
    }

    public function boot(): void
    {
        //
    }
}
