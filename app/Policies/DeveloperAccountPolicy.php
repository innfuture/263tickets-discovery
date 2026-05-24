<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeveloperAccount;
use App\Models\User;

/**
 * Backoffice-side authorization for DeveloperAccount admin actions.
 * Portal self-service is gated by the bootstrap-token middleware and
 * does not go through this policy.
 */
class DeveloperAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('developer.manage');
    }

    public function view(User $user, DeveloperAccount $account): bool
    {
        return $user->hasPermissionTo('developer.manage');
    }

    public function suspend(User $user, DeveloperAccount $account): bool
    {
        return $user->hasPermissionTo('developer.manage');
    }

    public function rotateToken(User $user, DeveloperAccount $account): bool
    {
        return $user->hasPermissionTo('developer.manage');
    }
}
