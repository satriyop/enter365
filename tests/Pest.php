<?php

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Core\Role;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit', 'Contract', 'Browser');

/*
|--------------------------------------------------------------------------
| Browser Test Configuration
|--------------------------------------------------------------------------
|
| Pest v4 browser tests use Playwright under the hood. This configures
| the default timeout for browser interactions (waiting for elements, etc).
|
*/

pest()->browser()->timeout(10000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create and authenticate an admin user for API tests.
 */
function authenticatedAdmin(): User
{
    $user = User::factory()->create();

    $role = Role::firstOrCreate(
        ['name' => Role::ADMIN],
        ['display_name' => 'Administrator', 'description' => 'Full system access', 'is_system' => true]
    );
    $user->roles()->attach($role);

    Sanctum::actingAs($user);

    return $user;
}

/**
 * Authenticate a Kasir (role cashier). Requires RolesAndPermissionsSeeder.
 */
function authenticatedCashier(): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('name', Role::CASHIER)->firstOrFail();
    $user->roles()->attach($role);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Authenticate an Akuntan. Requires RolesAndPermissionsSeeder.
 */
function authenticatedAccountant(): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('name', Role::ACCOUNTANT)->firstOrFail();
    $user->roles()->attach($role);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Authenticate Inventori staff. Requires RolesAndPermissionsSeeder.
 */
function authenticatedInventoryClerk(): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('name', Role::INVENTORY)->firstOrFail();
    $user->roles()->attach($role);
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Create and authenticate a regular user for API tests.
 */
function authenticatedUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/**
 * Configure feature flags for testing.
 *
 * @param  array<string, bool>  $features  Feature name => enabled status
 */
function withFeatures(array $features): void
{
    foreach ($features as $feature => $enabled) {
        config(["features.modules.{$feature}" => $enabled]);
    }
}

/**
 * Disable specific features for testing.
 *
 * @param  array<int, string>  $features  Feature names to disable
 */
function withoutFeatures(array $features): void
{
    foreach ($features as $feature) {
        config(["features.modules.{$feature}" => false]);
    }
}

/**
 * Apply feature modules matching a FEATURE_PRESET key (config/features.php packs).
 */
function applyFeaturePreset(string $preset): void
{
    $preset = strtolower($preset);
    if ($preset === 'nex') {
        $preset = 'solar';
    }

    config(['features.preset' => $preset]);

    $packs = match ($preset) {
        'full' => [
            'manufacturing' => true,
            'bom' => true,
            'work_orders' => true,
            'material_requisitions' => true,
            'mrp' => true,
            'subcontracting' => true,
            'projects' => true,
            'solar_proposals' => true,
            'electrical_panel' => true,
            'pos' => true,
        ],
        'enterprise' => [
            'manufacturing' => true,
            'bom' => true,
            'work_orders' => true,
            'material_requisitions' => true,
            'mrp' => true,
            'subcontracting' => true,
            'projects' => true,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'pos' => false,
        ],
        'manufacturing' => [
            'manufacturing' => true,
            'bom' => true,
            'work_orders' => true,
            'material_requisitions' => true,
            'mrp' => true,
            'subcontracting' => true,
            'projects' => false,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'pos' => false,
        ],
        'vahana' => [
            'manufacturing' => true,
            'bom' => true,
            'work_orders' => true,
            'material_requisitions' => true,
            'mrp' => true,
            'subcontracting' => true,
            'projects' => false,
            'solar_proposals' => false,
            'electrical_panel' => true,
            'pos' => false,
        ],
        'services' => [
            'manufacturing' => false,
            'bom' => false,
            'work_orders' => false,
            'material_requisitions' => false,
            'mrp' => false,
            'subcontracting' => false,
            'projects' => true,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'pos' => false,
        ],
        'pos' => [
            'manufacturing' => false,
            'bom' => false,
            'work_orders' => false,
            'material_requisitions' => false,
            'mrp' => false,
            'subcontracting' => false,
            'projects' => false,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'pos' => true,
            'quotations' => false,
            'invoices' => false,
            'payments' => false,
            'delivery_orders' => false,
            'sales_returns' => false,
            'down_payments' => false,
            'purchase_orders' => false,
            'goods_receipt_notes' => false,
            'purchase_returns' => false,
            'budgeting' => false,
            'recurring' => false,
            'multi_currency' => false,
            'bank_reconciliation' => false,
        ],
        'solar' => [
            'manufacturing' => false,
            'bom' => true,
            'work_orders' => false,
            'material_requisitions' => false,
            'mrp' => false,
            'subcontracting' => false,
            'projects' => true,
            'solar_proposals' => true,
            'electrical_panel' => false,
            'pos' => false,
        ],
        default => [ // general
            'manufacturing' => false,
            'bom' => false,
            'work_orders' => false,
            'material_requisitions' => false,
            'mrp' => false,
            'subcontracting' => false,
            'projects' => false,
            'solar_proposals' => false,
            'electrical_panel' => false,
            'pos' => false,
        ],
    };

    withFeatures($packs);
}

/**
 * Foundation seeders that seed:demo --fresh runs before DemoSeeder.
 *
 * @param  \PHPUnit\Framework\TestCase  $test
 */
function seedDemoFoundation($test): void
{
    $test->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $test->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
    $test->seed(\Database\Seeders\FiscalPeriodSeeder::class);
    $test->seed(\Database\Seeders\IndonesiaSolarDataSeeder::class);
    $test->seed(\Database\Seeders\PlnTariffSeeder::class);
}

/**
 * Run DemoSeeder for a profile via the same entry used by seed:demo (demo.choice).
 *
 * @param  \PHPUnit\Framework\TestCase  $test
 */
function seedDemoProfile($test, string $profile): void
{
    app()->instance('demo.choice', $profile);
    $test->seed(\Database\Seeders\Demo\DemoSeeder::class);
}

/**
 * Panel meta test helpers live under tests/Support/Addons/ (industry add-on tests only).
 */

/*
|--------------------------------------------------------------------------
| Browser Test Helpers
|--------------------------------------------------------------------------
|
| Shared helpers for Pest v4 browser tests. These functions are used by
| all tests in tests/Browser/ to interact with the SPA.
|
*/

/**
 * Build a full SPA URL from a relative path.
 */
function spaUrl(string $path = ''): string
{
    return env('SPA_URL', 'http://localhost:3000').$path;
}

/**
 * Live API feature flags (enter365.test), not phpunit.xml FEATURE_PRESET=full.
 *
 * @return array<string, bool>
 */
function liveApiModules(): array
{
    static $modules = null;
    if (is_array($modules)) {
        return $modules;
    }

    $base = rtrim((string) env('API_URL', 'https://enter365.test'), '/');

    try {
        $login = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
            ->acceptJson()
            ->post($base.'/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'password',
            ]);
        if (! $login->successful()) {
            $modules = [];

            return $modules;
        }

        $features = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
            ->acceptJson()
            ->withToken((string) $login->json('token'))
            ->get($base.'/api/v1/features');

        $modules = (array) ($features->json('data.modules') ?? []);
    } catch (\Throwable) {
        $modules = [];
    }

    return $modules;
}

