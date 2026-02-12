<?php

declare(strict_types=1);

use App\Models\Sales\Quotation;
use App\Services\Sales\Quotation\QuotationStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(QuotationStatisticsService::class);
});

describe('QuotationStatisticsService overview', function () {
    it('can be resolved from container', function () {
        expect($this->service)->toBeInstanceOf(QuotationStatisticsService::class);
    });

    it('returns statistics with no quotations', function () {
        $result = $this->service->get();

        expect($result)->toHaveKeys(['total', 'by_status', 'total_value', 'approval_rate', 'conversion_rate'])
            ->and($result['total'])->toBe(0)
            ->and($result['total_value'])->toBe(0);
    });

    it('counts quotations by status', function () {
        Quotation::factory()->draft()->create();
        Quotation::factory()->submitted()->create();
        Quotation::factory()->approved()->count(2)->create();

        $result = $this->service->get();

        expect($result['total'])->toBe(4)
            ->and($result['by_status']['draft'])->toBe(1)
            ->and($result['by_status']['submitted'])->toBe(1)
            ->and($result['by_status']['approved'])->toBe(2);
    });

    it('filters by date range', function () {
        Quotation::factory()->draft()->create(['quotation_date' => '2024-06-15']);
        Quotation::factory()->draft()->create(['quotation_date' => '2024-07-15']);

        $result = $this->service->get('2024-06-01', '2024-06-30');

        expect($result['total'])->toBe(1);
    });
});

describe('QuotationStatisticsService win/loss', function () {
    it('returns zero win rate when no quotations', function () {
        $result = $this->service->getWinLoss();

        expect($result['won'])->toBe(0)
            ->and($result['lost'])->toBe(0)
            ->and($result['win_rate'])->toEqual(0);
    });

    it('calculates win rate correctly', function () {
        Quotation::factory()->won()->count(3)->create();
        Quotation::factory()->lost()->count(1)->create();

        Cache::flush(); // Clear cache to get fresh data
        $result = $this->service->getWinLoss();

        expect($result['won'])->toBe(3)
            ->and($result['lost'])->toBe(1)
            ->and($result['win_rate'])->toBe(75.0);
    });
});

describe('QuotationStatisticsService outcome statistics', function () {
    it('returns zero counts with no data', function () {
        $result = $this->service->getOutcomeStatistics('2024-01-01', '2024-12-31');

        expect($result->total_quotations)->toBe(0)
            ->and($result->won_count)->toBe(0)
            ->and($result->lost_count)->toBe(0)
            ->and($result->won_value)->toBe(0);
    });

    it('aggregates outcomes within date range', function () {
        Quotation::factory()->won()->create([
            'quotation_date' => '2024-06-10',
            'total_amount' => 10000000,
        ]);
        Quotation::factory()->lost()->create([
            'quotation_date' => '2024-06-20',
            'total_amount' => 5000000,
        ]);
        // Outside range
        Quotation::factory()->won()->create([
            'quotation_date' => '2024-07-15',
            'total_amount' => 8000000,
        ]);

        $result = $this->service->getOutcomeStatistics('2024-06-01', '2024-06-30');

        expect($result->total_quotations)->toBe(2)
            ->and($result->won_count)->toBe(1)
            ->and($result->lost_count)->toBe(1)
            ->and($result->won_value)->toBe(10000000)
            ->and($result->lost_value)->toBe(5000000);
    });
});

describe('QuotationStatisticsService lost/won reasons', function () {
    it('returns empty array for lost reasons when no data', function () {
        $result = $this->service->getLostReasonsBreakdown('2024-01-01', '2024-12-31');

        expect($result)->toBe([]);
    });

    it('breaks down lost reasons with counts and values', function () {
        Quotation::factory()->lost('harga_tinggi')->create([
            'quotation_date' => '2024-06-10',
            'total_amount' => 10000000,
        ]);
        Quotation::factory()->lost('harga_tinggi')->create([
            'quotation_date' => '2024-06-15',
            'total_amount' => 5000000,
        ]);
        Quotation::factory()->lost('kalah_kompetitor')->create([
            'quotation_date' => '2024-06-20',
            'total_amount' => 8000000,
        ]);

        $result = $this->service->getLostReasonsBreakdown('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(2);

        $highPrice = collect($result)->firstWhere('reason', 'harga_tinggi');
        expect($highPrice['count'])->toBe(2)
            ->and($highPrice['value'])->toBe(15000000)
            ->and($highPrice['label'])->toBe('Harga Terlalu Tinggi');
    });

    it('returns empty array for won reasons when no data', function () {
        $result = $this->service->getWonReasonsBreakdown('2024-01-01', '2024-12-31');

        expect($result)->toBe([]);
    });

    it('breaks down won reasons with counts and values', function () {
        Quotation::factory()->won('harga_kompetitif')->create([
            'quotation_date' => '2024-06-10',
            'total_amount' => 20000000,
        ]);
        Quotation::factory()->won('kualitas_produk')->create([
            'quotation_date' => '2024-06-15',
            'total_amount' => 15000000,
        ]);

        $result = $this->service->getWonReasonsBreakdown('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(2);

        $competitive = collect($result)->firstWhere('reason', 'harga_kompetitif');
        expect($competitive['count'])->toBe(1)
            ->and($competitive['value'])->toBe(20000000)
            ->and($competitive['label'])->toBe('Harga Kompetitif');
    });
});

describe('QuotationStatisticsService dashboard data', function () {
    it('combines overview and win_loss data', function () {
        $result = $this->service->getDashboardData();

        expect($result)->toHaveKeys(['overview', 'win_loss', 'follow_up'])
            ->and($result['overview'])->toHaveKey('total')
            ->and($result['win_loss'])->toHaveKey('win_rate');
    });
});

describe('QuotationStatisticsService caching', function () {
    it('caches win/loss results', function () {
        Cache::flush();

        $result1 = $this->service->getWinLoss();
        $result2 = $this->service->getWinLoss();

        expect($result1)->toBe($result2);
        expect(Cache::has('quotation_stats:win_loss'))->toBeTrue();
    });

    it('caches outcome statistics', function () {
        Cache::flush();

        $this->service->getOutcomeStatistics('2024-06-01', '2024-06-30');

        $cacheKey = 'quotation_stats:outcomes:'.md5('2024-06-01:2024-06-30');
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    it('can flush quotation statistics cache', function () {
        Cache::flush();

        $this->service->getWinLoss();
        expect(Cache::has('quotation_stats:win_loss'))->toBeTrue();

        QuotationStatisticsService::flushCache();
        expect(Cache::has('quotation_stats:win_loss'))->toBeFalse();
    });
});
