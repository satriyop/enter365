<?php

declare(strict_types=1);

/**
 * Phase 3: Generate Now on a due recurring invoice template creates an invoice.
 */
beforeEach(fn () => skipUnlessLiveFeature('recurring'));

it('generates an invoice from a due recurring template', function () {
    $db = realDb();
    $customer = ensureBrowserTestCustomer();
    $adminId = $db->table('users')->where('email', 'admin@example.com')->value('id');
    $suffix = substr((string) time(), -6);
    $name = "E2E Recurring {$suffix}";

    $templateId = (int) $db->table('recurring_templates')->insertGetId([
        'name' => $name,
        'type' => 'invoice',
        'contact_id' => $customer->id,
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => null,
        'next_generate_date' => now()->subDay()->toDateString(),
        'occurrences_limit' => null,
        'occurrences_count' => 0,
        'description' => 'Phase 3 generate',
        'reference' => "E2E-REC-{$suffix}",
        'tax_rate' => 11.00,
        'discount_amount' => 0,
        'early_discount_percent' => 0,
        'early_discount_days' => 0,
        'payment_term_days' => 30,
        'currency' => 'IDR',
        'items' => json_encode([
            [
                'description' => 'Monthly retainer',
                'quantity' => 1,
                'unit' => 'month',
                'unit_price' => 1_000_000,
            ],
        ]),
        'is_active' => true,
        'auto_post' => false,
        'auto_send' => false,
        'created_by' => $adminId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = (int) $db->table('invoices')->where('recurring_template_id', $templateId)->count();

    $page = loginAndVisit("/accounting/recurring-templates/{$templateId}");
    $page->assertSee($name)
        ->assertSee('Generate Now')
        ->click('Generate Now')
        ->assertSee('Invoice generated');

    $invoiceId = 0;
    for ($i = 0; $i < 40; $i++) {
        $invoiceId = (int) $db->table('invoices')->where('recurring_template_id', $templateId)->value('id');
        if ($invoiceId > 0) {
            break;
        }
        usleep(250_000);
    }

    expect($invoiceId)->toBeGreaterThan(0)
        ->and((int) $db->table('invoices')->where('recurring_template_id', $templateId)->count())
        ->toBe($before + 1);

    $invoice = $db->table('invoices')->where('id', $invoiceId)->first();
    expect((int) $invoice->total_amount)->toBeGreaterThan(0);
});
