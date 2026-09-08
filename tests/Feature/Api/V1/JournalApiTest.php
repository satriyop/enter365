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
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'sequence_prefix',
                        'default_account_id',
                        'suspense_account_id',
                        'profit_account_id',
                        'loss_account_id',
                        'outstanding_receipts_account_id',
                        'outstanding_payments_account_id',
                        'bank_account_number',
                        'dedicated_payment_sequence',
                        'currency',
                        'is_active',
                    ],
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

    it('can create a bank journal with payment plumbing accounts', function () {
        $bank = Account::where('code', '1-1001')->firstOrFail();
        $suspense = Account::factory()->create(['name' => 'Bank Suspense']);
        $receipts = Account::factory()->create(['name' => 'Outstanding Receipts']);
        $payments = Account::factory()->create(['name' => 'Outstanding Payments']);

        $response = $this->postJson('/api/v1/journals', [
            'name' => 'Bank Mandiri',
            'type' => Journal::TYPE_BANK,
            'sequence_prefix' => 'MDR-',
            'default_account_id' => $bank->id,
            'suspense_account_id' => $suspense->id,
            'outstanding_receipts_account_id' => $receipts->id,
            'outstanding_payments_account_id' => $payments->id,
            'bank_account_number' => '1234567890',
            'dedicated_payment_sequence' => true,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bank Mandiri')
            ->assertJsonPath('data.suspense_account_id', $suspense->id)
            ->assertJsonPath('data.outstanding_receipts_account_id', $receipts->id)
            ->assertJsonPath('data.outstanding_payments_account_id', $payments->id)
            ->assertJsonPath('data.bank_account_number', '1234567890')
            ->assertJsonPath('data.dedicated_payment_sequence', true);

        $this->assertDatabaseHas('journals', [
            'sequence_prefix' => 'MDR-',
            'suspense_account_id' => $suspense->id,
            'outstanding_receipts_account_id' => $receipts->id,
            'outstanding_payments_account_id' => $payments->id,
            'bank_account_number' => '1234567890',
            'dedicated_payment_sequence' => true,
        ]);
    });

    it('can create a bank journal with profit and loss accounts', function () {
        $bank = Account::where('code', '1-1001')->firstOrFail();
        $profit = Account::factory()->create(['name' => 'Bank Difference Gain']);
        $loss = Account::factory()->create(['name' => 'Bank Difference Loss']);

        $response = $this->postJson('/api/v1/journals', [
            'name' => 'Bank BNI',
            'type' => Journal::TYPE_BANK,
            'sequence_prefix' => 'BNI-',
            'default_account_id' => $bank->id,
            'profit_account_id' => $profit->id,
            'loss_account_id' => $loss->id,
            'currency' => 'IDR',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.profit_account_id', $profit->id)
            ->assertJsonPath('data.loss_account_id', $loss->id);

        $this->assertDatabaseHas('journals', [
            'sequence_prefix' => 'BNI-',
            'profit_account_id' => $profit->id,
            'loss_account_id' => $loss->id,
        ]);
    });

    it('can update bank payment plumbing fields on a cash journal', function () {
        $journal = Journal::query()->where('type', Journal::TYPE_CASH)->firstOrFail();
        $suspense = Account::factory()->create(['name' => 'Cash Suspense']);
        $receipts = Account::factory()->create(['name' => 'Cash Outstanding Receipts']);
        $payments = Account::factory()->create(['name' => 'Cash Outstanding Payments']);
        $profit = Account::factory()->create(['name' => 'Cash Difference Gain']);
        $loss = Account::factory()->create(['name' => 'Cash Difference Loss']);

        $response = $this->putJson("/api/v1/journals/{$journal->id}", [
            'suspense_account_id' => $suspense->id,
            'outstanding_receipts_account_id' => $receipts->id,
            'outstanding_payments_account_id' => $payments->id,
            'profit_account_id' => $profit->id,
            'loss_account_id' => $loss->id,
            'bank_account_number' => null,
            'dedicated_payment_sequence' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.suspense_account_id', $suspense->id)
            ->assertJsonPath('data.outstanding_receipts_account_id', $receipts->id)
            ->assertJsonPath('data.outstanding_payments_account_id', $payments->id)
            ->assertJsonPath('data.profit_account_id', $profit->id)
            ->assertJsonPath('data.loss_account_id', $loss->id)
            ->assertJsonPath('data.dedicated_payment_sequence', false);

        $this->assertDatabaseHas('journals', [
            'id' => $journal->id,
            'suspense_account_id' => $suspense->id,
            'outstanding_receipts_account_id' => $receipts->id,
            'outstanding_payments_account_id' => $payments->id,
            'profit_account_id' => $profit->id,
            'loss_account_id' => $loss->id,
        ]);
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
