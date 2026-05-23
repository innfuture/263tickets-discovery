<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentGateway;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Exceptions\GatewayNotConfiguredException;
use App\Services\Payments\Exceptions\PaymentException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared scaffolding for every driver: config accessor, HTTP client
 * factory with sane defaults, currency guard, webhook URL builder.
 *
 * Subclasses set `protected string $identifier` and implement the
 * provider-specific request shapes. Everything reusable lives here so
 * the driver files stay focused on the wire format.
 */
abstract class AbstractGateway implements PaymentGateway
{
    protected string $identifier;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function label(): string
    {
        return $this->config['label'] ?? ucfirst($this->identifier);
    }

    /**
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        return array_map('strval', $this->config['supported_currencies'] ?? []);
    }

    /**
     * Read a dotted key from the driver's config slice, falling back
     * to `$default`. Throws GatewayNotConfiguredException if the key
     * is missing and `$required` is true — preferred over silently
     * sending blanks to the upstream API.
     */
    protected function config(string $key, mixed $default = null, bool $required = false): mixed
    {
        $value = data_get($this->config, $key, $default);

        if ($required && ($value === null || $value === '')) {
            throw new GatewayNotConfiguredException(
                sprintf('Missing config %s.%s for gateway %s.', $this->identifier, $key, $this->identifier)
            );
        }

        return $value;
    }

    protected function httpClient(): PendingRequest
    {
        $http = $this->config['http'] ?? [];

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($http['timeout'] ?? 30))
            ->connectTimeout((int) ($http['connect_timeout'] ?? 10))
            ->retry((int) ($http['retries'] ?? 0), 200, throw: false)
            ->withHeaders([
                'User-Agent' => 'example-app-payments/1.0 ('.$this->identifier.')',
            ]);
    }

    protected function webhookUrl(): string
    {
        $base = rtrim((string) config('payments.webhook.base_url'), '/');

        return $base.'/'.$this->identifier;
    }

    /**
     * Guard the requested currency against the driver's allow-list so we
     * fail fast with a friendly message rather than letting the gateway
     * reject the charge on the wire.
     */
    protected function assertCurrencySupported(ChargeRequest $request): void
    {
        $supported = $this->supportedCurrencies();
        if ($supported === []) {
            return;
        }

        $currency = $request->amount->currency;
        if (! in_array($currency, $supported, true)) {
            throw new PaymentException(
                sprintf('%s does not accept %s (supports: %s).',
                    $this->label(), $currency, implode(', ', $supported))
            );
        }
    }
}
