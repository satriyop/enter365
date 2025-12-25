<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed default accounts
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
});

describe('Account API', function () {
    
    it('can list all accounts', function () {
        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'type', 'is_active'],
                ],
                'links',
                'meta',
            ]);
    });

    it('can filter accounts by type', function () {
        $response = $this->getJson('/api/v1/accounts?type=asset');

        $response->assertOk();
        
        foreach ($response->json('data') as $account) {
            expect($account['type'])->toBe('asset');
        }
    });

    it('can search accounts by name', function () {
        $response = $this->getJson('/api/v1/accounts?search=Kas');

        $response->assertOk();
        expect($response->json('data'))->not->toBeEmpty();
    });

    it('can create a new account', function () {
        $response = $this->postJson('/api/v1/accounts', [
            'code' => '1-9999',
            'name' => 'Test Account',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'description' => 'Test description',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', '1-9999')
            ->assertJsonPath('data.name', 'Test Account');
        
        $this->assertDatabaseHas('accounts', ['code' => '1-9999']);
    });

    it('validates required fields when creating account', function () {
        $response = $this->postJson('/api/v1/accounts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'type']);
    });

    it('prevents duplicate account codes', function () {
        $existing = Account::where('code', '1-1001')->first();
        
        $response = $this->postJson('/api/v1/accounts', [
            'code' => '1-1001',
            'name' => 'Duplicate Account',
            'type' => Account::TYPE_ASSET,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('can show a single account', function () {
        $account = Account::where('code', '1-1001')->first();

        $response = $this->getJson("/api/v1/accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', '1-1001');
    });

    it('can update an account', function () {
        $account = Account::factory()->create();

        $response = $this->putJson("/api/v1/accounts/{$account->id}", [
            'name' => 'Updated Account Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Account Name');
    });

    it('cannot update system account code', function () {
        $account = Account::where('is_system', true)->first();

        $response = $this->putJson("/api/v1/accounts/{$account->id}", [
            'code' => '9-9999',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete an account', function () {
        $account = Account::factory()->create();

        $response = $this->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertOk();
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    });

    it('cannot delete system account', function () {
        $account = Account::where('is_system', true)->first();

        $response = $this->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertUnprocessable();
    });

    it('cannot delete account with journal entries', function () {
        $account = Account::factory()->create();
        $entry = JournalEntry::factory()->posted()->create();
        JournalEntryLine::factory()->forEntry($entry)->forAccount($account)->debit(100000)->create();

        $response = $this->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertUnprocessable();
    });

    it('can get account balance', function () {
        $account = Account::where('code', '1-1001')->first();

        $response = $this->getJson("/api/v1/accounts/{$account->id}/balance");

        $response->assertOk()
            ->assertJsonStructure(['account_id', 'code', 'name', 'type', 'as_of_date', 'balance']);
    });

    it('can get account ledger', function () {
        $account = Account::where('code', '1-1001')->first();

        $response = $this->getJson("/api/v1/accounts/{$account->id}/ledger");

        $response->assertOk()
            ->assertJsonStructure(['account_id', 'code', 'name', 'type', 'opening_balance', 'entries']);
    });
});
