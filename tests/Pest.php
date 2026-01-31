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

uses(Tests\TestCase::class)->in('Feature', 'Unit', 'Contract');

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
