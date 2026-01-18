<?php

use App\Exceptions\Domain\MissingAccountException;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AccountLookupService;
});

describe('findByCodeOrFail', function () {

    it('returns account when code exists', function () {
        $account = Account::factory()->create(['code' => '4-2001']);

        $result = $this->service->findByCodeOrFail('4-2001', 'sales returns');

        expect($result->id)->toBe($account->id);
        expect($result->code)->toBe('4-2001');
    });

    it('throws MissingAccountException when code does not exist', function () {
        $this->service->findByCodeOrFail('NONEXISTENT', 'test purpose');
    })->throws(MissingAccountException::class, "Required account with code 'NONEXISTENT' not found for test purpose");

    it('caches account for subsequent calls', function () {
        $account = Account::factory()->create(['code' => '1-1100']);

        // First call - should query database
        $result1 = $this->service->findByCodeOrFail('1-1100');

        // Delete the account from database
        $account->forceDelete();

        // Second call - should return cached result, not throw
        $result2 = $this->service->findByCodeOrFail('1-1100');

        expect($result1->id)->toBe($result2->id);
    });

    it('includes purpose in error message when provided', function () {
        try {
            $this->service->findByCodeOrFail('MISSING', 'journal entry creation');
            $this->fail('Expected exception was not thrown');
        } catch (MissingAccountException $e) {
            expect($e->getMessage())->toContain('journal entry creation');
            expect($e->getContext()['purpose'])->toBe('journal entry creation');
        }
    });

});

describe('findByCodesOrFail', function () {

    it('returns collection keyed by account code when all exist', function () {
        Account::factory()->create(['code' => '4-2001', 'name' => 'Sales Returns']);
        Account::factory()->create(['code' => '1-1100', 'name' => 'Receivable']);
        Account::factory()->create(['code' => '2-1200', 'name' => 'PPN']);

        $result = $this->service->findByCodesOrFail(['4-2001', '1-1100', '2-1200'], 'test');

        expect($result)->toHaveCount(3);
        expect($result->get('4-2001')->name)->toBe('Sales Returns');
        expect($result->get('1-1100')->name)->toBe('Receivable');
        expect($result->get('2-1200')->name)->toBe('PPN');
    });

    it('throws MissingAccountException listing ALL missing codes', function () {
        Account::factory()->create(['code' => '1-1100']);
        // 4-2001 and 2-1200 are missing

        try {
            $this->service->findByCodesOrFail(['4-2001', '1-1100', '2-1200'], 'journal creation');
            $this->fail('Expected exception was not thrown');
        } catch (MissingAccountException $e) {
            expect($e->getMessage())->toContain("'4-2001'");
            expect($e->getMessage())->toContain("'2-1200'");
            expect($e->getContext()['missing_codes'])->toContain('4-2001');
            expect($e->getContext()['missing_codes'])->toContain('2-1200');
            expect($e->getContext()['missing_codes'])->not->toContain('1-1100');
        }
    });

    it('uses cache for already-fetched codes', function () {
        Account::factory()->create(['code' => '1-1100']);
        Account::factory()->create(['code' => '4-2001']);

        // First call fetches 1-1100
        $this->service->findByCodeOrFail('1-1100');

        // Second call should only query for 4-2001, use cache for 1-1100
        $result = $this->service->findByCodesOrFail(['1-1100', '4-2001']);

        expect($result)->toHaveCount(2);
    });

});

describe('findByIdOrFail', function () {

    it('returns account when ID exists', function () {
        $account = Account::factory()->create(['code' => '1-1100']);

        $result = $this->service->findByIdOrFail($account->id, 'receivable lookup');

        expect($result->code)->toBe('1-1100');
    });

    it('throws MissingAccountException when ID does not exist', function () {
        $this->service->findByIdOrFail(99999, 'test lookup');
    })->throws(MissingAccountException::class, 'Account with ID 99999 not found for test lookup');

    it('caches account for subsequent ID lookups', function () {
        $account = Account::factory()->create();

        // First call
        $result1 = $this->service->findByIdOrFail($account->id);

        // Delete from DB
        $account->forceDelete();

        // Second call should use cache
        $result2 = $this->service->findByIdOrFail($account->id);

        expect($result1->id)->toBe($result2->id);
    });

    it('populates code cache when fetching by ID', function () {
        $account = Account::factory()->create(['code' => '3-3000']);

        // Fetch by ID
        $this->service->findByIdOrFail($account->id);

        // Delete from DB
        $account->forceDelete();

        // Should be able to fetch by code from cache
        $result = $this->service->findByCodeOrFail('3-3000');

        expect($result->id)->toBe($account->id);
    });

});

describe('clearCache', function () {

    it('clears all cached accounts', function () {
        $account = Account::factory()->create(['code' => '1-1100']);

        // Populate cache
        $this->service->findByCodeOrFail('1-1100');

        // Delete from DB
        $account->forceDelete();

        // Clear cache
        $this->service->clearCache();

        // Should now throw because not in cache or DB
        $this->service->findByCodeOrFail('1-1100');
    })->throws(MissingAccountException::class);

});

describe('MissingAccountException', function () {

    it('has correct error code', function () {
        $exception = MissingAccountException::forCode('X-0000');

        expect($exception->getErrorCode())->toBe('MISSING_ACCOUNT');
    });

    it('has 500 status code (server configuration issue)', function () {
        $exception = MissingAccountException::forCode('X-0000');

        expect($exception->getStatusCode())->toBe(500);
    });

    it('provides helpful resolution message', function () {
        $exception = MissingAccountException::forCode('X-0000');

        expect($exception->getMessage())->toContain('Chart of Accounts is properly seeded');
    });

});
