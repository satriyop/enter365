<?php

declare(strict_types=1);

/**
 * RPT-PEST-01: Financial reports browser tests.
 *
 * Prerequisites:
 * - Seeded user: admin@example.com / password
 * - Seeded accounts with posted journal entries
 *
 * Tests cover: trial balance, balance sheet, income statement page loads
 * and structural DB verification.
 *
 * Shared helpers (realDb, loginAndVisit, spaUrl, etc.) are in tests/Pest.php.
 */

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('trial balance page loads and shows balanced totals', function () {
    $page = loginAndVisit('/reports/trial-balance');

    // Assert page title visible
    $page->assertSee('Trial Balance');
    $page->assertSee('Neraca Saldo');

    // The trial balance may show "No accounts found" if no JEs exist for
    // the as-of-date. Assert the report structure loads correctly.
    $page->assertSee('TOTAL');

    // DB verification: total debits = total credits across all posted JEs
    $trialBalance = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
        ->first();

    expect((int) $trialBalance->total_debit)->toBe((int) $trialBalance->total_credit);
});

it('balance sheet page loads and shows correct structure', function () {
    $page = loginAndVisit('/reports/balance-sheet');

    // Assert page title visible
    $page->assertSee('Balance Sheet');
    $page->assertSee('Laporan Posisi Keuangan');

    // Assert section headers visible
    $page->assertSee('ASSETS');
    $page->assertSee('LIABILITIES');
    $page->assertSee('EQUITY');

    // DB verification: Assets = Liabilities + Equity
    // Asset accounts (type=asset): debit balance = SUM(debit) - SUM(credit)
    // Liability accounts (type=liability): credit balance = SUM(credit) - SUM(debit)
    // Equity accounts (type=equity): credit balance = SUM(credit) - SUM(debit)
    $balances = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->join('accounts as a', 'a.id', '=', 'jel.account_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->selectRaw("
            COALESCE(SUM(CASE WHEN a.type = 'asset' THEN jel.debit - jel.credit ELSE 0 END), 0) as asset_balance,
            COALESCE(SUM(CASE WHEN a.type = 'liability' THEN jel.credit - jel.debit ELSE 0 END), 0) as liability_balance,
            COALESCE(SUM(CASE WHEN a.type = 'equity' THEN jel.credit - jel.debit ELSE 0 END), 0) as equity_balance,
            COALESCE(SUM(CASE WHEN a.type = 'revenue' THEN jel.credit - jel.debit ELSE 0 END), 0) as revenue_balance,
            COALESCE(SUM(CASE WHEN a.type = 'expense' THEN jel.debit - jel.credit ELSE 0 END), 0) as expense_balance
        ")
        ->first();

    // Net income = Revenue - Expenses (forms part of equity on balance sheet)
    $netIncome = (int) $balances->revenue_balance - (int) $balances->expense_balance;
    $liabEquity = (int) $balances->liability_balance + (int) $balances->equity_balance + $netIncome;

    expect((int) $balances->asset_balance)->toBe($liabEquity);
});

it('income statement page loads and shows correct structure', function () {
    $page = loginAndVisit('/reports/income-statement');

    // Assert page title visible
    $page->assertSee('Income Statement');
    $page->assertSee('Laporan Laba Rugi');

    // Assert sections visible
    $page->assertSee('Revenue');
    $page->assertSee('Expenses');

    // DB verification: revenue and expense accounts have entries from posted JEs
    $revenueCount = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->join('accounts as a', 'a.id', '=', 'jel.account_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->where('a.type', 'revenue')
        ->count();

    $expenseCount = realDb()->table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->join('accounts as a', 'a.id', '=', 'jel.account_id')
        ->where('je.is_posted', true)
        ->where('je.deleted_at', null)
        ->where('je.is_reversed', false)
        ->where('a.type', 'expense')
        ->count();

    // At least one of revenue or expense should have entries (from previous test data)
    expect($revenueCount + $expenseCount)->toBeGreaterThan(0);
});
