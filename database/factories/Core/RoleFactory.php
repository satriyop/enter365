<?php

namespace Database\Factories\Core;

use App\Models\Core\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'display_name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::ADMIN,
            'display_name' => 'Administrator',
            'description' => 'Full system access',
            'is_system' => true,
        ]);
    }

    public function accountant(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::ACCOUNTANT,
            'display_name' => 'Akuntan',
            'description' => 'Akses ke fitur akuntansi',
            'is_system' => true,
        ]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::CASHIER,
            'display_name' => 'Kasir',
            'description' => 'Akses ke pembayaran dan faktur',
            'is_system' => true,
        ]);
    }

    public function inventory(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::INVENTORY,
            'display_name' => 'Inventori',
            'description' => 'Akses ke manajemen inventori',
            'is_system' => true,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::VIEWER,
            'display_name' => 'Viewer',
            'description' => 'Hanya bisa melihat',
            'is_system' => true,
        ]);
    }
}
