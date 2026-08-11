<?php

declare(strict_types=1);

/**
 * Report API Response Shape Tests
 *
 * Hit every report endpoint and assert the response has the correct
 * top-level structure. This catches field name mismatches, envelope
 * issues, and missing keys early — without testing values.
 *
 * Run with: php artisan test --filter=ReportResponseShape
 */

use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

// ─── Financial Reports ───────────────────────────────────────────────

it('trial-balance has correct response shape', function () {
    $this->getJson('/api/v1/reports/trial-balance')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'as_of_date',
                'accounts',
                'total_debit',
                'total_credit',
                'is_balanced',
            ],
        ]);
});

it('balance-sheet has correct response shape', function () {
    $this->getJson('/api/v1/reports/balance-sheet')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'as_of_date',
                'assets',
                'liabilities',
                'equity',
                'total_assets',
                'total_liabilities',
                'total_equity',
                'total_liabilities_equity',
                'is_balanced',
            ],
        ]);
});

it('income-statement has correct response shape', function () {
    $this->getJson('/api/v1/reports/income-statement')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period_start',
                'period_end',
                'revenue',
                'expenses',
                'total_revenue',
                'total_expenses',
                'gross_profit',
                'operating_income',
                'net_income',
            ],
        ]);
});

it('general-ledger has correct response shape', function () {
    $this->getJson('/api/v1/reports/general-ledger')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'start_date',
                'end_date',
                'accounts',
            ],
        ]);
});

// ─── Aging Reports ───────────────────────────────────────────────────

it('receivable-aging has correct response shape', function () {
    $this->getJson('/api/v1/reports/receivable-aging')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'as_of_date',
                'buckets',
                'contacts',
                'totals',
            ],
        ]);
});

it('payable-aging has correct response shape', function () {
    $this->getJson('/api/v1/reports/payable-aging')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'as_of_date',
                'buckets',
                'contacts',
                'totals',
            ],
        ]);
});

it('contact-aging has correct response shape', function () {
    $contact = Contact::factory()->create();

    $this->getJson("/api/v1/reports/contacts/{$contact->id}/aging")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'contact',
                'as_of_date',
            ],
        ]);
});

// ─── Tax Reports ─────────────────────────────────────────────────────

it('ppn-summary has correct response shape', function () {
    $this->getJson('/api/v1/reports/ppn-summary')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'output_tax',
                'input_tax',
                'net_tax',
                'net_tax_status',
                'details',
            ],
        ]);
});

it('ppn-monthly has correct response shape', function () {
    $this->getJson('/api/v1/reports/ppn-monthly')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'year',
                'months',
                'total_output',
                'total_input',
                'total_net',
            ],
        ]);
});

it('tax-invoice-list has correct response shape', function () {
    $this->getJson('/api/v1/reports/tax-invoice-list')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'items',
                'total_dpp',
                'total_ppn',
                'total',
            ],
        ]);
});

it('input-tax-list has correct response shape', function () {
    $this->getJson('/api/v1/reports/input-tax-list')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'items',
                'total_dpp',
                'total_ppn',
                'total',
            ],
        ]);
});

// ─── Cash Flow Reports ───────────────────────────────────────────────

it('cash-flow has correct response shape', function () {
    $this->getJson('/api/v1/reports/cash-flow')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'operating_activities',
                'investing_activities',
                'financing_activities',
                'net_cash_change',
                'opening_balance',
                'closing_balance',
            ],
        ]);
});

it('daily-cash-movement has correct response shape', function () {
    $this->getJson('/api/v1/reports/daily-cash-movement')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'movements',
                'total_receipts',
                'total_payments',
                'net_movement',
            ],
        ]);
});

// ─── Project Reports ─────────────────────────────────────────────────

it('project-profitability has correct response shape', function () {
    $this->getJson('/api/v1/reports/project-profitability')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'projects',
                'totals',
            ],
        ]);
});

it('project-profitability-detail has correct response shape', function () {
    $project = Project::factory()->create();

    $this->getJson("/api/v1/reports/projects/{$project->id}/profitability")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'project',
                'financials',
                'cost_breakdown',
                'revenue_breakdown',
            ],
        ]);
});

it('project-cost-analysis has correct response shape', function () {
    $this->getJson('/api/v1/reports/project-cost-analysis')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'by_type',
                'by_project',
                'totals',
            ],
        ]);
});

// ─── Work Order Reports ──────────────────────────────────────────────

