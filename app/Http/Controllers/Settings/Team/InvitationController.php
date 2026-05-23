<?php

namespace App\Http\Controllers\Settings\Team;

use App\Enums\TeamRole;
use App\Http\Controllers\Settings\SettingsController;
use App\Models\OrganizationInvitation;
use App\Notifications\Organizations\OrganizationInvitation as OrganizationInvitationNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending invitation list. Existing send + cancel logic lives in
 * Organizations\OrganizationInvitationController; this controller
 * adds the org-wide list view + a resend action that mints a fresh
 * expiry and re-sends the email.
 */
class InvitationController extends SettingsController
{
    public function index(Request $request): Response
    {
        $org = $this->org($request, 'organization_member.invite');

        $invitations = $org->invitations()
            ->with('inviter')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role instanceof TeamRole ? $i->role->value : (string) $i->role,
                'invited_by' => $i->inviter?->name,
                'sent_at' => $i->created_at->toIso8601String(),
                'expires_at' => $i->expires_at?->toIso8601String(),
                'is_expired' => $i->expires_at?->isPast() ?? false,
            ]);

        return Inertia::render('settings/team/invitations', [
            'invitations' => $invitations,
            'roles' => collect(TeamRole::cases())
                ->reject(fn ($r) => $r === TeamRole::Owner)
                ->map(fn ($r) => ['value' => $r->value, 'label' => ucfirst($r->value)])
                ->values(),
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Invitations', 'href' => '/settings/team/invitations'],
            ],
        ]);
    }

    public function resend(Request $request, OrganizationInvitation $invitation, AuditLogger $audit): RedirectResponse
    {
        $org = $this->org($request, 'organization_member.invite');
        abort_if($invitation->organization_id !== $org->id, 403);

        $invitation->update(['expires_at' => now()->addDays(3)]);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        $audit->record('org.invitation.resent', $org, $request->user(), resourceId: (string) $invitation->id, after: ['email' => $invitation->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent.')]);

        return back();
    }
}
