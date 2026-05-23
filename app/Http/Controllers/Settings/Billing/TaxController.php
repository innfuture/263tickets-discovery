<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tax IDs, regional rate table, who-pays-fees toggle, tax-inclusive
 * pricing. All persisted in the org's `taxes` namespace.
 */
class TaxController extends SettingsController
{
    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'finance.export-reports');
        $settings = OrganizationSetting::for($org);
        $taxes = (array) $settings->get('taxes', []);

        return Inertia::render('settings/billing/taxes', [
            'taxes' => [
                'tax_ids' => $taxes['tax_ids'] ?? [],
                'regional_rates' => $taxes['regional_rates'] ?? [],
                'fees_paid_by' => $taxes['fees_paid_by'] ?? 'attendee',
                'tax_inclusive_pricing' => $taxes['tax_inclusive_pricing'] ?? false,
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Taxes', 'href' => '/settings/billing/taxes'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'finance.export-reports');

        $data = $request->validate([
            'tax_ids' => ['nullable', 'array', 'max:16'],
            'tax_ids.*.country_code' => ['required', 'string', 'size:2'],
            'tax_ids.*.label' => ['required', 'string', 'max:32'],
            'tax_ids.*.value' => ['required', 'string', 'max:64'],
            'regional_rates' => ['nullable', 'array', 'max:128'],
            'regional_rates.*.region' => ['required', 'string', 'max:64'],
            'regional_rates.*.rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fees_paid_by' => ['required', 'in:attendee,organizer,split'],
            'tax_inclusive_pricing' => ['required', 'boolean'],
        ]);

        OrganizationSetting::for($org)->merge('taxes', [
            'tax_ids' => array_values($data['tax_ids'] ?? []),
            'regional_rates' => array_values($data['regional_rates'] ?? []),
            'fees_paid_by' => $data['fees_paid_by'],
            'tax_inclusive_pricing' => $data['tax_inclusive_pricing'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tax settings saved.')]);

        return back();
    }
}
