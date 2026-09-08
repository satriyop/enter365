<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Contacts\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\JournalSeeder']);
});

it('returns the partner ledger grouped by contact', function () {
    $customer = Contact::factory()->customer()->create(['name' => 'Kopi Partner']);
    $ar = Account::factory()->create(['code' => '1-1301']);
    $entry = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($ar)->debit(75_000)->create([
        'partner_id' => $customer->id,
    ]);

    $response = $this->getJson('/api/v1/reports/partner-ledger');

    $response->assertOk()
        ->assertJsonPath('data.report_name', 'Buku Besar Partner')
        ->assertJsonPath('data.partners.0.name', 'Kopi Partner')
        ->assertJsonPath('data.partners.0.debit', 75_000)
        ->assertJsonStructure([
            'data' => [
                'report_name',
                'start_date',
                'end_date',
                'partners' => [
                    '*' => [
                        'id',
                        'name',
                        'opening_balance',
                        'debit',
                        'credit',
                        'closing_balance',
                        'entries',
                    ],
                ],
                'total_debit',
                'total_credit',
            ],
        ]);
});

it('exports partner ledger as csv', function () {
    $customer = Contact::factory()->customer()->create(['name' => 'Export Partner']);
    $ar = Account::factory()->create();
    $entry = JournalEntry::factory()->posted()->create(['entry_date' => now()->toDateString()]);
    JournalEntryLine::factory()->forEntry($entry)->forAccount($ar)->debit(10_000)->create([
        'partner_id' => $customer->id,
    ]);

    $response = $this->get('/api/v1/export/partner-ledger?format=csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->getContent())->toContain('Export Partner');
});