function skipUnlessLiveFeature(string $module): void
{
    if (! (liveApiModules()[$module] ?? false)) {
        test()->markTestSkipped("Live API feature '{$module}' is off for this tenant.");
    }
}

function loginAndVisitAs(string $email, string $path = '/', string $password = 'password')
{
    ensureOpenCurrentFiscalPeriod();

    $page = visit(spaUrl('/login'));
    $page->fill('[data-testid="login-email"]', $email)
        ->fill('[data-testid="login-password"]', $password)
        ->click('[data-testid="login-submit"]')
        ->assertMissing('[data-testid="login-email"]');

    if ($path !== '/' && $path !== '') {
        return $page->navigate(spaUrl($path));
    }

    return $page;
}

/**
 * Login to the SPA and navigate to a given path.
 */
function loginAndVisit(string $path = '/')
{
    // Keep live browser DB postable across suites that lock fiscal periods.
    ensureOpenCurrentFiscalPeriod();

    $page = visit(spaUrl('/login'));

    $page->fill('[data-testid="login-email"]', 'admin@example.com')
        ->fill('[data-testid="login-password"]', 'password')
        ->click('[data-testid="login-submit"]')
        ->assertPathIs('/');

    return $page->navigate(spaUrl($path));
}

/**
 * Reload the current page. Needed because the SPA doesn't auto-refresh
 * after workflow actions (submit, approve, post, etc).
 */
function reloadPage($page)
{
    $currentUrl = $page->url();
    $page->navigate($currentUrl);

    return $page;
}

/**
 * Get a database connection to the real PostgreSQL used by the API server.
 * phpunit.xml overrides DB_DATABASE to :memory: for sqlite, so we configure
 * a separate connection with the real database name.
 */
