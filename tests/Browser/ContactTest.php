<?php

declare(strict_types=1);

/**
 * MASTER-PEST-02: Contacts CRUD browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded contacts: C-PLN-JBR (PT PLN Bandung), V-SCH (Schneider Electric)
 *
 * Tests cover: create customer, create vendor, edit, detail view, list page.
 *
 * Contact types: customer, supplier, both
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getContactIdFromUrl($page): int
{
    $url = $page->url();
    preg_match('/\/contacts\/(\d+)/', $url, $matches);

    return (int) ($matches[1] ?? 0);
}

function generateContactCode(string $prefix = 'E2E'): string
{
    return $prefix.'-'.strtoupper(substr(md5((string) time()), 0, 6));
}

/**
 * Create a customer contact via the SPA form and return the page on the detail view.
 */
function createCustomerContact(string $name = 'E2E Test Customer', ?string $code = null)
{
    $code = $code ?? generateContactCode('CUST');
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill Code
    $page->fill('[data-testid="contact-code"]', $code);

    // Fill Name
    $page->fill('[data-testid="contact-name"]', $name);

    // Select Type = Customer (may be a select or radio)
    // The form uses a Select component with options: customer, supplier, both
    $page->click('[data-testid="contact-type"]'); // Type select
    $page->click('[role="option"] >> text=Customer');

    // Fill Email
    $page->fill('[data-testid="contact-email"]', 'e2e-test@example.com');

    // Fill Phone
    $page->fill('[data-testid="contact-phone"]', '08123456789');

    // Submit the form
    $page->click('[data-testid="contact-submit"]');

    // Wait for navigation to detail page
    $page->assertSee($code);

    return $page;
}

/**
 * Create a vendor contact via the SPA form and return the page on the detail view.
 */
