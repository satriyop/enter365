<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Journal Entry API', function () {
    
    it('can list all journal entries', function () {
        JournalEntry::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/journal-entries');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter journal entries by posted status', function () {
        JournalEntry::factory()->count(2)->create();
        JournalEntry::factory()->posted()->count(3)->create();

        $response = $this->getJson('/api/v1/journal-entries?is_posted=1');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter journal entries by date range', function () {
        JournalEntry::factory()->create(['entry_date' => '2024-01-15']);
        JournalEntry::factory()->create(['entry_date' => '2024-02-15']);
        JournalEntry::factory()->create(['entry_date' => '2024-03-15']);

        $response = $this->getJson('/api/v1/journal-entries?start_date=2024-02-01&end_date=2024-02-28');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a balanced journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Test Journal Entry',
            'reference' => 'TEST-001',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash received',
                    'debit' => 1000000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'description' => 'Revenue',
                    'debit' => 0,
                    'credit' => 1000000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Test Journal Entry')
            ->assertJsonPath('data.is_balanced', true)
            ->assertJsonCount(2, 'data.lines');
    });

    it('can create and auto-post journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Auto-posted entry',
            'auto_post' => true,
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_posted', true);
    });

    it('rejects unbalanced journal entry', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    });

    it('requires at least two lines', function () {
        $cashAccount = Account::where('code', '1-1001')->first();

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Single line entry',
            'lines' => [
                ['account_id' => $cashAccount->id, 'debit' => 1000000, 'credit' => 0],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    });

    it('can show a journal entry with lines', function () {
        $entry = JournalEntry::factory()->create();
        $account = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->credit(100000)->create();

        $response = $this->getJson("/api/v1/journal-entries/{$entry->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'entry_number', 'entry_date', 'description', 'is_posted', 'lines',
                ],
            ]);
    });

    it('can post a draft journal entry', function () {
        $entry = JournalEntry::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/post");

        $response->assertOk()
            ->assertJsonPath('data.is_posted', true);
    });

    it('cannot post already posted entry', function () {
        $entry = JournalEntry::factory()->posted()->create();
        $account = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/post");

        $response->assertUnprocessable();
    });

    it('can reverse a posted journal entry', function () {
        $entry = JournalEntry::factory()->posted()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account1)->debit(100000)->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account2)->credit(100000)->create();

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse", [
            'description' => 'Custom reversal description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_posted', true);
        
        // Verify original entry is marked as reversed
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'is_reversed' => true,
        ]);
    });

    it('cannot reverse unposted entry', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        $response = $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse");

        $response->assertUnprocessable();
    });
});
