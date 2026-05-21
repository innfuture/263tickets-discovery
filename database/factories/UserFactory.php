<?php

namespace Database\Factories;

use App\Enums\TeamRole;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'workos_id' => 'fake-'.Str::random(10),
            'remember_token' => Str::random(10),
            'avatar' => '',
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            $org = Organization::factory()->personal()->create([
                'name' => $user->name."'s Organization",
            ]);

            $org->members()->attach($user, [
                'role' => TeamRole::Owner->value,
            ]);

            $team = Team::factory()->personal()->create([
                'organization_id' => $org->id,
                'name' => 'General',
            ]);

            $team->members()->attach($user, [
                'role' => TeamRole::Owner->value,
            ]);

            $user->switchOrganization($org);
            $user->switchTeam($team);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
