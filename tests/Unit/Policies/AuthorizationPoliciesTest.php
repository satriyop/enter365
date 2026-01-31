<?php

declare(strict_types=1);

use App\Models\Accounting\FiscalPeriod;
use App\Models\Core\Permission;
use App\Models\Core\Role;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Sales\SalesReturn;
use App\Models\User;
use App\Policies\FiscalPeriodPolicy;
use App\Policies\GoodsReceiptNotePolicy;
use App\Policies\MaterialRequisitionPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\SalesReturnPolicy;
use App\Policies\StockOpnamePolicy;
use App\Policies\SubcontractorWorkOrderPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: create a user with specific permissions.
 */
function userWithPermissions(array $permissions): User
{
    $user = User::factory()->create();
    $role = Role::create([
        'name' => 'test-role-'.uniqid(),
        'display_name' => 'Test Role',
        'description' => 'Test',
    ]);

    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(
            ['name' => $permName],
            ['display_name' => $permName, 'group' => explode('.', $permName)[0], 'description' => $permName]
        );
        $role->permissions()->attach($perm);
    }

    $user->roles()->attach($role);
    // Reload to ensure relationships are fresh
    $user->load('roles.permissions');

    return $user;
}

/**
 * Helper: create a user with no permissions.
 */
function userWithoutPermissions(): User
{
    return User::factory()->create();
}

// ─── SalesReturnPolicy ─────────────────────────────────────────────

describe('SalesReturnPolicy', function () {
    it('allows viewAny when user has sales_returns.view permission', function () {
        $user = userWithPermissions(['sales_returns.view']);
        $policy = new SalesReturnPolicy;

        expect($policy->viewAny($user))->toBeTrue();
    });

    it('denies viewAny when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new SalesReturnPolicy;

        expect($policy->viewAny($user))->toBeFalse();
    });

    it('allows create when user has sales_returns.create permission', function () {
        $user = userWithPermissions(['sales_returns.create']);
        $policy = new SalesReturnPolicy;

        expect($policy->create($user))->toBeTrue();
    });

    it('allows approve when user has sales_returns.approve permission', function () {
        $user = userWithPermissions(['sales_returns.approve']);
        $policy = new SalesReturnPolicy;
        $model = SalesReturn::factory()->make();

        expect($policy->approve($user, $model))->toBeTrue();
    });

    it('denies approve when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new SalesReturnPolicy;
        $model = SalesReturn::factory()->make();

        expect($policy->approve($user, $model))->toBeFalse();
    });
});

// ─── GoodsReceiptNotePolicy ────────────────────────────────────────

describe('GoodsReceiptNotePolicy', function () {
    it('allows viewAny when user has goods_receipt_notes.view permission', function () {
        $user = userWithPermissions(['goods_receipt_notes.view']);
        $policy = new GoodsReceiptNotePolicy;

        expect($policy->viewAny($user))->toBeTrue();
    });

    it('denies create when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new GoodsReceiptNotePolicy;

        expect($policy->create($user))->toBeFalse();
    });

    it('allows receive when user has goods_receipt_notes.receive permission', function () {
        $user = userWithPermissions(['goods_receipt_notes.receive']);
        $policy = new GoodsReceiptNotePolicy;
        $model = GoodsReceiptNote::factory()->make();

        expect($policy->receive($user, $model))->toBeTrue();
    });

    it('denies receive when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new GoodsReceiptNotePolicy;
        $model = GoodsReceiptNote::factory()->make();

        expect($policy->receive($user, $model))->toBeFalse();
    });
});

// ─── PurchaseReturnPolicy ──────────────────────────────────────────

