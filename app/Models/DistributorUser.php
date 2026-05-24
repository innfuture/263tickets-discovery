<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot binding a User to a Distributor with a role. Distinct from
 * the org-level RBAC because a distributor's staff may not be members
 * of the parent organization at all (think: a pharmacy chain that
 * sells tickets for many organizers, with its own staff).
 */
class DistributorUser extends Model
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_SELLER = 'seller';
    public const ROLE_AUDITOR = 'auditor';

    protected $fillable = [
        'distributor_id', 'user_id', 'role',
        'invited_at', 'accepted_at', 'revoked_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Distributor, $this> */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->accepted_at !== null;
    }
}
