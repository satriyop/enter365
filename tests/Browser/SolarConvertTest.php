<?php

declare(strict_types=1);

/**
 * Phase 4: solar wizard loads; accepted proposal with selected BOM converts to quotation.
 */
beforeEach(fn () => skipUnlessLiveFeature('solar_proposals'));

it('opens the solar proposal wizard', function () {
    $page = loginAndVisit('/solar-proposals/new');
    $page->assertSee('Site Info')
        ->assertSee('Customer & location details')
        ->assertNoJavascriptErrors();
});

it('converts an accepted solar proposal to a quotation', function () {
    $db = realDb();
    $customer = ensureBrowserTestCustomer();
    $adminId = (int) $db->table('users')->where('email', 'admin@example.com')->value('id');
    $suffix = substr((string) time(), -6);

    $fgProductId = (int) ($db->table('products')->whereNull('deleted_at')->orderBy('id')->value('id'));
    expect($fgProductId)->toBeGreaterThan(0);

    $bomId = (int) $db->table('boms')->insertGetId([
        'bom_number' => 'BOM-E2E-SOL-'.$suffix,
        'name' => 'E2E Solar System '.$suffix,
        'product_id' => $fgProductId,
        'output_quantity' => 1,
        'output_unit' => 'system',
        'total_material_cost' => 40_000_000,
        'total_labor_cost' => 5_000_000,
        'total_overhead_cost' => 5_000_000,
        'total_cost' => 50_000_000,
        'unit_cost' => 50_000_000,
        'status' => 'active',
        'version' => '1.0',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $proposalNumber = 'SPR-E2E-'.$suffix;
    $proposalId = (int) $db->table('solar_proposals')->insertGetId([
        'proposal_number' => $proposalNumber,
        'contact_id' => $customer->id,
        'status' => 'accepted',
        'site_name' => 'E2E Rooftop '.$suffix,
        'site_address' => 'Jakarta',
        'province' => 'DKI Jakarta',
        'city' => 'Jakarta Selatan',
        'latitude' => -6.2615,
        'longitude' => 106.8106,
        'roof_area_m2' => 80,
        'roof_type' => 'sloped',
        'roof_orientation' => 'north',
        'roof_tilt_degrees' => 10,
        'shading_percentage' => 0,
        'monthly_consumption_kwh' => 800,
        'pln_tariff_category' => 'R-1/TR',
        'electricity_rate' => 1444,
        'tariff_escalation_percent' => 3,
        'peak_sun_hours' => 4.5,
        'solar_irradiance' => 4.8,
        'performance_ratio' => 0.80,
        'selected_bom_id' => $bomId,
        'system_capacity_kwp' => 5,
        'annual_production_kwh' => 6500,
        'valid_until' => now()->addDays(30)->toDateString(),
        'created_by' => $adminId,
        'sent_at' => now()->subDay(),
        'accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page = loginAndVisit("/solar-proposals/{$proposalId}");
    $page->assertSee($proposalNumber)
        ->assertSee('Convert to Quotation')
        ->click('Convert to Quotation')
        ->assertSee('Quotation created successfully');

    $quotationId = 0;
    for ($i = 0; $i < 40; $i++) {
        $quotationId = (int) $db->table('solar_proposals')->where('id', $proposalId)->value('converted_quotation_id');
        if ($quotationId > 0) {
            break;
        }
        usleep(250_000);
    }

    expect($quotationId)->toBeGreaterThan(0);
    $quotation = $db->table('quotations')->where('id', $quotationId)->first();
    expect($quotation)->not->toBeNull()
        ->and((int) $quotation->contact_id)->toBe((int) $customer->id)
        ->and((int) $quotation->total_amount)->toBeGreaterThan(0);
});