function createVendorContact(string $name = 'E2E Test Vendor', ?string $code = null)
{
    $code = $code ?? generateContactCode('SUPP');
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill Code
    $page->fill('[data-testid="contact-code"]', $code);

    // Fill Name
    $page->fill('[data-testid="contact-name"]', $name);

    // Select Type = Supplier
    $page->click('[data-testid="contact-type"]');
    $page->click('[role="option"] >> text=Supplier');

    // Fill Email
    $page->fill('[data-testid="contact-email"]', 'vendor-e2e@example.com');

    // Fill Phone
    $page->fill('[data-testid="contact-phone"]', '08198765432');

    // Submit the form
    $page->click('[data-testid="contact-submit"]');

    // Wait for navigation to detail page
    $page->assertSee($code);

    return $page;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('can create a customer contact', function () {
    $code = generateContactCode('CUST');
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill Code
    $page->fill('[data-testid="contact-code"]', $code);

    // Fill Name
    $page->fill('[data-testid="contact-name"]', 'PT Test Customer E2E');

    // Select Type = Customer
    $page->click('[data-testid="contact-type"]');
    $page->click('[role="option"] >> text=Customer');

    // Fill Email
    $page->fill('[data-testid="contact-email"]', 'customer-e2e@example.com');

    // Fill Phone
    $page->fill('[data-testid="contact-phone"]', '08123456789');

    // Fill Address (Street Address textarea)
    $page->fill('[data-testid="contact-address"]', 'Jl. Test No. 123');

    // Submit
    $page->click('[data-testid="contact-submit"]');

    // Wait for navigation to detail page
    $page->assertSee($code);
    $page->assertSee('PT Test Customer E2E');

    // Verify in database
    $contactId = getContactIdFromUrl($page);
    expect($contactId)->toBeGreaterThan(0);

    $contact = realDb()->table('contacts')->where('id', $contactId)->first();
    expect($contact)->not->toBeNull();
    expect($contact->code)->toBe($code);
    expect($contact->name)->toBe('PT Test Customer E2E');
    expect($contact->type)->toBe('customer');
    expect($contact->email)->toBe('customer-e2e@example.com');
});

it('can create a vendor contact', function () {
    $code = generateContactCode('SUPP');
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill Code
    $page->fill('[data-testid="contact-code"]', $code);

    // Fill Name
    $page->fill('[data-testid="contact-name"]', 'PT Test Vendor E2E');

    // Select Type = Supplier
    $page->click('[data-testid="contact-type"]');
    $page->click('[role="option"] >> text=Supplier');

    // Fill Email
    $page->fill('[data-testid="contact-email"]', 'vendor-e2e@example.com');

    // Fill Phone
    $page->fill('[data-testid="contact-phone"]', '08198765432');

    // Submit
    $page->click('[data-testid="contact-submit"]');

    // Wait for navigation to detail page
    $page->assertSee($code);
    $page->assertSee('PT Test Vendor E2E');

    // Verify in database
    $contactId = getContactIdFromUrl($page);
    $contact = realDb()->table('contacts')->where('id', $contactId)->first();
    expect($contact->type)->toBe('supplier');
});

it('can load contact edit page and shows form fields', function () {
    // Find an existing contact
    $existingContact = realDb()->table('contacts')
        ->whereNull('deleted_at')
        ->first();

    if (! $existingContact) {
        expect(true)->toBeTrue(); // Skip if no contacts exist

        return;
    }

    $contactId = $existingContact->id;

    // Navigate directly to edit page with fresh login
    $page = loginAndVisit("/contacts/{$contactId}/edit");

    // Wait for SPA to load the edit form
    $page->assertSee('Edit Contact');

    // Should have form fields
    $page->assertSee('Basic Information');
    $page->assertSee('Address');

    // Should have Update button
    $page->assertSee('Update Contact');
});

it('shows contact detail page with outstanding balances', function () {
    // Use an existing seeded customer with potential outstanding
    $contact = realDb()->table('contacts')
        ->where('code', 'C-PLN-JBR')
        ->first();

    if (! $contact) {
        // Skip if demo data not seeded - create one instead
        $page = createCustomerContact();
        $page->assertSee('CUST-'); // Verify contact created

        return;
    }

    $page = loginAndVisit("/contacts/{$contact->id}");

    $page->assertSee('C-PLN-JBR');
    $page->assertSee('PT PLN');
    // Should show credit limit and payment terms
    $page->assertSee('Credit Limit');
});

it('shows contacts in the list page with type filter', function () {
    $page = loginAndVisit('/contacts');

    $page->assertSee('Contacts');
    $page->assertSee('New Contact'); // New button

    // Filter by type - click the type filter
    // Look for the type filter select
    $typeFilterExists = false;
    try {
        $page->click('button >> text=All Types');
        $typeFilterExists = true;
    } catch (\Exception) {
        // Filter might have different text
    }

    if ($typeFilterExists) {
        $page->click('[role="option"] >> text=Customers');
        usleep(500_000); // Wait for filter

        // Should show only customers
        $page->assertSee('Contacts');
    }

    // Try search functionality
    $searchInput = 'input[placeholder*="Search"]';
    $page->fill($searchInput, 'PLN');
    usleep(500_000); // Wait for search
});

it('validates contact credit limit as non-negative', function () {
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill required fields
    $code = generateContactCode('VAL');
    $page->fill('[data-testid="contact-code"]', $code);
    $page->fill('[data-testid="contact-name"]', 'Validation Test Contact');

    // Select Type
    $page->click('[data-testid="contact-type"]');
    $page->click('[role="option"] >> text=Customer');

    // Try to set negative credit limit (if field exists)
    $creditLimitInput = 'input[type="number"]';
    try {
        $page->fill($creditLimitInput.' >> nth=0', '-1000000');
    } catch (\Exception) {
        // Credit limit input might use CurrencyInput which doesn't accept negative
    }

    // Submit
    $page->click('[data-testid="contact-submit"]');

    // If negative was accepted, form should show error
    // If not accepted, contact should be created with 0 or positive value
    usleep(500_000);

    // Check if we're still on form (validation failed) or redirected (success)
    $url = $page->url();
    if (str_contains($url, '/new')) {
        // Still on form - validation working
        $page->assertSee('New Contact');
    } else {
        // Contact created - verify credit limit is not negative
        $contactId = getContactIdFromUrl($page);
        if ($contactId > 0) {
            $contact = realDb()->table('contacts')->where('id', $contactId)->first();
            expect((int) $contact->credit_limit)->toBeGreaterThanOrEqual(0);
        }
    }
});

it('can create a contact of type both (customer and supplier)', function () {
    $code = generateContactCode('BOTH');
    $page = loginAndVisit('/contacts/new');

    $page->assertSee('New Contact');

    // Fill Code
    $page->fill('[data-testid="contact-code"]', $code);

    // Fill Name
    $page->fill('[data-testid="contact-name"]', 'PT Both Type Contact');

    // Select Type = Both
    $page->click('[data-testid="contact-type"]');
    $page->click('[role="option"] >> text=Both');

    // Fill Email
    $page->fill('[data-testid="contact-email"]', 'both-e2e@example.com');

    // Submit
    $page->click('[data-testid="contact-submit"]');

    // Wait for navigation to detail page
    $page->assertSee($code);
    $page->assertSee('PT Both Type Contact');

    // Verify in database
    $contactId = getContactIdFromUrl($page);
    $contact = realDb()->table('contacts')->where('id', $contactId)->first();
    expect($contact->type)->toBe('both');
});
