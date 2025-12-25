<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Dashboard API', function () {

    it('can get dashboard summary', function () {
        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'receivables',
                'payables',
                'cash_position',
                'recent_activity',
                'monthly_comparison',
            ]);
    });

    it('can get receivables summary', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->forContact($customer)->overdue()->create([
            'total_amount' => 3000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/dashboard/receivables');

        $response->assertOk()
            ->assertJsonStructure([
                'total_outstanding',
                'total_overdue',
                'count',
                'overdue_count',
                'aging',
                'top_debtors',
            ])
            ->assertJsonPath('total_outstanding', 8000000)
            ->assertJsonPath('total_overdue', 3000000);
    });

    it('can get payables summary', function () {
        $supplier = Contact::factory()->supplier()->create();

        Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/dashboard/payables');

        $response->assertOk()
            ->assertJsonStructure([
                'total_outstanding',
                'total_overdue',
                'count',
                'overdue_count',
                'aging',
                'top_creditors',
            ]);
    });

    it('can get cash flow summary', function () {
        $response = $this->getJson('/api/v1/dashboard/cash-flow');

        $response->assertOk()
            ->assertJsonStructure([
                'period_days',
                'total_inflow',
                'total_outflow',
                'net_flow',
                'daily_movement',
            ]);
    });

    it('can get profit/loss summary', function () {
        $response = $this->getJson('/api/v1/dashboard/profit-loss');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'total_revenue',
                'total_expense',
                'net_income',
                'profit_margin',
            ]);
    });

    it('can get KPIs', function () {
        $response = $this->getJson('/api/v1/dashboard/kpis');

        $response->assertOk()
            ->assertJsonStructure([
                'revenue' => [
                    'current_month',
                    'last_month',
                    'growth_percent',
                ],
                'collection' => [
                    'average_days',
                    'overdue_invoices',
                ],
                'customers' => [
                    'total',
                    'active_this_month',
                ],
            ]);
    });

    it('includes recent activity in summary', function () {
        $customer = Contact::factory()->customer()->create();
        Invoice::factory()->forContact($customer)->count(3)->create();

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();

        $recentActivity = $response->json('recent_activity');
        expect($recentActivity)->toBeArray();
    });

    it('includes monthly comparison data', function () {
        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk();

        $monthlyComparison = $response->json('monthly_comparison');
        expect($monthlyComparison)->toHaveCount(6); // Last 6 months
    });

    it('calculates top debtors correctly', function () {
        $customer1 = Contact::factory()->customer()->create(['name' => 'Customer A']);
        $customer2 = Contact::factory()->customer()->create(['name' => 'Customer B']);

        Invoice::factory()->forContact($customer1)->sent()->create([
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->forContact($customer2)->sent()->create([
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/dashboard/receivables');

        $response->assertOk();

        $topDebtors = $response->json('top_debtors');
        expect($topDebtors[0]['name'])->toBe('Customer A');
        expect($topDebtors[0]['outstanding'])->toBe(10000000);
    });

    it('can filter cash flow by days', function () {
        $response = $this->getJson('/api/v1/dashboard/cash-flow?days=7');

        $response->assertOk()
            ->assertJsonPath('period_days', 7);
    });

    it('can filter profit/loss by date range', function () {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/v1/dashboard/profit-loss?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start_date', $startDate)
            ->assertJsonPath('period.end_date', $endDate);
    });
});
