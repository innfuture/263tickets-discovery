<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasOrganizations;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'workos_id', 'avatar', 'current_team_id', 'current_organization_id'])]
#[Hidden(['workos_id', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOrganizations, HasRoles, HasTeams, Notifiable {
        // Our HasTeams::teams() is the sub-team belongsToMany the rest
        // of the app uses. Spatie's HasRoles::teams() is its internal
        // helper that returns a morphToMany to the configured team
        // model (Organization here, since we scope its team_id to
        // organization_id). We never call it — alias it out so it
        // doesn't collide.
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as spatieRoleTeams;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