describe('PurchaseReturnPolicy', function () {
    it('allows CRUD when user has all permissions', function () {
        $user = userWithPermissions([
            'purchase_returns.view',
            'purchase_returns.create',
            'purchase_returns.edit',
            'purchase_returns.delete',
        ]);
        $policy = new PurchaseReturnPolicy;
        $model = PurchaseReturn::factory()->make();

        expect($policy->viewAny($user))->toBeTrue()
            ->and($policy->create($user))->toBeTrue()
            ->and($policy->update($user, $model))->toBeTrue()
            ->and($policy->delete($user, $model))->toBeTrue();
    });

    it('allows approve when user has purchase_returns.approve', function () {
        $user = userWithPermissions(['purchase_returns.approve']);
        $policy = new PurchaseReturnPolicy;
        $model = PurchaseReturn::factory()->make();

        expect($policy->approve($user, $model))->toBeTrue();
    });

    it('denies all when user has no permissions', function () {
        $user = userWithoutPermissions();
        $policy = new PurchaseReturnPolicy;
        $model = PurchaseReturn::factory()->make();

        expect($policy->viewAny($user))->toBeFalse()
            ->and($policy->create($user))->toBeFalse()
            ->and($policy->update($user, $model))->toBeFalse()
            ->and($policy->delete($user, $model))->toBeFalse()
            ->and($policy->approve($user, $model))->toBeFalse();
    });
});

// ─── MaterialRequisitionPolicy ─────────────────────────────────────

describe('MaterialRequisitionPolicy', function () {
    it('allows create when user has material_requisitions.create', function () {
        $user = userWithPermissions(['material_requisitions.create']);
        $policy = new MaterialRequisitionPolicy;

        expect($policy->create($user))->toBeTrue();
    });

    it('allows approve when user has material_requisitions.approve', function () {
        $user = userWithPermissions(['material_requisitions.approve']);
        $policy = new MaterialRequisitionPolicy;
        $model = MaterialRequisition::factory()->make();

        expect($policy->approve($user, $model))->toBeTrue();
    });

    it('denies approve when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new MaterialRequisitionPolicy;
        $model = MaterialRequisition::factory()->make();

        expect($policy->approve($user, $model))->toBeFalse();
    });
});

// ─── SubcontractorWorkOrderPolicy ──────────────────────────────────

describe('SubcontractorWorkOrderPolicy', function () {
    it('allows CRUD when user has all permissions', function () {
        $user = userWithPermissions([
            'subcontractor_work_orders.view',
            'subcontractor_work_orders.create',
            'subcontractor_work_orders.edit',
            'subcontractor_work_orders.delete',
        ]);
        $policy = new SubcontractorWorkOrderPolicy;
        $model = SubcontractorWorkOrder::factory()->make();

        expect($policy->viewAny($user))->toBeTrue()
            ->and($policy->create($user))->toBeTrue()
            ->and($policy->update($user, $model))->toBeTrue()
            ->and($policy->delete($user, $model))->toBeTrue();
    });

    it('denies all when user has no permissions', function () {
        $user = userWithoutPermissions();
        $policy = new SubcontractorWorkOrderPolicy;
        $model = SubcontractorWorkOrder::factory()->make();

        expect($policy->viewAny($user))->toBeFalse()
            ->and($policy->create($user))->toBeFalse()
            ->and($policy->update($user, $model))->toBeFalse()
            ->and($policy->delete($user, $model))->toBeFalse();
    });
});

// ─── StockOpnamePolicy ─────────────────────────────────────────────

describe('StockOpnamePolicy', function () {
    it('allows approve when user has stock_opnames.approve', function () {
        $user = userWithPermissions(['stock_opnames.approve']);
        $policy = new StockOpnamePolicy;
        $model = StockOpname::factory()->make();

        expect($policy->approve($user, $model))->toBeTrue();
    });

    it('denies approve when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new StockOpnamePolicy;
        $model = StockOpname::factory()->make();

        expect($policy->approve($user, $model))->toBeFalse();
    });

    it('allows view when user has stock_opnames.view', function () {
        $user = userWithPermissions(['stock_opnames.view']);
        $policy = new StockOpnamePolicy;

        expect($policy->viewAny($user))->toBeTrue();
    });
});

// ─── FiscalPeriodPolicy ────────────────────────────────────────────

