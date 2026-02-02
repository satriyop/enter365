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
 * Login to the SPA and navigate to a given path.
 */
function loginAndVisit(string $path = '/')
{
    $page = visit(spaUrl('/login'));

    $page->fill('input[type="email"]', 'admin@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
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
 * Create an invoice via the SPA form and return the page on the detail view.
 */
function createInvoice(string $description = 'E2E Test Item', int $qty = 10, string $price = '100000')
{
    $page = loginAndVisit('/invoices/new');

    $page->assertSee('New Invoice');

    // Select customer — Radix-Vue Select
    $page->click('Select customer...');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill line item description
    $page->fill('input[placeholder="Item description"]', $description);

    // Fill quantity
    $page->fill('table input[type="number"][min="1"]', (string) $qty);

    // Fill unit price (CurrencyInput in the table row, not the discount field below)
    $page->click('td input[inputmode="numeric"]');
    $page->type('td input[inputmode="numeric"]', $price);

    // Submit the form
    $page->click('Create Invoice');

    // Wait for navigation to detail page
    $page->assertSee('INV-');

    return $page;
}

/**
 * Post an invoice from its detail page and reload.
 */
function postInvoice($page): void
{
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
