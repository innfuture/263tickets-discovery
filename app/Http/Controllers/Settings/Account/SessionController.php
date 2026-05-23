<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\UserSession;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Active sessions list. Rows are populated by the session-tracking
 * middleware on every request; the current session is highlighted so
 * the user doesn't accidentally sign themselves out.
 */
class SessionController extends SettingsController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        $sessions = UserSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get()
            ->map(fn (UserSession $s) => [
                'id' => $s->id,
                'is_current' => $s->id === $currentId,
                'device_label' => $s->device_label,
                'ip_address' => $s->ip_address,
                'location' => $s->location,
                'user_agent' => $s->user_agent,
                'started_at' => $s->started_at?->toIso8601String(),
                'last_active_at' => $s->last_active_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/account/sessions', [
            'sessions' => $sessions,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Sessions', 'href' => '/settings/sessions'],
            ],
        ]);
    }

    public function destroy(Request $request, string $session, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $row = UserSession::query()
            ->where('user_id', $user->id)
            ->where('id', $session)
            ->firstOrFail();

        abort_if($row->id === $request->session()->getId(), 422, 'Sign out from the menu instead.');

        $row->forceFill(['revoked_at' => now()])->save();

        $audit->record(
            action: 'account.session.revoked',
            user: $user,
            resourceType: 'user_session',
            resourceId: $row->id,
            before: ['device_label' => $row->device_label, 'ip' => $row->ip_address],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session revoked.')]);

        return back();
    }

    public function destroyAll(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        UserSession::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $audit->record(action: 'account.session.revoked_all', user: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All other sessions revoked.')]);

        return back();
    }
}
