<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Models\PaymentMethod;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cards on file for the org's platform subscription. The provider_id
 * is opaque — in a Stripe-connected deploy it stores `pm_…`; here it
 * is a deterministic local placeholder so the page lights up E2E.
 */
class PaymentMethodController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-billing');
        $settings = OrganizationSetting::for($org);

        $methods = PaymentMethod::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PaymentMethod $m) => [
                'id' => $m->id,
                'brand' => $m->brand,
                'last4' => $m->last4,
                'exp_month' => $m->exp_month,
                'exp_year' => $m->exp_year,
                'holder_name' => $m->holder_name,
                'is_default' => $m->is_default,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/billing/methods', [
            'methods' => $methods,
            'billing' => [
                'email' => $settings->get('billing.email'),
                'address' => $settings->get('billing.address'),
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Payment methods', 'href' => '/settings/billing/methods'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');

        $data = $request->validate([
            'brand' => ['required', 'in:visa,mastercard,amex,discover,jcb,unionpay'],
            'last4' => ['required', 'digits:4'],
            'exp_month' => ['required', 'integer', 'between:1,12'],
            'exp_year' => ['required', 'integer', 'min:'.now()->year, 'max:'.(now()->year + 20)],
            'holder_name' => ['required', 'string', 'max:120'],
            'is_default' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($org, $data) {
            if ($data['is_default']) {
                PaymentMethod::where('organization_id', $org->id)->update(['is_default' => false]);
            }

            PaymentMethod::create([
                ...$data,
                'organization_id' => $org->id,
                'provider_id' => 'pm_local_'.bin2hex(random_bytes(8)),
            ]);
        });

        $audit->record('billing.method.added', $org, $request->user(), after: ['brand' => $data['brand'], 'last4' => $data['last4']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card added.')]);

        return back();
    }

    public function setDefault(Request $request, PaymentMethod $method): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');
        abort_if($method->organization_id !== $org->id, 403);

        DB::transaction(function () use ($org, $method) {
            PaymentMethod::where('organization_id', $org->id)->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default card updated.')]);

        return back();
    }

    public function destroy(Request $request, PaymentMethod $method, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');
        abort_if($method->organization_id !== $org->id, 403);

        $audit->record('billing.method.removed', $org, $request->user(), before: ['last4' => $method->last4]);
        $method->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Card removed.')]);

        return back();
    }

    public function updateBillingDetails(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        OrganizationSetting::for($org)->merge('billing', $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing contact updated.')]);

        return back();
    }
}
