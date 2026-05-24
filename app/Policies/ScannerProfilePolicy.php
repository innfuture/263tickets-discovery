<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\ScannerProfile;
use App\Models\User;

/**
 * Authorization for organizer-side scanner-profile management. The
 * mobile scanner app itself authenticates as a device, not a user,
 * and is gated by EnsureScannerDevice rather than this policy.
 */
class ScannerProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('scanner.profile.view');
    }

    public function view(User $user, ScannerProfile $profile): bool
    {
        return $this->memberOfProfileOrg($user, $profile)
            && $user->hasPermissionTo('scanner.profile.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('scanner.profile.manage');
    }

    public function update(User $user, ScannerProfile $profile): bool
    {
        return $this->memberOfProfileOrg($user, $profile)
            && $user->hasPermissionTo('scanner.profile.manage');
    }

    public function delete(User $user, ScannerProfile $profile): bool
    {
        return $this->memberOfProfileOrg($user, $profile)
            && $user->hasPermissionTo('scanner.profile.manage');
    }

    public function pair(User $user, ScannerProfile $profile): bool
    {
        return $this->memberOfProfileOrg($user, $profile)
            && $user->hasPermissionTo('scanner.profile.manage');
    }

    protected function memberOfProfileOrg(User $user, ScannerProfile $profile): bool
    {
        $orgUuid = (string) ($profile->organisation_id ?? '');
        if ($orgUuid === '') {
            return false;
        }

        $org = Organization::query()->where('uuid', $orgUuid)->first();

        return $org !== null
            && $user->organizations()->whereKey($org->id)->exists();
    }
}
