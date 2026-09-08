<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Contacts\Contact;
use App\Services\Accounting\Reports\Financial\PartnerLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
    $this->service = app(PartnerLedgerReportService::class);
});

it('groups posted partner lines and ignores lines without a partner', function () {
    $customer = Contact::factory()->customer()->create(['name' => 'Kopi Partner']);
    $ar = Account::factory()->create(['code' => '1-1301', 'name' => 'Piutang']);
    $revenue = Account::factory()->create(['code' => '4-1001', 'name' => 'Pendapatan']);

    $entry = JournalEntry::factory()->posted()->create(['entry_date' => '2026-03-15']);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($ar)->debit(110_000)->create([
        'partner_id' => $customer->id,
        'description' => 'Invoice AR',
    ]);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($revenue)->credit(110_000)->create();

    $report = $this->service->getPartnerLedger('2026-03-01', '2026-03-31');

    expect($report['report_name'])->toBe('Buku Besar Partner')
        ->and($report['partners'])->toHaveCount(1)
        ->and($report['partners'][0]['name'])->toBe('Kopi Partner')
        ->and($report['partners'][0]['debit'])->toBe(110_000)
        ->and($report['partners'][0]['credit'])->toBe(0)
        ->and($report['partners'][0]['closing_balance'])->toBe(110_000)
        ->and($report['partners'][0]['entries'])->toHaveCount(1)
        ->and($report['total_debit'])->toBe(110_000);
});

it('filters by contact and applies opening balance before the start date', function () {
    $customer = Contact::factory()->customer()->create(['name' => 'Rina']);
    $other = Contact::factory()->customer()->create(['name' => 'Other']);
    $ar = Account::factory()->create();

    $prior = JournalEntry::factory()->posted()->create(['entry_date' => '2026-01-10']);
    JournalEntryLine::factory()->forEntry($prior)->forAccount($ar)->debit(50_000)->create([
        'partner_id' => $customer->id,
    ]);

    $period = JournalEntry::factory()->posted()->create(['entry_date' => '2026-02-10']);
    JournalEntryLine::factory()->forEntry($period)->forAccount($ar)->credit(20_000)->create([
        'partner_id' => $customer->id,
    ]);
    JournalEntryLine::factory()->forEntry($period)->forAccount($ar)->debit(9_000)->create([
        'partner_id' => $other->id,
    ]);

    $report = $this->service->getPartnerLedger('2026-02-01', '2026-02-28', $customer->id);

    expect($report['partners'])->toHaveCount(1)
        ->and($report['partners'][0]['id'])->toBe($customer->id)
        ->and($report['partners'][0]['opening_balance'])->toBe(50_000)
        ->and($report['partners'][0]['debit'])->toBe(0)
        ->and($report['partners'][0]['credit'])->toBe(20_000)
        ->and($report['partners'][0]['closing_balance'])->toBe(30_000);
});

it('skips unposted journal entries', function () {
    $customer = Contact::factory()->customer()->create();
    $ar = Account::factory()->create();
    $draft = JournalEntry::factory()->create(['is_posted' => false, 'entry_date' => '2026-03-01']);
    JournalEntryLine::factory()->forEntry($draft)->forAccount($ar)->debit(1_000)->create([
        'partner_id' => $customer->id,
    ]);

    $report = $this->service->getPartnerLedger();

    expect($report['partners'])->toBe([]);
});
