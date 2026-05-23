<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\PersonalApiToken;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal API tokens — user-scoped tokens that inherit the holder's
 * effective permissions. The raw secret is shown exactly once on
 * creation (flashed via session); after that only the prefix and
 * stored SHA-256 hash remain.
 */
class ApiTokenController extends SettingsController
{
    private const SCOPES = [
        ['key' => 'read', 'label' => 'Read', 'hint' => 'GET requests for events, tickets, attendees.'],
        ['key' => 'write', 'label' => 'Write', 'hint' => 'POST/PATCH on events, tickets, ticket inventory.'],
        ['key' => 'finance', 'label' => 'Finance', 'hint' => 'Read revenue, process refunds, export reports.'],
        ['key' => 'admin', 'label' => 'Admin', 'hint' => 'Full account-level admin including roles and members.'],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tokens = PersonalApiToken::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalApiToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'prefix' => $t->prefix,
                'scopes' => $t->scopes ?? [],
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
                'revoked_at' => $t->revoked_at?->toIso8601String(),
                'created_at' => $t->created_at->toIso8601String(),
                'is_active' => $t->isActive(),
            ]);

        return Inertia::render('settings/account/api-tokens', [
            'tokens' => $tokens,
            'scopeOptions' => self::SCOPES,
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API tokens', 'href' => '/settings/api-tokens'],
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:'.implode(',', array_column(self::SCOPES, 'key'))],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $mint = PersonalApiToken::mint([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'scopes' => $data['scopes'],
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays($data['expires_in_days'])
                : null,
        ]);

        $audit->record(
            action: 'account.token.created',
            user: $request->user(),
            resourceType: 'personal_api_token',
            resourceId: (string) $mint['model']->id,
            after: ['name' => $data['name'], 'scopes' => $data['scopes']],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created. Copy it now — you won\'t see it again.')]);

        return back()->with('newToken', $mint['plain']);
    }

    public function destroy(Request $request, PersonalApiToken $token, AuditLogger $audit): RedirectResponse
    {
        abort_if($token->user_id !== $request->user()->id, 403);

        $token->forceFill(['revoked_at' => now()])->save();

        $audit->record(
            action: 'account.token.revoked',
            user: $request->user(),
            resourceType: 'personal_api_token',
            resourceId: (string) $token->id,
            before: ['name' => $token->name],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
