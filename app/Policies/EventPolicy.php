<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Event;
use App\Models\User;

/**
 * Event authorisation. Two gates apply:
 *
 *   1. Org-level permission via Spatie (e.g. event.update).
 *   2. Optional sub-team gating: when an event has `team_id` set, only
 *      members of that sub-team can mutate it. Events with null
 *      team_id are "org-wide" and any member with the relevant
 *      permission can act on them.
 *
 * View permission deliberately skips sub-team gating — analytics &
 * reporting roles need read access regardless of team membership.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EventView->value);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->inCurrentOrg($user, $event)
            && $user->can(Permission::EventView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EventCreate->value);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->inCurrentOrg($user, $event)
            && $user->can(Permission::EventUpdate->value)
            && $this->onEventTeam($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->inCurrentOrg($user, $event)
            && $user->can(Permission::EventDelete->value)
            && $this->onEventTeam($user, $event);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->inCurrentOrg($user, $event)
            && $user->can(Permission::EventPublish->value)
            && $this->onEventTeam($user, $event);
    }

    public function unpublish(User $user, Event $event): bool
    {
        return $this->inCurrentOrg($user, $event)
            && $user->can(Permission::EventUnpublish->value)
            && $this->onEventTeam($user, $event);
    }

    public function duplicate(User $user, Event $event): bool
    {
        return $this->view($user, $event)
            && $user->can(Permission::EventDuplicate->value);
    }

    /**
     * Both the user and the event must belong to the active org.
     * Events store their org by uuid (`organisation_id`), so we
     * compare against the user's `currentOrganization->uuid`.
     */
    private function inCurrentOrg(User $user, Event $event): bool
    {
        return $user->currentOrganization?->uuid !== null
            && $event->organisation_id === $user->currentOrganization->uuid;
    }

    /**
     * Sub-team gate. Event without a team_id is treated as org-wide
     * (any qualified member acts on it). Event WITH a team_id requires
     * the viewer to belong to that team.
     */
    private function onEventTeam(User $user, Event $event): bool
    {
        if ($event->team_id === null) {
            return true;
        }

        return $user->teams()->where('teams.id', $event->team_id)->exists();
    }
}
