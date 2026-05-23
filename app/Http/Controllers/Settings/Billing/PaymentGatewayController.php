<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings page for picking which payment gateways are active for this
 * deployment and which one is the default. Credentials live in .env
 * (platform-wide), so this surface stores only the org-level toggles +
 * default selection on the OrganizationSetting bag.
 */
class PaymentGatewayController extends SettingsController
{
    public function __construct(protected PaymentManager $payments) {}

    public function index(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-billing');
        $settings = OrganizationSetting::for($org);

        $allGateways = collect($this->payments->all())
            ->map(function (string $name) {
                $config = (array) config("payments.gateways.{$name}", []);

                return [
                    'id' => $name,
                    'label' => (string) ($config['label'] ?? ucfirst($name)),
                    'configured' => $this->isConfigured($name, $config),
                    'enabled_globally' => (bool) ($config['enabled'] ?? false),
                    'supported_currencies' => array_values(array_map('strval', $config['supported_currencies'] ?? [])),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('settings/billing/gateways', [
            'gateways' => $allGateways,
            'selection' => [
                'default' => (string) ($settings->get('payments.default') ?? config('payments.default')),
                'enabled' => (array) ($settings->get('payments.enabled') ?? $this->payments->enabled()),
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Payment gateways', 'href' => '/settings/billing/gateways'],
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');

        $available = $this->payments->all();

        $data = $request->validate([
            'default' => ['required', 'string', 'in:'.implode(',', $available)],
            'enabled' => ['array'],
            'enabled.*' => ['string', 'in:'.implode(',', $available)],
        ]);

        OrganizationSetting::for($org)->merge('payments', [
            'default' => $data['default'],
            'enabled' => array_values(array_unique($data['enabled'] ?? [])),
        ]);

        $audit->record('payments.gateways.updated', $org, $request->user(), after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gateway selection saved.')]);

        return back();
    }

    /**
     * Heuristic: a gateway is "configured" once its required credentials
     * are present in config. Used to grey-out toggles the operator
     * cannot meaningfully turn on yet.
     *
     * @param  array<string, mixed>  $config
     */
    protected function isConfigured(string $name, array $config): bool
    {
        return match ($name) {
            'ecocash' => ! empty(data_get($config, 'auth.username'))
                && ! empty(data_get($config, 'auth.password'))
                && ! empty(data_get($config, 'merchant.code')),
            'paynow' => ! empty($config['integration_id']) && ! empty($config['integration_key']),
            'pesepay' => ! empty($config['integration_key']) && ! empty($config['encryption_key']),
            'zimswitch' => ! empty($config['access_token']) && ! empty($config['entity_id']),
            default => false,
        };
    }
}