describe('FiscalPeriodPolicy', function () {
    it('allows close when user has fiscal_periods.close', function () {
        $user = userWithPermissions(['fiscal_periods.close']);
        $policy = new FiscalPeriodPolicy;
        $model = FiscalPeriod::factory()->make();

        expect($policy->close($user, $model))->toBeTrue();
    });

    it('denies close when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new FiscalPeriodPolicy;
        $model = FiscalPeriod::factory()->make();

        expect($policy->close($user, $model))->toBeFalse();
    });

    it('allows reopen when user has fiscal_periods.reopen', function () {
        $user = userWithPermissions(['fiscal_periods.reopen']);
        $policy = new FiscalPeriodPolicy;
        $model = FiscalPeriod::factory()->make();

        expect($policy->reopen($user, $model))->toBeTrue();
    });

    it('denies reopen when user lacks permission', function () {
        $user = userWithoutPermissions();
        $policy = new FiscalPeriodPolicy;
        $model = FiscalPeriod::factory()->make();

        expect($policy->reopen($user, $model))->toBeFalse();
    });

    it('allows CRUD when user has all permissions', function () {
        $user = userWithPermissions([
            'fiscal_periods.view',
            'fiscal_periods.create',
            'fiscal_periods.edit',
            'fiscal_periods.delete',
        ]);
        $policy = new FiscalPeriodPolicy;
        $model = FiscalPeriod::factory()->make();

        expect($policy->viewAny($user))->toBeTrue()
            ->and($policy->create($user))->toBeTrue()
            ->and($policy->update($user, $model))->toBeTrue()
            ->and($policy->delete($user, $model))->toBeTrue();
    });
});

// ─── WarehousePolicy ───────────────────────────────────────────────

describe('WarehousePolicy', function () {
    it('allows CRUD when user has all permissions', function () {
        $user = userWithPermissions([
            'warehouses.view',
            'warehouses.create',
            'warehouses.edit',
            'warehouses.delete',
        ]);
        $policy = new WarehousePolicy;
        $model = Warehouse::factory()->make();

        expect($policy->viewAny($user))->toBeTrue()
            ->and($policy->create($user))->toBeTrue()
            ->and($policy->update($user, $model))->toBeTrue()
            ->and($policy->delete($user, $model))->toBeTrue();
    });

    it('denies all when user has no permissions', function () {
        $user = userWithoutPermissions();
        $policy = new WarehousePolicy;
        $model = Warehouse::factory()->make();

        expect($policy->viewAny($user))->toBeFalse()
            ->and($policy->create($user))->toBeFalse()
            ->and($policy->update($user, $model))->toBeFalse()
            ->and($policy->delete($user, $model))->toBeFalse();
    });
});

// ─── Admin Bypass ──────────────────────────────────────────────────

describe('Admin bypass', function () {
    it('admin can access all new policies', function () {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['display_name' => 'Admin', 'description' => 'Admin', 'is_system' => true]
        );
        $admin->roles()->attach($role);
        $admin->load('roles.permissions');

        $policies = [
            new SalesReturnPolicy,
            new GoodsReceiptNotePolicy,
            new PurchaseReturnPolicy,
            new MaterialRequisitionPolicy,
            new SubcontractorWorkOrderPolicy,
            new StockOpnamePolicy,
            new FiscalPeriodPolicy,
            new WarehousePolicy,
        ];

        foreach ($policies as $policy) {
            expect($policy->viewAny($admin))->toBeTrue()
                ->and($policy->create($admin))->toBeTrue();
        }
    });
});

// ─── Permission Registration ───────────────────────────────────────

describe('Permission registration', function () {
    it('has all new permission groups in getDefaultPermissions', function () {
        $permissions = Permission::getDefaultPermissions();
        $names = array_column($permissions, 'name');

        // Sales Returns
        expect($names)->toContain('sales_returns.view')
            ->toContain('sales_returns.create')
            ->toContain('sales_returns.approve');

        // Goods Receipt Notes
        expect($names)->toContain('goods_receipt_notes.view')
            ->toContain('goods_receipt_notes.receive');

        // Purchase Returns
        expect($names)->toContain('purchase_returns.view')
            ->toContain('purchase_returns.approve');

        // Material Requisitions
        expect($names)->toContain('material_requisitions.view')
            ->toContain('material_requisitions.approve');

        // Subcontractor Work Orders
        expect($names)->toContain('subcontractor_work_orders.view')
            ->toContain('subcontractor_work_orders.create');

        // Stock Opnames
        expect($names)->toContain('stock_opnames.view')
            ->toContain('stock_opnames.approve');

        // Fiscal Periods
        expect($names)->toContain('fiscal_periods.view')
            ->toContain('fiscal_periods.close')
            ->toContain('fiscal_periods.reopen');

        // Warehouses
        expect($names)->toContain('warehouses.view')
            ->toContain('warehouses.create');
    });
});
