<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    authenticatedAdmin();
});

it('exports the general ledger without requiring account_id', function () {
    $cash = Account::query()->where('code', '1-1001')->firstOrFail();
    $revenue = Account::query()->where('code', '4-1001')->firstOrFail();
    $entry = JournalEntry::factory()->posted()->create([
        'entry_date' => now()->toDateString(),
        'description' => 'GL export fixture',
    ]);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($cash)->debit(50_000)->create();
    JournalEntryLine::factory()->forEntry($entry)->forAccount($revenue)->credit(50_000)->create();

    $csv = $this->get('/api/v1/export/general-ledger?format=csv');
    $csv->assertOk();
    expect($csv->headers->get('Content-Disposition'))->toContain('.csv')
        ->and($csv->getContent())->toContain('1-1001')
        ->and($csv->getContent())->toContain('4-1001');

    $xlsx = $this->get('/api/v1/export/general-ledger?format=xlsx');
    $xlsx->assertOk();
    expect($xlsx->headers->get('Content-Disposition'))->toContain('.xlsx');

    $pdf = $this->get('/api/v1/export/general-ledger?format=pdf');
    $pdf->assertOk();
    expect($pdf->headers->get('Content-Disposition'))->toContain('.pdf');
});

it('still exports a single account when account_id is given', function () {
    $cash = Account::query()->where('code', '1-1001')->firstOrFail();
    $revenue = Account::query()->where('code', '4-1001')->firstOrFail();
    $entry = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($cash)->debit(25_000)->create();
    JournalEntryLine::factory()->forEntry($entry)->forAccount($revenue)->credit(25_000)->create();

    $csv = $this->get('/api/v1/export/general-ledger?format=csv&account_id='.$cash->id);
    $csv->assertOk();
    expect($csv->getContent())->toContain('1-1001')
        ->and($csv->getContent())->not->toContain('4-1001');
});
