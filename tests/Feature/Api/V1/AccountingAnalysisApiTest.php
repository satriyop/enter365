<?php

use App\Contracts\Accounting\JournalServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\Journal;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    authenticatedAdmin();
});

describe('Accounting analysis reports (#152)', function () {
    it('lists monthly tax returns from posted invoices and bills', function () {
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-10',
            'subtotal' => 10_000_000,
            'tax_amount' => 1_100_000,
            'tax_rate' => 11,
            'total_amount' => 11_100_000,
        ]);
        Bill::factory()->received()->create([
            'bill_date' => '2026-03-12',
            'subtotal' => 5_000_000,
            'tax_amount' => 550_000,
            'tax_rate' => 11,
            'total_amount' => 5_550_000,
        ]);
        Invoice::factory()->create([
            'status' => DocumentStatus::Draft,
            'invoice_date' => '2026-03-11',
            'tax_amount' => 999_000,
        ]);

        $response = $this->getJson('/api/v1/reports/tax-returns?year=2026')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Tax Returns')
            ->assertJsonPath('data.year', 2026);

        $march = collect($response->json('data.rows'))->firstWhere('period', '2026-03');

        expect($march['status'])->toBe('to_file')
            ->and($march['output_tax'])->toBe(1_100_000)
            ->and($march['input_tax'])->toBe(550_000)
            ->and($march['net_tax'])->toBe(550_000)
            ->and($response->json('data.totals.output_tax'))->toBe(1_100_000);
    });

    it('reviews unrealized FX on open foreign invoices', function () {
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-01',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-01',
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'total_amount' => 50_000,
        ]);

        $response = $this->getJson('/api/v1/reports/review/unrealized-currencies?as_of_date=2026-03-31&rates[USD]=16000')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Unrealized Currencies')
            ->assertJsonPath('data.totals.count', 1);

        expect($response->json('data.rows.0.currency'))->toBe('USD')
            ->and($response->json('data.rows.0.outstanding'))->toBe(1000)
            ->and($response->json('data.rows.0.unrealized_fx'))->toBe(1_000_000)
            ->and($response->json('data.totals.total_gain'))->toBe(1_000_000);
    });

    it('groups posted invoices for invoice analysis and ignores drafts', function () {
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-05',
            'subtotal' => 100_000,
            'tax_amount' => 11_000,
            'total_amount' => 111_000,
            'paid_amount' => 0,
        ]);
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-20',
            'subtotal' => 200_000,
            'tax_amount' => 22_000,
            'total_amount' => 222_000,
            'paid_amount' => 50_000,
        ]);
        Invoice::factory()->create([
            'status' => DocumentStatus::Draft,
            'invoice_date' => '2026-03-08',
            'total_amount' => 999_000,
        ]);

        $response = $this->getJson('/api/v1/reports/invoice-analysis?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Invoice Analysis')
            ->assertJsonPath('data.group_by', 'month')
            ->assertJsonPath('data.totals.count', 2)
            ->assertJsonPath('data.totals.total_amount', 333_000)
            ->assertJsonPath('data.totals.outstanding', 283_000);

        expect($response->json('data.rows.0.group'))->toBe('2026-03');
    });

    it('summarises analytic distributions as an analytic report', function () {
        $analyticA = AnalyticAccount::factory()->create(['code' => 'MKT']);
        $analyticB = AnalyticAccount::factory()->create(['code' => 'OPS']);
        $cash = Account::query()->where('code', '1-1001')->firstOrFail();
        $expense = Account::query()->where('code', '5-2100')->firstOrFail();
        $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();

        app(JournalServiceInterface::class)->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => '2026-03-15',
            'description' => 'Analytic split',
            'lines' => [
                [
                    'account_id' => $expense->id,
                    'analytic_distribution' => [
                        (string) $analyticA->id => 60,
                        (string) $analyticB->id => 40,
                    ],
                    'debit' => 100_000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => 100_000,
                ],
            ],
        ], true);

        $response = $this->getJson('/api/v1/reports/analytic-report?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Analytic Report');

        $rows = collect($response->json('data.rows'));
        expect($rows)->toHaveCount(2)
            ->and($rows->firstWhere('code', 'MKT')['expense'])->toBe(60_000)
            ->and($rows->firstWhere('code', 'OPS')['expense'])->toBe(40_000)
            ->and($response->json('data.totals.expense'))->toBe(100_000);
    });

    it('builds an executive summary from posted invoices and bills', function () {
        Invoice::factory()->sent()->create([
            'invoice_date' => '2026-03-04',
            'total_amount' => 200_000,
            'tax_amount' => 20_000,
            'paid_amount' => 0,
        ]);
        Bill::factory()->received()->create([
            'bill_date' => '2026-03-06',
            'total_amount' => 80_000,
            'tax_amount' => 8_000,
            'paid_amount' => 0,
        ]);

        $this->getJson('/api/v1/reports/executive-summary?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Executive Summary')
            ->assertJsonPath('data.kpis.sales_total', 200_000)
            ->assertJsonPath('data.kpis.purchase_total', 80_000)
            ->assertJsonPath('data.kpis.net_operating', 120_000)
            ->assertJsonPath('data.kpis.receivable_outstanding', 200_000)
            ->assertJsonPath('data.kpis.payable_outstanding', 80_000);
    });

    it('lists company budgets versus actuals', function () {
        $period = FiscalPeriod::query()
            ->whereDate('start_date', '<=', '2026-03-31')
            ->whereDate('end_date', '>=', '2026-03-31')
            ->first() ?? FiscalPeriod::factory()->create([
                'name' => 'FY 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ]);
        $revenue = Account::query()->where('code', '4-1001')->firstOrFail();
        $budget = Budget::factory()->create([
            'name' => 'FY 2026',
            'fiscal_period_id' => $period->id,
            'total_revenue' => 1_000_000,
            'total_expense' => 400_000,
            'net_budget' => 600_000,
        ]);
        BudgetLine::factory()->forBudget($budget)->forAccount($revenue)->withAnnualAmount(1_000_000)->create();

        $response = $this->getJson('/api/v1/reports/budget-report?as_of_date=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.report_name', 'Budget Report')
            ->assertJsonPath('data.rows.0.name', 'FY 2026')
            ->assertJsonPath('data.rows.0.budgeted_revenue', 1_000_000)
            ->assertJsonPath('data.totals.budgeted_revenue', 1_000_000);
    });
});
