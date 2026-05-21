<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot for the org-level `organization_members` table. Mirrors the
 * sub-team `Membership` pivot but at the parent tier — a user's role
 * here (Owner/Admin/Member) is what governs every org-wide capability
 * (editing the brand, managing teams, inviting people in).
 */
#[Fillable(['organization_id', 'user_id', 'role'])]
class OrganizationMembership extends Pivot
{
    protected $table = 'organization_members';

    public $incrementing = true;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
