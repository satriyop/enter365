<?php

namespace Database\Factories\Core;

use App\Models\Core\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $groups = [
            Permission::GROUP_ACCOUNTS,
            Permission::GROUP_CONTACTS,
            Permission::GROUP_PRODUCTS,
            Permission::GROUP_INVOICES,
            Permission::GROUP_BILLS,
            Permission::GROUP_PAYMENTS,
            Permission::GROUP_JOURNALS,
            Permission::GROUP_INVENTORY,
            Permission::GROUP_BUDGETS,
            Permission::GROUP_REPORTS,
            Permission::GROUP_SETTINGS,
            Permission::GROUP_USERS,
        ];

        $actions = ['view', 'create', 'edit', 'delete'];
        $group = $this->faker->randomElement($groups);
        $action = $this->faker->randomElement($actions);

        return [
            'name' => $this->faker->unique()->slug(3).'.'.$action,
            'display_name' => ucfirst($action).' '.ucfirst($group),
            'group' => $group,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    public function inGroup(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group' => $group,
        ]);
    }

    public function viewPermission(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $group.'.view',
            'display_name' => 'Lihat '.ucfirst($group),
            'group' => $group,
        ]);
    }

    public function createPermission(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $group.'.create',
            'display_name' => 'Buat '.ucfirst($group),
            'group' => $group,
        ]);
    }

    public function editPermission(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $group.'.edit',
            'display_name' => 'Edit '.ucfirst($group),
            'group' => $group,
        ]);
    }

    public function deletePermission(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $group.'.delete',
            'display_name' => 'Hapus '.ucfirst($group),
            'group' => $group,
        ]);
    }
}