function realDb(): \Illuminate\Database\ConnectionInterface
{
    if (! config()->has('database.connections.browser_pgsql')) {
        config(['database.connections.browser_pgsql' => array_merge(
            config('database.connections.pgsql'),
            ['database' => env('BROWSER_DB_DATABASE', 'akuntansi')]
        )]);
    }

    return \Illuminate\Support\Facades\DB::connection('browser_pgsql');
}

/**
 * Canonical browser E2E customer name (created if missing).
 */
function browserTestCustomerName(): string
{
    return 'PT Test Customer';
}

/**
 * Canonical browser E2E supplier name (created if missing).
 */
function browserTestSupplierName(): string
{
    return 'PT Test Supplier';
}

/**
 * Ensure the SPA combobox customer exists in the live browser DB.
 *
 * @return object{id: int, name: string, type: string}
 */
function ensureBrowserTestCustomer(): object
{
    $db = realDb();
    $name = browserTestCustomerName();

    $existing = $db->table('contacts')
        ->where('name', $name)
        ->whereNull('deleted_at')
        ->first();

    if ($existing) {
        if (! $existing->is_active) {
            $db->table('contacts')->where('id', $existing->id)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
            $existing->is_active = true;
        }

        return $existing;
    }

    $id = (int) $db->table('contacts')->insertGetId([
        'code' => 'E2E-CUST',
        'name' => $name,
        'type' => 'customer',
        'email' => 'e2e-customer@example.com',
        'phone' => '08123456789',
        'address' => 'Jakarta',
        'city' => 'Jakarta',
        'is_active' => true,
        'credit_limit' => 0,
        'payment_term_days' => 30,
        'currency' => 'IDR',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $db->table('contacts')->where('id', $id)->first();
}

/**
 * Ensure a supplier contact exists for bill/PO browser flows.
 *
 * @return object{id: int, name: string, type: string}
 */
function ensureBrowserTestSupplier(): object
{
    $db = realDb();
    $name = browserTestSupplierName();

    $existing = $db->table('contacts')
        ->where('name', $name)
        ->whereNull('deleted_at')
        ->first();

    if ($existing) {
        if (! $existing->is_active) {
            $db->table('contacts')->where('id', $existing->id)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        return $db->table('contacts')->where('id', $existing->id)->first();
    }

    // Prefer an existing real supplier if present
    $supplier = $db->table('contacts')
        ->whereIn('type', ['supplier', 'both'])
        ->where('is_active', true)
        ->whereNull('deleted_at')
        ->orderBy('id')
        ->first();

    if ($supplier) {
        return $supplier;
    }

    $id = (int) $db->table('contacts')->insertGetId([
        'code' => 'E2E-SUP',
        'name' => $name,
        'type' => 'supplier',
        'email' => 'e2e-supplier@example.com',
        'phone' => '08129876543',
        'address' => 'Jakarta',
        'city' => 'Jakarta',
        'is_active' => true,
        'credit_limit' => 0,
        'payment_term_days' => 30,
        'currency' => 'IDR',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $db->table('contacts')->where('id', $id)->first();
}

/**
 * Resolve a CoA account id by code from the browser DB (IDs drift across seeds).
 */
function browserAccountIdByCode(string $code): int
{
    $id = realDb()->table('accounts')->where('code', $code)->value('id');

    if (! $id) {
        throw new RuntimeException("Browser DB missing account code {$code}");
    }

    return (int) $id;
}

/**
 * Bank / cash account used by payment & DP browser tests (1-1010 Bank BCA).
 */
function browserCashAccountId(): int
{
    return browserAccountIdByCode('1-1010');
}

/**
 * Ensure fiscal periods are open/unlocked so posting is not blocked by leftover
 * browser test state (FiscalPeriodTest can leave locks behind on the live DB).
 *
 * Uses the `status` column (open|locked|closing|closed) — not only legacy booleans.
 */
function ensureOpenCurrentFiscalPeriod(): void
{
    $db = realDb();

    $db->table('fiscal_periods')
        ->where('start_date', '<=', now()->toDateString())
        ->where('end_date', '>=', now()->toDateString())
        ->update([
            'status' => 'open',
            'is_closed' => false,
            'is_locked' => false,
            'closed_at' => null,
            'closed_by' => null,
            'updated_at' => now(),
        ]);

    // Clear stray locked/closing rows that still look "active" via legacy flags.
    $db->table('fiscal_periods')
        ->whereIn('status', ['locked', 'closing'])
        ->update([
            'status' => 'open',
            'is_locked' => false,
            'is_closed' => false,
            'updated_at' => now(),
        ]);
}

/**
 * First available product id in the browser DB.
 */
function browserProductId(): int
{
    $id = realDb()->table('products')->whereNull('deleted_at')->orderBy('id')->value('id');

    if (! $id) {
        throw new RuntimeException('Browser DB has no products');
    }

    return (int) $id;
}

/**
 * Create an invoice via the SPA form and return the page on the detail view.
 */
function createInvoice(string $description = 'E2E Test Item', int $qty = 10, string $price = '100000')
{
    ensureOpenCurrentFiscalPeriod();
    $customer = ensureBrowserTestCustomer();
    $page = loginAndVisit('/invoices/new');

    $page->assertSee('New Invoice');

    // Select customer — Radix-Vue Select
    $page->click('[data-testid="invoice-customer"]');
    $page->click('[role="option"] >> text="'.$customer->name.'"');

    // Fill line item description
    $page->fill('[data-testid="invoice-item-0-description"]', $description);

    // Fill quantity
    $page->fill('[data-testid="invoice-item-0-quantity"]', (string) $qty);

    // Fill unit price
    $page->click('[data-testid="invoice-item-0-price"]');
    $page->type('[data-testid="invoice-item-0-price"]', $price);

    // Submit the form
    $page->click('[data-testid="invoice-submit"]');

    // Wait for navigation to detail page
    $page->assertSee('INV-');

    return $page;
}

/**
 * Post an invoice from its detail page and reload.
 */
function postInvoice($page): void
{
    ensureOpenCurrentFiscalPeriod();
    $page->click('Post Invoice');
    $page->assertSee('posted successfully');

    // Reload to get updated status (SPA may not auto-refresh)
    reloadPage($page);
}

/**
 * Get the invoice ID from the current detail page URL.
 */
function getInvoiceIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/\/invoices\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Domain Test Helpers
|--------------------------------------------------------------------------
|
| These helpers create common domain objects for testing.
|
*/

/**
 * Create a draft invoice with items.
 */
function createDraftInvoiceWithItems(int $itemCount = 2): Invoice
{
    return Invoice::factory()
        ->has(InvoiceItem::factory()->count($itemCount))
        ->draft()
        ->create();
}

/**
 * Create a draft invoice without items.
 */
function createDraftInvoice(): Invoice
{
    return Invoice::factory()->draft()->create();
}

/**
 * Create a sent invoice with items.
 */
function createSentInvoice(int $itemCount = 1): Invoice
{
    $invoice = Invoice::factory()->sent()->create();

    // Create items that match the invoice subtotal
    $lineTotal = (int) ($invoice->subtotal / $itemCount);
    for ($i = 0; $i < $itemCount; $i++) {
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'line_total' => $lineTotal,
        ]);
    }

    return $invoice->fresh(['items']);
}

/**
 * Create a customer contact.
 */
function createCustomer(): Contact
{
    return Contact::factory()->customer()->create();
}

/**
 * Create a vendor contact.
 */
function createVendor(): Contact
{
    return Contact::factory()->vendor()->create();
}

/**
 * Create a draft work order with items.
 */
function createDraftWorkOrderWithItems(int $itemCount = 2): WorkOrder
{
    return WorkOrder::factory()
        ->has(WorkOrderItem::factory()->count($itemCount))
        ->create(['status' => DocumentStatus::Draft]);
}

/**
 * Create a confirmed work order.
 */
function createConfirmedWorkOrder(): WorkOrder
{
    return WorkOrder::factory()
        ->has(WorkOrderItem::factory())
        ->create(['status' => DocumentStatus::Confirmed]);
}

/**
 * Ensure a product has inventory tracking enabled and stock in a warehouse.
 *
 * @return array{product_id: int, warehouse_id: int}
 */
function ensureInventorySetup(): array
{
    $db = realDb();
    $productId = browserProductId();

    // Get or create a warehouse
    $warehouse = $db->table('warehouses')->where('is_active', true)->first();
    if (! $warehouse) {
        $warehouseId = (int) $db->table('warehouses')->insertGetId([
            'code' => 'WH-INV-TEST',
            'name' => 'Inventory Test Warehouse',
            'is_active' => true,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        $warehouseId = (int) $warehouse->id;
    }

    $db->table('products')->where('id', $productId)->update(['track_inventory' => true]);

    $stock = $db->table('product_stocks')
        ->where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();

    if (! $stock) {
        $db->table('product_stocks')->insert([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return ['product_id' => $productId, 'warehouse_id' => $warehouseId];
}
