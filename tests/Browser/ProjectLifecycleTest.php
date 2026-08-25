<?php

declare(strict_types=1);

/**
 * Phase 4: start a planning project and add a cost line through the SPA.
 */
beforeEach(fn () => skipUnlessLiveFeature('projects'));

it('starts a project and records a material cost', function () {
    $db = realDb();
    $customer = ensureBrowserTestCustomer();
    $adminId = $db->table('users')->where('email', 'admin@example.com')->value('id');
    $suffix = substr((string) time(), -6);
    $name = "E2E Project {$suffix}";
    $number = 'PRJ-E2E-'.$suffix;

    $projectId = (int) $db->table('projects')->insertGetId([
        'project_number' => $number,
        'name' => $name,
        'description' => 'Phase 4 lifecycle',
        'contact_id' => $customer->id,
        'status' => 'planning',
        'budget_amount' => 10_000_000,
        'contract_amount' => 12_000_000,
        'total_cost' => 0,
        'total_revenue' => 0,
        'gross_profit' => 0,
        'profit_margin' => 0,
        'progress_percentage' => 0,
        'priority' => 'normal',
        'created_by' => $adminId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page = loginAndVisit("/projects/{$projectId}");
    $page->assertSee($name)
        ->assertSee($number)
        ->script('window.confirm = () => true');

    $page->click('Start Project');
    for ($i = 0; $i < 40; $i++) {
        if ($db->table('projects')->where('id', $projectId)->value('status') === 'in_progress') {
            break;
        }
        usleep(250_000);
    }
    expect($db->table('projects')->where('id', $projectId)->value('status'))->toBe('in_progress');

    $page = loginAndVisit("/projects/{$projectId}");
    $page->assertSee('No costs recorded yet.');
    $page->click('button >> text=Add Cost');
    usleep(400_000);
    $page->assertSee('Unit Cost');
    $page->fill('input[placeholder="Enter description"]', 'E2E cable');
    $page->fill('input[placeholder="e.g., pcs, kg"]', 'meter');
    $page->fill('input[type="number"][step="0.01"]', '2');
    $page->fill('input[type="number"][step="1"]', '50000');
    $page->click('button[form="cost-form"]');
    $page->assertSee('Cost added');

    $cost = null;
    for ($i = 0; $i < 40; $i++) {
        $cost = $db->table('project_costs')->where('project_id', $projectId)->first();
        if ($cost) {
            break;
        }
        usleep(250_000);
    }

    expect($cost)->not->toBeNull()
        ->and((int) $cost->total_cost)->toBe(100_000)
        ->and((int) $db->table('projects')->where('id', $projectId)->value('total_cost'))
        ->toBe(100_000);

    $page = loginAndVisit("/projects/{$projectId}");
    $page->assertSee('E2E cable')
        ->assertSee('100.000')
        ->assertNoJavascriptErrors();
});
