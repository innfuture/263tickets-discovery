<?php

namespace App\Listeners;

use App\Actions\Organizations\CreateOrganization;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * On new user registration, materialise a personal Organization (and
 * its default sub-team) so the hierarchy is populated before the user
 * lands on the dashboard.
 */
class CreatePersonalOrganization
{
    public function __construct(private CreateOrganization $createOrganization)
    {
        //
    }

    public function handle(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $this->createOrganization->handle($user, $user->name."'s Organization", isPersonal: true);
    }
}
