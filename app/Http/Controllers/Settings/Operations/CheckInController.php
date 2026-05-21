<?php

namespace App\Http\Controllers\Settings\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Check-in & scanning settings. The PIN is the only piece that's
 * actually persisted — it lives on `organizations.door_pin` and lets
 * the scanner app authenticate venue-side door staff for the active
 * org without re-entering full credentials.
 *
 * The rest of the page (scanner-mode TTL, simultaneous-scan limit,
 * after-hours lockout) is scaffolded for future work.
 */
class CheckInController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('ticket.scan'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        return Inertia::render('settings/operations/check-in', [
            'doorPin' => $org->door_pin,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Check-in &amp; scanning', 'href' => '/settings/operations/check-in'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ticket.scan'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $action = $request->input('action', 'regenerate');

        if ($action === 'clear') {
            $org->forceFill(['door_pin' => null])->save();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Door PIN cleared.')]);

            return back();
        }

        // 8-char alphanumeric uppercase, e.g. 7K2F9X3M. Easy to read
        // off a printed sheet or whispered between staff.
        $pin = strtoupper(Str::random(8));
        $org->forceFill(['door_pin' => $pin])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Door PIN regenerated.')]);

        return back();
    }
}
