<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Journal;
use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    authenticatedAdmin();
});

describe('Journal Master API', function () {

    it('can list seeded journals', function () {
        $response = $this->getJson('/api/v1/journals');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'type', 'sequence_prefix', 'default_account_id', 'currency', 'is_active'],
                ],
            ]);

        expect(count($response->json('data')))->toBeGreaterThanOrEqual(5);
    });

    it('can filter journals by type', function () {
        $response = $this->getJson('/api/v1/journals?type=bank');

        $response->assertOk();

        foreach ($response->json('data') as $journal) {
            expect($journal['type'])->toBe('bank');
        }
    });

    it('can create a journal', function () {
        $cash = Account::where('code', '1-1001')->first();

        $response = $this->postJson('/api/v1/journals', [
            'name' => 'Bank BCA',
            'type' => Journal::TYPE_BANK,
            'sequence_prefix' => 'BCA-',
            'default_account_id' => $cash?->id,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bank BCA')
            ->assertJsonPath('data.type', 'bank')
            ->assertJsonPath('data.sequence_prefix', 'BCA-')
            ->assertJsonPath('data.currency', 'IDR');

        $this->assertDatabaseHas('journals', ['sequence_prefix' => 'BCA-']);
    });

    it('validates required fields when creating journal', function () {
        $response = $this->postJson('/api/v1/journals', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'sequence_prefix']);
    });

    it('prevents duplicate sequence prefixes', function () {
        $response = $this->postJson('/api/v1/journals', [
            'name' => 'Dup',
            'type' => Journal::TYPE_MISCELLANEOUS,
            'sequence_prefix' => 'MISC-',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sequence_prefix']);
    });

    it('can show a journal', function () {
        $journal = Journal::query()->where('type', Journal::TYPE_SALES)->firstOrFail();

        $response = $this->getJson("/api/v1/journals/{$journal->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $journal->id)
            ->assertJsonPath('data.type', 'sales');
    });

    it('can update a journal', function () {
        $journal = Journal::query()->where('type', Journal::TYPE_CASH)->firstOrFail();

        $response = $this->putJson("/api/v1/journals/{$journal->id}", [
            'name' => 'Cash Drawer',
            'currency' => 'IDR',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Cash Drawer')
            ->assertJsonPath('data.currency', 'IDR');
    });

    it('can delete an unused journal', function () {
        $journal = Journal::factory()->create([
            'name' => 'Temp Journal',
            'type' => Journal::TYPE_MISCELLANEOUS,
            'sequence_prefix' => 'TMP-',
        ]);

        $response = $this->deleteJson("/api/v1/journals/{$journal->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('journals', ['id' => $journal->id]);
    });

    it('cannot delete a journal that has entries', function () {
        $journal = Journal::query()->where('type', Journal::TYPE_MISCELLANEOUS)->firstOrFail();
        JournalEntry::factory()->create(['journal_id' => $journal->id]);

        $response = $this->deleteJson("/api/v1/journals/{$journal->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('journals', ['id' => $journal->id]);
    });
});
