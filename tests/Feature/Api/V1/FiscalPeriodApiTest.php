<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('Fiscal Period API', function () {

    it('can list all fiscal periods', function () {
        FiscalPeriod::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/fiscal-periods');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter fiscal periods by is_closed', function () {
        FiscalPeriod::factory()->count(2)->create(['is_closed' => false]);
        FiscalPeriod::factory()->count(3)->create(['is_closed' => true]);

        $response = $this->getJson('/api/v1/fiscal-periods?is_closed=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a fiscal period', function () {
        $response = $this->postJson('/api/v1/fiscal-periods', [
            'name' => 'Tahun Fiskal 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tahun Fiskal 2025')
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.is_locked', false);
    });

    it('validates overlapping fiscal periods', function () {
        FiscalPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $response = $this->postJson('/api/v1/fiscal-periods', [
            'name' => 'Overlap Period',
            'start_date' => '2025-06-01',
            'end_date' => '2026-05-31',
        ]);

        $response->assertUnprocessable();
    });

    it('can show a fiscal period', function () {
        $period = FiscalPeriod::factory()->create();

        $response = $this->getJson("/api/v1/fiscal-periods/{$period->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $period->id);
    });

    it('can lock a fiscal period', function () {
        $period = FiscalPeriod::factory()->create(['is_locked' => false]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/lock");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', true);
    });

    it('cannot lock already locked period', function () {
        $period = FiscalPeriod::factory()->create(['is_locked' => true]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/lock");

        $response->assertUnprocessable();
    });

    it('can unlock a fiscal period', function () {
        $period = FiscalPeriod::factory()->create(['is_locked' => true, 'is_closed' => false]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/unlock");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);
    });

    it('cannot unlock closed period', function () {
        $period = FiscalPeriod::factory()->create(['is_locked' => true, 'is_closed' => true]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/unlock");

        $response->assertUnprocessable();
    });

    it('can get closing checklist', function () {
        $period = FiscalPeriod::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        $response = $this->getJson("/api/v1/fiscal-periods/{$period->id}/closing-checklist");

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'can_close',
                'checklist' => [
                    'unposted_journals',
                    'draft_invoices',
                    'draft_bills',
                ],
            ]);
    });

    it('cannot close period with unposted journals', function () {
        $period = FiscalPeriod::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
        ]);

        JournalEntry::factory()->create([
            'fiscal_period_id' => $period->id,
            'is_posted' => false,
        ]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/close");

        $response->assertUnprocessable();
    });

    it('cannot reopen period that is not closed', function () {
        $period = FiscalPeriod::factory()->create(['is_closed' => false]);

        $response = $this->postJson("/api/v1/fiscal-periods/{$period->id}/reopen");

        $response->assertUnprocessable();
    });
});
