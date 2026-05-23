<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Models\Payout;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Payouts — connect a destination (Stripe Connect, manual bank), set
 * the schedule, view the payout history. The Stripe Connect handshake
 * lives in the integrations layer; this page reads the persisted
 * connection state and lists payout rows.
 */
class PayoutController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'finance.view-revenue');
        $settings = OrganizationSetting::for($org);
        $connect = (array) $settings->get('payouts.connect', []);

        $payouts = Payout::query()
            ->where('organization_id', $org->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Payout $p) => [
                'id' => $p->id,
                'amount_cents' => $p->amount_cents,
                'amount_formatted' => number_format($p->amount_cents / 100, 2),
                'currency' => $p->currency,
                'status' => $p->status,
                'destination_label' => $p->destination_label,
                'arrival_on' => $p->arrival_on?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/billing/payouts', [
            'connect' => [
                'status' => $connect['status'] ?? 'unconfigured',
                'destination_label' => $connect['destination_label'] ?? null,
                'schedule' => $connect['schedule'] ?? 'weekly',
                'withholding_percent' => $connect['withholding_percent'] ?? 0,
                'connected_at' => $connect['connected_at'] ?? null,
            ],
            'payouts' => $payouts,
            'scheduleOptions' => ['manual', 'daily', 'weekly', 'biweekly', 'monthly'],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Payouts', 'href' => '/settings/billing/payouts'],
            ],
        ]);
    }

    public function connect(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'finance.view-revenue');

        $data = $request->validate([
            'destination_label' => ['required', 'string', 'max:120'],
            'account_holder' => ['required', 'string', 'max:120'],
            'account_last4' => ['required', 'digits:4'],
            'routing_last4' => ['nullable', 'digits:4'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        OrganizationSetting::for($org)->merge('payouts.connect', [
            'status' => 'active',
            'destination_label' => $data['destination_label'],
            'account_holder' => $data['account_holder'],
            'account_last4' => $data['account_last4'],
            'routing_last4' => $data['routing_last4'] ?? null,
            'country_code' => strtoupper($data['country_code']),
            'connected_at' => now()->toIso8601String(),
        ]);

        $audit->record('payouts.connected', $org, $request->user(), after: ['destination' => $data['destination_label']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payout destination connected.')]);

        return back();
    }

    public function updateSchedule(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'finance.view-revenue');
        $data = $request->validate([
            'schedule' => ['required', 'in:manual,daily,weekly,biweekly,monthly'],
            'withholding_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        OrganizationSetting::for($org)->merge('payouts.connect', $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule saved.')]);

        return back();
    }

    public function disconnect(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'finance.view-revenue');
        OrganizationSetting::for($org)->set('payouts.connect', null);

        $audit->record('payouts.disconnected', $org, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Destination disconnected.')]);

        return back();
    }
}
