<?php

declare(strict_types=1);

/**
 * Multi-Currency E2E Browser Tests.
 *
 * Tests cover currency selection, exchange rate visibility, contact auto-fill,
 * and IDR-only behavior across all transactional forms (Bill, Invoice,
 * Quotation, Purchase Order).
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded suppliers and customers (at least one each)
 * - SPA running at SPA_URL (default: http://localhost:3000)
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

if (! function_exists('getTestSupplierName')) {
    function getTestSupplierName(): string
    {
        $name = realDb()->table('contacts')
            ->where('type', 'supplier')
            ->orderBy('id')
            ->value('name');

        return $name ?: 'Rohan-Predovic';
    }
}

/**
 * Get the first customer's ID and name for exact option targeting.
 *
 * @return array{id: int, name: string}
 */
function getTestCustomer(): array
{
    $customer = realDb()->table('contacts')
        ->where('type', 'customer')
        ->orderBy('id')
        ->select('id', 'name')
        ->first();

    return [
        'id' => (int) ($customer?->id ?? 4),
        'name' => $customer?->name ?? 'PT Test Customer',
    ];
}

/**
 * Ensure a USD supplier exists in the database.
 * Returns the supplier name.
 */
function ensureUsdSupplier(): string
{
    $db = realDb();
    $existing = $db->table('contacts')
        ->where('type', 'supplier')
        ->where('currency', 'USD')
        ->value('name');

    if ($existing) {
        return $existing;
    }

    $code = 'SUP-USD-'.now()->format('His');
    $db->table('contacts')->insert([
        'code' => $code,
        'name' => 'USD Test Supplier',
        'type' => 'supplier',
        'email' => 'usd-supplier@test.com',
        'currency' => 'USD',
        'is_active' => true,
        'payment_term_days' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return 'USD Test Supplier';
}

/**
 * Ensure a USD customer exists in the database.
 * Returns array with id and name for exact option targeting.
 *
 * @return array{id: int, name: string}
 */
function ensureUsdCustomer(): array
{
    $db = realDb();
    $existing = $db->table('contacts')
        ->where('type', 'customer')
        ->where('currency', 'USD')
        ->select('id', 'name')
        ->first();

    if ($existing) {
        return ['id' => (int) $existing->id, 'name' => $existing->name];
    }

    $code = 'CUS-USD-'.now()->format('His');
    $id = (int) $db->table('contacts')->insertGetId([
        'code' => $code,
        'name' => 'USD Test Customer',
        'type' => 'customer',
        'email' => 'usd-customer@test.com',
        'currency' => 'USD',
        'is_active' => true,
        'payment_term_days' => 30,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['id' => $id, 'name' => 'USD Test Customer'];
}

/**
 * Get an entity ID from the current detail page URL.
 */
function getEntityIdFromUrl($page, string $entityPath): int
{
    $url = $page->url();
    preg_match("/\/{$entityPath}\/(\d+)/", $url, $matches);

    return (int) ($matches[1] ?? 0);
}

// ---------------------------------------------------------------------------
// Test 1: Bill Form — Currency Selection & Exchange Rate
// ---------------------------------------------------------------------------

it('can create a bill with foreign currency and exchange rate', function () {
    $supplierName = getTestSupplierName();
    $page = loginAndVisit('/bills/new');

    $page->assertSee('New Bill');

    // Select vendor
    $page->click('[data-testid="bill-vendor"]');
    $page->click('[role="option"] >> text='.$supplierName);

    // Verify currency defaults to IDR
    $page->assertSee('IDR - Rupiah');

    // Exchange rate field should NOT exist in DOM for IDR (v-if removes it)
    $page->assertDontSee('Exchange Rate');

    // Change currency to USD
    $page->click('[data-testid="bill-currency"]');
    $page->click('[data-testid="bill-currency-option-USD"]');

    // Exchange rate field should now be visible
    $page->assertVisible('[data-testid="bill-exchange-rate"]');

    // Enter exchange rate
    $page->fill('[data-testid="bill-exchange-rate"]', '15500');

    // Add line item
    $page->fill('[data-testid="bill-item-0-description"]', 'Imported Component USD');
    $page->fill('[data-testid="bill-item-0-quantity"]', '10');
    $page->click('[data-testid="bill-item-0-price"]');
    $page->type('[data-testid="bill-item-0-price"]', '100');

    // Submit
    $page->click('[data-testid="bill-submit"]');

    // Wait for detail page
    $page->assertSee('BL-');

    // Verify detail page shows currency and base currency total
    $page->assertVisible('[data-testid="bill-currency-display"]');
    $page->assertSee('USD');
    $page->assertSee('15500');
    $page->assertVisible('[data-testid="bill-base-currency-total"]');

    // DB assertions
    $billId = getEntityIdFromUrl($page, 'bills');
    expect($billId)->toBeGreaterThan(0);

    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->currency)->toBe('USD');
    expect((float) $bill->exchange_rate)->toBe(15500.0);
    expect((int) $bill->base_currency_total)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Test 2: Auto-fill Currency from Contact (Bill)
// ---------------------------------------------------------------------------

it('auto-fills currency when selecting a USD vendor on bill form', function () {
    $usdSupplierName = ensureUsdSupplier();
    $page = loginAndVisit('/bills/new');

    $page->assertSee('New Bill');

    // Select the USD vendor
    $page->click('[data-testid="bill-vendor"]');
    $page->click('[role="option"] >> text='.$usdSupplierName);

    // Currency should auto-fill to USD
    $page->assertSee('USD - US Dollar');

    // Exchange rate field should appear automatically
    $page->assertVisible('[data-testid="bill-exchange-rate"]');
});

// ---------------------------------------------------------------------------
// Test 3: Invoice Form — Currency Selection & Exchange Rate
// ---------------------------------------------------------------------------

it('can create an invoice with foreign currency and exchange rate', function () {
    $customer = getTestCustomer();
    $page = loginAndVisit('/invoices/new');

    $page->assertSee('New Invoice');

    // Select customer using exact data-testid option to avoid ambiguous matches
    $page->click('[data-testid="invoice-customer"]');
    $page->click('[data-testid="invoice-customer-option-'.$customer['id'].'"]');

    // Verify defaults to IDR, exchange rate hidden
    $page->assertSee('IDR - Rupiah');
    $page->assertDontSee('Exchange Rate');

    // Change to USD
    $page->click('[data-testid="invoice-currency"]');
    $page->click('[data-testid="invoice-currency-option-USD"]');

    // Exchange rate should appear
    $page->assertVisible('[data-testid="invoice-exchange-rate"]');
    $page->fill('[data-testid="invoice-exchange-rate"]', '15500');

    // Add line item
    $page->fill('[data-testid="invoice-item-0-description"]', 'Export Service USD');
    $page->fill('[data-testid="invoice-item-0-quantity"]', '5');
    $page->click('[data-testid="invoice-item-0-price"]');
    $page->type('[data-testid="invoice-item-0-price"]', '200');

    // Submit
    $page->click('[data-testid="invoice-submit"]');

    // Wait for detail page
    $page->assertSee('INV-');

    // Verify detail page shows currency and base currency total
    $page->assertVisible('[data-testid="invoice-currency-display"]');
    $page->assertSee('USD');
    $page->assertSee('15500');
    $page->assertVisible('[data-testid="invoice-base-currency-total"]');

    // DB assertions
    $invoiceId = getEntityIdFromUrl($page, 'invoices');
    expect($invoiceId)->toBeGreaterThan(0);

    $invoice = realDb()->table('invoices')->where('id', $invoiceId)->first();
    expect($invoice->currency)->toBe('USD');
    expect((float) $invoice->exchange_rate)->toBe(15500.0);
    expect((int) $invoice->base_currency_total)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Test 4: Quotation Form — Currency Selection & Exchange Rate
// ---------------------------------------------------------------------------

it('can create a quotation with foreign currency and exchange rate', function () {
    $customer = getTestCustomer();
    $page = loginAndVisit('/quotations/new');

    $page->assertSee('New Quotation');

    // Select customer using exact data-testid option
    $page->click('[data-testid="quotation-customer"]');
    $page->click('[data-testid="quotation-customer-option-'.$customer['id'].'"]');

    // Fill subject (required field)
    $page->fill('[data-testid="quotation-subject"]', 'Multi-Currency EUR Test');

    // Verify defaults to IDR, exchange rate hidden
    $page->assertSee('IDR - Rupiah');
    $page->assertDontSee('Exchange Rate');

    // Change to EUR
    $page->click('[data-testid="quotation-currency"]');
    $page->click('[data-testid="quotation-currency-option-EUR"]');

    // Exchange rate should appear
    $page->assertVisible('[data-testid="quotation-exchange-rate"]');
    $page->fill('[data-testid="quotation-exchange-rate"]', '17200');

    // Add line item — select product then set quantity
    $page->select('[data-testid="quotation-item-0-product"]', '1');
    $page->fill('[data-testid="quotation-item-0-quantity"]', '3');

    // Submit
    $page->click('[data-testid="quotation-submit"]');

    // Wait for detail page
    $page->assertSee('QUO-');

    // Verify detail page shows currency and base currency total
    $page->assertVisible('[data-testid="quotation-currency-display"]');
    $page->assertSee('EUR');
    $page->assertSee('17200');
    $page->assertVisible('[data-testid="quotation-base-currency-total"]');

    // DB assertions
    $quotationId = getEntityIdFromUrl($page, 'quotations');
    expect($quotationId)->toBeGreaterThan(0);

    $quotation = realDb()->table('quotations')->where('id', $quotationId)->first();
    expect($quotation->currency)->toBe('EUR');
    expect((float) $quotation->exchange_rate)->toBe(17200.0);
    expect((int) $quotation->base_currency_total)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Test 5: Purchase Order Form — Currency Selection & Exchange Rate
// ---------------------------------------------------------------------------

it('can create a purchase order with foreign currency and exchange rate', function () {
    $supplierName = getTestSupplierName();
    $page = loginAndVisit('/purchasing/purchase-orders/new');

    $page->assertSee('New Purchase Order');

    // Select vendor
    $page->click('[data-testid="po-vendor"]');
    $page->click('[role="option"] >> text='.$supplierName);

    // Verify defaults to IDR, exchange rate hidden
    $page->assertSee('IDR - Rupiah');
    $page->assertDontSee('Exchange Rate');

    // Change to SGD
    $page->click('[data-testid="po-currency"]');
    $page->click('[data-testid="po-currency-option-SGD"]');

    // Exchange rate should appear
    $page->assertVisible('[data-testid="po-exchange-rate"]');
    $page->fill('[data-testid="po-exchange-rate"]', '11800');

    // Add line item
    $page->fill('[data-testid="po-item-0-description"]', 'Singapore Parts SGD');
    $page->fill('[data-testid="po-item-0-quantity"]', '20');
    $page->click('[data-testid="po-item-0-price"]');
    $page->type('[data-testid="po-item-0-price"]', '50');

    // Submit
    $page->click('[data-testid="po-submit"]');

    // Wait for success and detail page
    $page->assertSee('created successfully');
    $page->assertSee('PO-');

    // Verify detail page shows currency and base currency total
    $page->assertVisible('[data-testid="po-currency-display"]');
    $page->assertSee('SGD');
    $page->assertSee('11800');
    $page->assertVisible('[data-testid="po-base-currency-total"]');

    // DB assertions
    $poId = getEntityIdFromUrl($page, 'purchase-orders');
    expect($poId)->toBeGreaterThan(0);

    $po = realDb()->table('purchase_orders')->where('id', $poId)->first();
    expect($po->currency)->toBe('SGD');
    expect((float) $po->exchange_rate)->toBe(11800.0);
    expect((int) $po->base_currency_total)->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Test 6: Auto-fill Currency from Contact (Invoice)
// ---------------------------------------------------------------------------

it('auto-fills currency when selecting a USD customer on invoice form', function () {
    $usdCustomer = ensureUsdCustomer();
    $page = loginAndVisit('/invoices/new');

    $page->assertSee('New Invoice');

    // Select the USD customer using exact data-testid option
    $page->click('[data-testid="invoice-customer"]');
    $page->click('[data-testid="invoice-customer-option-'.$usdCustomer['id'].'"]');

    // Currency should auto-fill to USD
    $page->assertSee('USD - US Dollar');

    // Exchange rate field should appear automatically
    $page->assertVisible('[data-testid="invoice-exchange-rate"]');
});

// ---------------------------------------------------------------------------
// Test 7: Edit Mode Preserves Currency
// ---------------------------------------------------------------------------

it('preserves currency and exchange rate in bill edit mode', function () {
    // First, create a USD bill via DB for speed
    $db = realDb();
    $userId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
    $contactId = (int) $db->table('contacts')->where('type', 'supplier')->orderBy('id')->value('id');

    $prefix = 'BL-'.now()->format('Ym').'-';
    $lastNumber = $db->table('bills')
        ->where('bill_number', 'like', $prefix.'%')
        ->orderByDesc('bill_number')
        ->value('bill_number');
    $seq = $lastNumber ? (int) substr($lastNumber, strlen($prefix)) + 1 : 1;
    $billNumber = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

    $billId = (int) $db->table('bills')->insertGetId([
        'bill_number' => $billNumber,
        'contact_id' => $contactId,
        'bill_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'description' => 'USD Edit Test',
        'subtotal' => 1000,
        'tax_amount' => 0,
        'tax_rate' => 0,
        'discount_amount' => 0,
        'total_amount' => 1000,
        'paid_amount' => 0,
        'status' => 'draft',
        'currency' => 'USD',
        'exchange_rate' => 15500,
        'base_currency_total' => 15500000,
        'payable_account_id' => 1024,
        'created_by' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $db->table('bill_items')->insert([
        'bill_id' => $billId,
        'description' => 'USD Test Item',
        'quantity' => 10,
        'unit' => 'pcs',
        'unit_price' => 100,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'line_total' => 1000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Navigate to edit page
    $page = loginAndVisit("/bills/{$billId}/edit");

    // Verify currency is pre-populated to USD
    $page->assertSee('USD - US Dollar');

    // Exchange rate field should be visible and pre-filled
    $page->assertVisible('[data-testid="bill-exchange-rate"]');

    // Update and save — verify values persist
    $page->click('[data-testid="bill-submit"]');

    // Wait for detail page
    $page->assertSee($billNumber);

    // Verify currency still shows on detail
    $page->assertVisible('[data-testid="bill-currency-display"]');
    $page->assertSee('USD');
    $page->assertSee('15500');

    // DB assertion
    $bill = $db->table('bills')->where('id', $billId)->first();
    expect($bill->currency)->toBe('USD');
    expect((float) $bill->exchange_rate)->toBe(15500.0);
});

// ---------------------------------------------------------------------------
// Test 8: IDR-only (No Exchange Rate)
// ---------------------------------------------------------------------------

it('hides exchange rate field and base currency row when currency is IDR', function () {
    $supplierName = getTestSupplierName();
    $page = loginAndVisit('/bills/new');

    $page->assertSee('New Bill');

    // Select vendor (IDR vendor)
    $page->click('[data-testid="bill-vendor"]');
    $page->click('[role="option"] >> text='.$supplierName);

    // Currency should be IDR
    $page->assertSee('IDR - Rupiah');

    // Exchange rate field should NOT exist (v-if removes from DOM)
    $page->assertDontSee('Exchange Rate');

    // Create the bill with IDR
    $page->fill('[data-testid="bill-item-0-description"]', 'IDR Only Item');
    $page->fill('[data-testid="bill-item-0-quantity"]', '5');
    $page->click('[data-testid="bill-item-0-price"]');
    $page->type('[data-testid="bill-item-0-price"]', '100000');

    // Submit
    $page->click('[data-testid="bill-submit"]');

    // Wait for detail page
    $page->assertSee('BL-');

    // On the detail page, base currency row should NOT appear (v-if removes it)
    $page->assertDontSee('Base Currency');

    // DB assertion
    $billId = getEntityIdFromUrl($page, 'bills');
    $bill = realDb()->table('bills')->where('id', $billId)->first();
    expect($bill->currency)->toBe('IDR');
    expect((float) $bill->exchange_rate)->toBe(1.0);
});

// ---------------------------------------------------------------------------
// Test 9: Base Currency Total Calculation Accuracy
// ---------------------------------------------------------------------------

it('calculates base currency total correctly for a USD bill', function () {
    $supplierName = getTestSupplierName();
    $page = loginAndVisit('/bills/new');

    $page->assertSee('New Bill');

    // Select vendor
    $page->click('[data-testid="bill-vendor"]');
    $page->click('[role="option"] >> text='.$supplierName);

    // Set currency to USD with rate 16000
    $page->click('[data-testid="bill-currency"]');
    $page->click('[data-testid="bill-currency-option-USD"]');
    $page->fill('[data-testid="bill-exchange-rate"]', '16000');

    // Add line item: 10 x $100 = $1,000 USD
    $page->fill('[data-testid="bill-item-0-description"]', 'Base Currency Calc Test');
    $page->fill('[data-testid="bill-item-0-quantity"]', '10');
    $page->click('[data-testid="bill-item-0-price"]');
    $page->type('[data-testid="bill-item-0-price"]', '100');

    // Submit
    $page->click('[data-testid="bill-submit"]');
    $page->assertSee('BL-');

    // DB assertion: base_currency_total = total_amount * exchange_rate
    $billId = getEntityIdFromUrl($page, 'bills');
    $bill = realDb()->table('bills')->where('id', $billId)->first();

    $expectedBaseCurrency = (int) $bill->total_amount * (int) $bill->exchange_rate;
    expect((int) $bill->base_currency_total)->toBe($expectedBaseCurrency);
    expect($bill->currency)->toBe('USD');
});
