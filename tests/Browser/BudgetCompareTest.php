<?php

declare(strict_types=1);

/**
 * Phase 3: budget create is hybrid (seed live row); SPA must show vs-actual
 * with the budgeted amount. Comparison API is wrapped as data.comparison.
 */
beforeEach(fn () => skipUnlessLiveFeature('budgeting'));

it('shows budget vs actual with the seeded annual amount', function () {
    $db = realDb();
    $periodId = $db->table('fiscal_periods')
        ->whereDate('start_date', '<=', now()->toDateString())
        ->whereDate('end_date', '>=', now()->toDateString())
        ->value('id');
    expect($periodId)->not->toBeNull();

    $account = $db->table('accounts')->where('code', '5-1000')->first()
        ?? $db->table('accounts')->where('type', 'expense')->where('is_system', true)->orderBy('id')->first()
        ?? $db->table('accounts')->where('type', 'expense')->orderBy('id')->first();
    expect($account)->not->toBeNull();

    $annual = 12_345_000;
    $name = 'E2E Budget Compare '.substr((string) time(), -6);

    $budgetId = (int) $db->table('budgets')->insertGetId([
        'name' => $name,
        'description' => 'Phase 3 SPA comparison',
        'fiscal_period_id' => $periodId,
        'type' => 'annual',
        'status' => 'draft',
        'total_revenue' => 0,
        'total_expense' => $annual,
        'net_budget' => -$annual,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $month = (int) ($annual / 12);
    $db->table('budget_lines')->insert([
        'budget_id' => $budgetId,
        'account_id' => $account->id,
        'annual_amount' => $annual,
        'jan_amount' => $month,
        'feb_amount' => $month,
        'mar_amount' => $month,
        'apr_amount' => $month,
        'may_amount' => $month,
        'jun_amount' => $month,
        'jul_amount' => $month,
        'aug_amount' => $month,
        'sep_amount' => $month,
        'oct_amount' => $month,
        'nov_amount' => $month,
        'dec_amount' => $annual - ($month * 11),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page = loginAndVisit("/accounting/budgets/{$budgetId}");
    $page->assertSee($name)
        ->assertSee('Budget vs Actual')
        ->assertSee('12.345.000')
        ->assertSee($account->code)
        ->assertSee('TOTAL')
        ->assertNoJavascriptErrors();
});
