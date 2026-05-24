<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

/**
 * Defensive authorization layer for Event model access. Sits on top
 * of (not in place of) the `permission:event.*` route middleware so
 * direct model access from jobs, console commands, or backoffice code
 * paths that bypass route middleware still gets enforced.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('event.view');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->memberOfEventOrg($user, $event)
            && $user->hasPermissionTo('event.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('event.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->memberOfEventOrg($user, $event)
            && $user->hasPermissionTo('event.update');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->memberOfEventOrg($user, $event)
            && $user->hasPermissionTo('event.delete');
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->memberOfEventOrg($user, $event)
            && $user->hasPermissionTo('event.publish');
    }

    protected function memberOfEventOrg(User $user, Event $event): bool
    {
        $orgUuid = (string) ($event->organisation_id ?? '');
        if ($orgUuid === '') {
            return false;
        }

        $org = Organization::query()->where('uuid', $orgUuid)->first();

        return $org !== null
            && $user->organizations()->whereKey($org->id)->exists();
    }
}
