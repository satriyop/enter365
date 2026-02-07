<?php

namespace Database\Factories;

use App\Models\Core\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
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

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign admin role after creating.
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $role = Role::firstOrCreate(
                ['name' => Role::ADMIN],
                ['display_name' => 'Administrator', 'description' => 'Full system access']
            );
            $user->roles()->attach($role);
        });
    }

    /**
     * Assign specific role after creating.
     */
    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function ($user) use ($roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->roles()->attach($role);
            }
        });
    }
}
