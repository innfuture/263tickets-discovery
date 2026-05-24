<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Automation;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AutomationToken;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD + issue/revoke for `aut_…` automation tokens. The plaintext
 * secret is shown exactly once at issuance — never persisted, never
 * recoverable. Rotate by minting a fresh token + revoking the old.
 */
class AutomationTokenController extends Controller
{
    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => AutomationToken::query()
                ->where('organization_id', $currentOrganization->id)
                ->orderByDesc('id')
                ->get([
                    'id', 'uuid', 'label', 'display_prefix', 'scopes',
                    'last_used_at', 'last_used_ip', 'expires_at',
                    'revoked_at', 'created_at',
                ]),
        ]);
    }

    public function store(Organization $currentOrganization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:'.implode(',', AutomationToken::ALL_SCOPES).',*'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $issued = AutomationToken::issue(
            org: $currentOrganization,
            label: $validated['label'],
            scopes: $validated['scopes'],
            creator: $request->user(),
            expiresAt: isset($validated['expires_at']) ? new \DateTimeImmutable($validated['expires_at']) : null,
        );

        AuditLog::create([
            'organization_id' => $currentOrganization->id,
            'user_id' => $request->user()?->id,
            'actor_type' => 'user',
            'action' => 'automation_token.issued',
            'resource_type' => AutomationToken::class,
            'resource_id' => (string) $issued['model']->id,
            'after' => ['label' => $issued['model']->label, 'scopes' => $issued['model']->scopes],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'data' => [
                'uuid' => $issued['model']->uuid,
                'label' => $issued['model']->label,
                'scopes' => $issued['model']->scopes,
                'plaintext' => $issued['plaintext'],
                'display_prefix' => $issued['model']->display_prefix,
                'note' => 'This token is shown once. Store it now — it cannot be recovered.',
            ],
        ], 201);
    }

    public function revoke(Organization $currentOrganization, AutomationToken $token, Request $request): JsonResponse
    {
        abort_if($token->organization_id !== $currentOrganization->id, 404);

        $token->forceFill(['revoked_at' => now()])->save();

        AuditLog::create([
            'organization_id' => $currentOrganization->id,
            'user_id' => $request->user()?->id,
            'actor_type' => 'user',
            'action' => 'automation_token.revoked',
            'resource_type' => AutomationToken::class,
            'resource_id' => (string) $token->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
