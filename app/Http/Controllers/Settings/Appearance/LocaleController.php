<?php

namespace App\Http\Controllers\Settings\Appearance;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-level locale + timezone defaults.
 *
 * The fields here (`default_currency`, `default_timezone`) already
 * exist on the `organizations` table — they are surfaced + edited via
 * the big Organization Profile form today. This controller decouples
 * them onto their own focused page so a user changing the timezone
 * doesn't have to scroll past 20 unrelated fields.
 */
class LocaleController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('organization.update'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        return Inertia::render('settings/appearance/locale', [
            'organization' => [
                'default_currency' => $org->default_currency,
                'default_timezone' => $org->default_timezone,
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Locale &amp; timezone', 'href' => '/settings/appearance/locale'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('organization.update'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $data = $request->validate([
            'default_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'default_timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $org->update([
            'default_currency' => $data['default_currency']
                ? strtoupper($data['default_currency'])
                : null,
            'default_timezone' => $data['default_timezone'] ?: null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Locale &amp; timezone saved.')]);

        return back();
    }
}
