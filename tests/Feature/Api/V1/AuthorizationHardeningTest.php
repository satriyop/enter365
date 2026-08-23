<?php

declare(strict_types=1);

use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

/**
 * Creates a low-privilege user holding a role with only the given permissions.
 * Models a POS cashier: a real login with a deliberately narrow permission set.
 */
function lowPrivilegeUser(array $permissionNames = []): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'kasir-test', 'display_name' => 'Kasir Test']);

    if ($permissionNames !== []) {
        $role->permissions()->sync(
            Permission::whereIn('name', $permissionNames)->pluck('id')->all()
        );
    }

    $user->roles()->attach($role);

    return $user->fresh('roles');
}

describe('privilege escalation via role permission sync', function () {
    it('blocks a low-privilege user from granting their own role every permission', function () {
        $user = lowPrivilegeUser(['pos.sale.checkout']);
        $role = $user->roles->first();
        Sanctum::actingAs($user);

        $allPermissionIds = Permission::pluck('id')->all();

        $this->postJson("/api/v1/roles/{$role->id}/sync-permissions", [
            'permissions' => $allPermissionIds,
        ])->assertForbidden();

        expect($user->fresh()->hasPermission('invoices.void'))->toBeFalse()
            ->and($role->fresh()->permissions()->count())->toBe(1);
    });

    it('blocks a low-privilege user from listing permissions', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));

        $this->getJson('/api/v1/permissions')->assertForbidden();
        $this->getJson('/api/v1/permissions/grouped')->assertForbidden();
    });

    it('blocks a low-privilege user from creating or listing roles', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));

        $this->getJson('/api/v1/roles')->assertForbidden();
        $this->postJson('/api/v1/roles', ['name' => 'pwned', 'display_name' => 'Pwned'])
            ->assertForbidden();

        expect(Role::where('name', 'pwned')->exists())->toBeFalse();
    });

    it('still allows a user with users.manage_roles to sync permissions', function () {
        $user = lowPrivilegeUser(['users.manage_roles']);
        $target = Role::create(['name' => 'target', 'display_name' => 'Target']);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/roles/{$target->id}/sync-permissions", [
            'permissions' => Permission::where('name', 'invoices.view')->pluck('id')->all(),
        ])->assertSuccessful();

        expect($target->fresh()->permissions()->count())->toBe(1);
    });
});

describe('inventory movement authorization', function () {
    it('blocks a low-privilege user from adjusting stock', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));

        $product = Product::factory()->create(['track_inventory' => true]);
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'new_quantity' => 999999,
            'notes' => 'covering shrinkage',
        ])->assertForbidden();
    });

    it('blocks a low-privilege user from stock-in, stock-out and transfer', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));

        $this->postJson('/api/v1/inventory/stock-in', [])->assertForbidden();
        $this->postJson('/api/v1/inventory/stock-out', [])->assertForbidden();
        $this->postJson('/api/v1/inventory/transfer', [])->assertForbidden();
    });

    it('blocks a low-privilege user from reading inventory valuation', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));

        $this->getJson('/api/v1/inventory/valuation')->assertForbidden();
        $this->getJson('/api/v1/inventory/movements')->assertForbidden();
    });

    it('still allows a user holding inventory.adjust to adjust stock', function () {
        Sanctum::actingAs(lowPrivilegeUser(['inventory.adjust']));

        $product = Product::factory()->create(['track_inventory' => true]);
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'new_quantity' => 10,
            'notes' => 'legitimate correction',
        ])->assertSuccessful();
    });
});

describe('warehouse authorization', function () {
    it('blocks a low-privilege user from creating or deleting warehouses', function () {
        Sanctum::actingAs(lowPrivilegeUser(['pos.sale.checkout']));
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/api/v1/warehouses', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertForbidden();
    });
});

describe('admin still has full access', function () {
    it('lets an admin reach roles, permissions and inventory', function () {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/roles')->assertSuccessful();
        $this->getJson('/api/v1/permissions')->assertSuccessful();
        $this->getJson('/api/v1/inventory/valuation')->assertSuccessful();
        $this->getJson('/api/v1/warehouses')->assertSuccessful();
    });
});
