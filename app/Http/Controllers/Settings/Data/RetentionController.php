<?php

namespace App\Http\Controllers\Settings\Data;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data retention windows. Defaults follow common legal recommendations
 * (e.g. 7y for financial records); operators can tighten per policy.
 */
class RetentionController extends SettingsController
{
    private const DEFAULTS = [
        'events_days' => 365 * 3,
        'attendee_pii_days' => 365,
        'tickets_days' => 365 * 7,
        'audit_log_days' => 365 * 2,
        'backups_days' => 30,
        'apply_to_existing' => false,
    ];

    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.manage-billing');
        $settings = OrganizationSetting::for($org);
        $retention = array_replace(self::DEFAULTS, (array) $settings->get('retention', []));

        return Inertia::render('settings/data/retention', [
            'retention' => $retention,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Retention', 'href' => '/settings/data/retention'],
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization.manage-billing');

        $data = $request->validate([
            'events_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'attendee_pii_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'tickets_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'audit_log_days' => ['required', 'integer', 'min:90', 'max:3650'],
            'backups_days' => ['required', 'integer', 'min:7', 'max:365'],
            'apply_to_existing' => ['required', 'boolean'],
        ]);

        OrganizationSetting::for($org)->merge('retention', $data);

        $audit->record('data.retention.updated', $org, $request->user(), after: $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retention policy saved.')]);

        return back();
    }
}
