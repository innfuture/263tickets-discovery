<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\Support\IdempotencyCache;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves and caches gateway driver instances. Callers use:
 *
 *   $manager->gateway('paynow')->charge(...)
 *   $manager->default()->charge(...)
 *
 * Drivers are read from `config/payments.php`. Adding a new provider
 * means dropping in a Driver class, listing it in config, and the
 * manager picks it up — no editing required here.
 *
 * Disabled gateways (config flag `enabled => false`) throw on lookup
 * so we don't fall back silently to the default.
 */
class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    protected array $resolved = [];

    public function __construct(protected Container $container) {}

    public function default(): PaymentGateway
    {
        return $this->gateway($this->defaultIdentifier());
    }

    public function defaultIdentifier(): string
    {
        $default = (string) config('payments.default');
        if ($default === '') {
            throw new GatewayNotConfiguredException('payments.default is not set.');
        }

        return $default;
    }

    public function gateway(string $name): PaymentGateway
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $config = config("payments.gateways.{$name}");
        if (! is_array($config)) {
            throw new GatewayNotConfiguredException("Unknown payment gateway: {$name}");
        }

        if (! ($config['enabled'] ?? false)) {
            throw new GatewayNotConfiguredException("Payment gateway {$name} is disabled.");
        }

        $driverClass = $config['driver'] ?? null;
        if (! is_string($driverClass) || ! class_exists($driverClass)) {
            throw new GatewayNotConfiguredException("Driver class missing for gateway {$name}.");
        }

        $instance = $this->container->make($driverClass, ['config' => $config]);
        if (! $instance instanceof PaymentGateway) {
            throw new GatewayNotConfiguredException(
                "Driver {$driverClass} does not implement PaymentGateway."
            );
        }

        return $this->resolved[$name] = $instance;
    }

    /**
     * Charge through a named gateway with optional idempotency. Calling
     * with the same $idempotencyKey within the configured TTL returns
     * the original ChargeResult instead of double-charging.
     *
     * Gateways are free to call $manager->gateway($name)->charge(...)
     * directly when they handle their own idempotency (the sandbox
     * already does). For every other case, route through here.
     */
    public function charge(string $gateway, ChargeRequest $request, ?string $idempotencyKey = null): ChargeResult
    {
        $driver = $this->gateway($gateway);

        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $driver->charge($request);
        }

        return IdempotencyCache::default()->remember(
            $gateway,
            $idempotencyKey,
            fn (): ChargeResult => $driver->charge($request),
        );
    }

    /**
     * List the identifiers of gateways currently enabled. The settings
     * UI uses this to render the toggle list.
     *
     * @return array<int, string>
     */
    public function enabled(): array
    {
        $enabled = [];
        foreach ((array) config('payments.gateways') as $name => $config) {
            if (($config['enabled'] ?? false) === true) {
                $enabled[] = (string) $name;
            }
        }

        return $enabled;
    }

    /**
     * Identifiers of every configured gateway regardless of state — used
     * for ops surfaces that show what's available to enable.
     *
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_map('strval', array_keys((array) config('payments.gateways')));
    }
}
