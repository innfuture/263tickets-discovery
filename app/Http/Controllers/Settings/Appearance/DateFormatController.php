<?php

namespace App\Http\Controllers\Settings\Appearance;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Date + number format preferences. Read by the frontend formatter
 * helper; applied in the dashboard and on attendee-facing surfaces.
 */
class DateFormatController extends SettingsController
{
    private const DEFAULTS = [
        'date_format' => 'YYYY-MM-DD',
        'time_format' => '24h',
        'week_start' => 'monday',
        'number_grouping' => 'comma',
        'fiscal_year_start_month' => 1,
    ];

    public const OPTIONS = [
        'date_format' => ['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'D MMM YYYY', 'MMM D, YYYY'],
        'time_format' => ['12h', '24h'],
        'week_start' => ['sunday', 'monday'],
        'number_grouping' => ['comma', 'space', 'dot'],
    ];

    public function edit(Request $request): Response
    {
        $org = $this->org($request, 'organization.update');
        $settings = OrganizationSetting::for($org);
        $dates = array_replace(self::DEFAULTS, (array) $settings->get('dates', []));

        return Inertia::render('settings/appearance/dates', [
            'dates' => $dates,
            'options' => self::OPTIONS,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Date formats', 'href' => '/settings/appearance/dates'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $org = $this->org($request, 'organization.update');

        $data = $request->validate([
            'date_format' => ['required', 'in:'.implode(',', self::OPTIONS['date_format'])],
            'time_format' => ['required', 'in:12h,24h'],
            'week_start' => ['required', 'in:sunday,monday'],
            'number_grouping' => ['required', 'in:comma,space,dot'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
        ]);

        OrganizationSetting::for($org)->merge('dates', $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Date & number formats saved.')]);

        return back();
    }
}
