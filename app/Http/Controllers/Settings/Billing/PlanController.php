<?php

namespace App\Http\Controllers\Settings\Billing;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan & usage — current tier and a snapshot of consumption for the
 * current billing cycle. The tier is persisted under the org's
 * settings bag; usage is computed live from event + ticket tables.
 */
class PlanController extends SettingsController
{
    public const PLANS = [
        'free' => ['name' => 'Free', 'events_per_month' => 3, 'tickets_per_month' => 200, 'storage_gb' => 1, 'price_cents' => 0],
        'starter' => ['name' => 'Starter', 'events_per_month' => 25, 'tickets_per_month' => 5000, 'storage_gb' => 10, 'price_cents' => 4900],
        'growth' => ['name' => 'Growth', 'events_per_month' => 200, 'tickets_per_month' => 50000, 'storage_gb' => 100, 'price_cents' => 24900],
        'enterprise' => ['name' => 'Enterprise', 'events_per_month' => null, 'tickets_per_month' => null, 'storage_gb' => null, 'price_cents' => null],
    ];

    public function show(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-billing');
        $settings = OrganizationSetting::for($org);

        $tier = (string) $settings->get('plan.tier', 'free');
        $tier = isset(self::PLANS[$tier]) ? $tier : 'free';

        $monthStart = now()->startOfMonth();

        $eventsThisMonth = $org->events()->where('created_at', '>=', $monthStart)->count();
        $eventIds = $org->events()->pluck('id');
        $ticketsThisMonth = (int) DB::table('offline_tickets')
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $monthStart)
            ->count();
        $uniqueAttendees = (int) DB::table('offline_tickets')
            ->whereIn('event_id', $eventIds)
            ->where('created_at', '>=', $monthStart)
            ->distinct()
            ->count('uuid');

        return Inertia::render('settings/billing/plan', [
            'current' => array_merge(self::PLANS[$tier], ['tier' => $tier]),
            'plans' => collect(self::PLANS)
                ->map(fn ($p, $key) => array_merge($p, ['tier' => $key]))
                ->values(),
            'usage' => [
                'events_this_month' => $eventsThisMonth,
                'tickets_this_month' => $ticketsThisMonth,
                'unique_attendees' => $uniqueAttendees,
                'storage_mb' => (int) ($settings->get('plan.storage_mb_used', 0)),
                'period_start' => $monthStart->toIso8601String(),
                'period_end' => now()->endOfMonth()->toIso8601String(),
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Plan & usage', 'href' => '/settings/billing/plan'],
            ],
        ]);
    }

    public function change(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');
        $data = $request->validate([
            'tier' => ['required', 'in:'.implode(',', array_keys(self::PLANS))],
        ]);

        $settings = OrganizationSetting::for($org);
        $before = $settings->get('plan.tier');
        $settings->merge('plan', ['tier' => $data['tier'], 'changed_at' => now()->toIso8601String()]);

        $audit->record('billing.plan.changed', $org, $request->user(), before: ['tier' => $before], after: ['tier' => $data['tier']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }
}
