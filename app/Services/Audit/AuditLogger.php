<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Single entry point for writing audit log rows. Called from settings
 * controllers whenever a privileged action lands (key rotated,
 * member role changed, integration disconnected, refund issued…).
 *
 * Reads request context (IP + UA) automatically when invoked inside
 * an HTTP cycle; pass `null` for system-initiated writes.
 */
class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    public function record(
        string $action,
        ?Organization $organization = null,
        ?User $user = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $before = null,
        ?array $after = null,
        string $actorType = 'user',
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization?->id,
            'user_id' => $user?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->request?->ip(),
            'user_agent' => substr((string) $this->request?->userAgent(), 0, 512) ?: null,
            'created_at' => now(),
        ]);
    }
}
