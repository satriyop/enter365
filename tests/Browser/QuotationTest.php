<?php

declare(strict_types=1);

/**
 * SALES-PEST-01: Quotation CRUD + workflow browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded customer: PT Test Customer
 * - Seeded product: MCB-16A-1P (MCB 16A 1 Phase)
 *
 * Note: After workflow actions (submit, approve, convert), the SPA does not
 * automatically refresh the detail page. A page reload is needed to see the
 * updated status.
 */

function spaUrl(string $path = ''): string
{
    return env('SPA_URL', 'http://localhost:3000').$path;
}

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
 * Helper: fill and submit the quotation form, returning the page on the detail view.
 */
function createQuotation(string $subject = 'E2E Test Quotation')
{
    $page = loginAndVisit('/quotations/new');

    $page->assertSee('New Quotation');

    // Select customer — open Radix-Vue combobox then pick option
    $page->click('Select customer...');
    $page->click('[role="option"] >> text=PT Test Customer');

    // Fill subject
    $page->fill('input[placeholder*="subject"]', $subject);

    // Fill first line item — select product by value (product ID).
    // Pest's guessLocator needs CSS special chars to use explicit mode;
    // 'td > select' targets the product select (parent=TD), not the discount select (parent=DIV).
    $page->select('td > select', '1');

    // Set quantity
    $page->fill('table input[type="number"][min="1"]', '10');

    // Submit the form
    $page->click('Create Quotation');

    // Wait for navigation to detail page — QUO- prefix appears in the quotation number
    $page->assertSee('QUO-');

    return $page;
}

/**
 * Helper: reload the current page to pick up status changes from the API.
 * The SPA doesn't auto-refetch after workflow actions.
 */
function reloadPage($page)
{
    $currentUrl = $page->url();
    $page->navigate($currentUrl);

    return $page;
}

it('can create a quotation with line items via SPA form', function () {
    $page = createQuotation('E2E Create Test');

    // Should be on the quotation detail page
    $page->assertSee('Draft')
        ->assertSee('PT Test Customer')
        ->assertSee('E2E Create Test')
        ->assertSee('MCB 16A 1 Phase')
        ->assertSee('10 pcs');
});

it('shows quotations in the list', function () {
    $page = loginAndVisit('/quotations');

    $page->assertSee('Quotations')
        ->assertSee('QUO-')
        ->assertSee('PT Test Customer');
});

it('can submit quotation for approval', function () {
    $page = createQuotation('Submit Test');
    $page->assertSee('Draft');

    // Submit for approval
    $page->click('Submit for Approval');
    $page->assertSee('submitted for approval'); // toast confirmation

    // Reload page to verify persisted status
    reloadPage($page);
    $page->assertSee('Submitted');
    $page->assertDontSee('Submit for Approval');
});

it('can approve a submitted quotation', function () {
    // Create and submit a quotation
    $page = createQuotation('Approve Test');
    $page->click('Submit for Approval');
    $page->assertSee('submitted for approval');

    // Reload to get updated status and buttons
    reloadPage($page);
    $page->assertSee('Submitted');

    // Approve
    $page->click('Approve');
    $page->assertSee('approved'); // toast confirmation

    // Reload to verify persisted status
    reloadPage($page);
    $page->assertSee('Approved');
});

it('can convert approved quotation to invoice', function () {
    // Create, submit, and approve a quotation
    $page = createQuotation('Convert Test');

    $page->click('Submit for Approval');
    $page->assertSee('submitted for approval');
    reloadPage($page);

    $page->click('Approve');
    $page->assertSee('approved');
    reloadPage($page);

    $page->assertSee('Approved');

    // Convert to invoice
    $page->click('Convert to Invoice');
    $page->assertSee('Invoice created successfully');
});
