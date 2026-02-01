<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Services\Shared\DashboardQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(DashboardQueryService::class);
});

describe('DashboardQueryService', function () {
    it('can be resolved from container', function () {
        expect($this->service)->toBeInstanceOf(DashboardQueryService::class);
    });

    it('returns zero average collection days when no invoices exist', function () {
        $result = $this->service->getAverageCollectionDays();

        expect($result)->toBe(0.0);
    });

    it('returns empty top debtors when no outstanding invoices', function () {
        $result = $this->service->getTopDebtors(5, [Contact::TYPE_CUSTOMER], ['sent']);

        expect($result)->toBe([]);
    });

    it('returns empty top creditors when no outstanding bills', function () {
        $result = $this->service->getTopCreditors(5, [Contact::TYPE_SUPPLIER], ['received']);

        expect($result)->toBe([]);
    });

    it('returns empty cash flow when no journal entries', function () {
        $result = $this->service->getCashFlowDailyMovement(
            collect([999]),
            now()->subDays(30),
            now()
        );

        expect($result)->toBeEmpty();
    });

    it('returns zero account balance when no entries', function () {
        $account = Account::factory()->create(['type' => Account::TYPE_ASSET]);

        $result = $this->service->getAccountBalanceForPeriod(
            $account,
            now()->startOfMonth()->toDateString(),
            now()->toDateString()
        );

        expect($result)->toBe(0);
    });
});

describe('DashboardQueryService caching', function () {
    it('caches average collection days result', function () {
        Cache::flush();

        $result1 = $this->service->getAverageCollectionDays();
        $result2 = $this->service->getAverageCollectionDays();

        expect($result1)->toBe($result2);
        expect(Cache::has('dashboard:avg_collection_days'))->toBeTrue();
    });

    it('caches account balance result', function () {
        Cache::flush();

        $account = Account::factory()->create(['type' => Account::TYPE_ASSET]);
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->toDateString();

        $result1 = $this->service->getAccountBalanceForPeriod($account, $startDate, $endDate);

        $cacheKey = "dashboard:balance:{$account->id}:{$startDate}:{$endDate}";
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('can flush dashboard cache', function () {
        Cache::flush();

        // Populate cache
        $this->service->getAverageCollectionDays();
        expect(Cache::has('dashboard:avg_collection_days'))->toBeTrue();

        // Flush
        DashboardQueryService::flushCache();
        expect(Cache::has('dashboard:avg_collection_days'))->toBeFalse();
    });

    it('top debtors result is cached', function () {
        Cache::flush();

        $result1 = $this->service->getTopDebtors(5, [Contact::TYPE_CUSTOMER], ['sent']);
        $result2 = $this->service->getTopDebtors(5, [Contact::TYPE_CUSTOMER], ['sent']);

        expect($result1)->toBe($result2);
    });
});