it('work-order-costs has correct response shape', function () {
    $this->getJson('/api/v1/reports/work-order-costs')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'work_orders',
                'totals',
            ],
        ]);
});

it('work-order-cost-detail has correct response shape', function () {
    $workOrder = WorkOrder::factory()->create();

    $this->getJson("/api/v1/reports/work-orders/{$workOrder->id}/costs")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'work_order',
                'cost_summary',
            ],
        ]);
});

it('cost-variance has correct response shape', function () {
    $this->getJson('/api/v1/reports/cost-variance')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'over_budget',
                'under_budget',
                'on_budget',
                'summary',
            ],
        ]);
});

// ─── Subcontractor Reports ───────────────────────────────────────────

it('subcontractor-summary has correct response shape', function () {
    $this->getJson('/api/v1/reports/subcontractor-summary')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'subcontractors',
                'totals',
            ],
        ]);
});

it('subcontractor-detail has correct response shape', function () {
    $contact = Contact::factory()->create();

    $this->getJson("/api/v1/reports/subcontractors/{$contact->id}/summary")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'subcontractor',
                'period',
                'work_orders',
                'summary',
            ],
        ]);
});

it('subcontractor-retention has correct response shape', function () {
    $this->getJson('/api/v1/reports/subcontractor-retention')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'retentions',
                'by_subcontractor',
                'totals',
            ],
        ]);
});

// ─── Equity Reports ──────────────────────────────────────────────────

it('changes-in-equity has correct response shape', function () {
    $this->getJson('/api/v1/reports/changes-in-equity')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'opening_equity',
                'total_opening_equity',
                'changes',
                'closing_equity',
                'total_closing_equity',
            ],
        ]);
});

// ─── Bank Reconciliation Reports ─────────────────────────────────────

it('bank-reconciliation has correct response shape', function () {
    // Cash/bank accounts use current_asset subtype (e.g. Kas 1-1001 from COA seeder)
    $account = Account::where('code', '1-1001')->first()
        ?? Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Kas',
        ]);

    $this->getJson("/api/v1/reports/accounts/{$account->id}/bank-reconciliation")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'account',
                'as_of_date',
                'book_balance',
                'bank_balance',
                'adjusted_book_balance',
                'adjusted_bank_balance',
                'is_reconciled',
            ],
        ]);
});

it('bank-reconciliation-outstanding has correct response shape', function () {
    $account = Account::where('code', '1-1001')->first()
        ?? Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'code' => '1-1001',
            'name' => 'Kas',
        ]);

    $this->getJson("/api/v1/reports/accounts/{$account->id}/bank-reconciliation/outstanding")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'account',
                'as_of_date',
                'outstanding_deposits',
                'outstanding_checks',
            ],
        ]);
});

// ─── COGS Reports ────────────────────────────────────────────────────

it('cogs-summary has correct response shape', function () {
    $this->getJson('/api/v1/reports/cogs-summary')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
            ],
        ]);
});

it('cogs-by-product has correct response shape', function () {
    $this->getJson('/api/v1/reports/cogs-by-product')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'products',
                'total_cogs',
            ],
        ]);
});

it('cogs-by-category has correct response shape', function () {
    $this->getJson('/api/v1/reports/cogs-by-category')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'period',
                'categories',
                'total_cogs',
            ],
        ]);
});

it('cogs-monthly-trend has correct response shape', function () {
    $this->getJson('/api/v1/reports/cogs-monthly-trend')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'year',
                'months',
                'total_cogs',
            ],
        ]);
});

it('product-cogs-detail has correct response shape', function () {
    $product = Product::factory()->create();

    $this->getJson("/api/v1/reports/products/{$product->id}/cogs")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'product',
                'period',
                'movements',
                'total_quantity',
                'total_cogs',
            ],
        ]);
});

// ─── Comparative Reports (separate shapes) ───────────────────────────

it('comparative balance-sheet has correct response shape', function () {
    $currentDate = now()->toDateString();
    $previousDate = now()->subYear()->toDateString();

    $this->getJson("/api/v1/reports/balance-sheet?as_of_date={$currentDate}&compare_to={$previousDate}")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'current_period',
                'previous_period',
                'variance',
            ],
        ]);
});

it('comparative income-statement has correct response shape', function () {
    $this->getJson('/api/v1/reports/income-statement?compare_previous_period=true')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report_name',
                'current_period',
                'previous_period',
                'variance',
            ],
        ]);
});
