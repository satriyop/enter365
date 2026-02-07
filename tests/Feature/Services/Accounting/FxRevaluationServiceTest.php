<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Services\Accounting\FxRevaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(FxRevaluationService::class);
    $this->arAccount = Account::where('code', '1-1100')->first();
    $this->apAccount = Account::where('code', '2-1100')->first();
    $this->unrealizedGainAccount = Account::where('code', config('accounting.default_accounts.unrealized_fx_gain'))->first();
    $this->unrealizedLossAccount = Account::where('code', config('accounting.default_accounts.unrealized_fx_loss'))->first();
});

describe('IS-H5: Unrealized FX Revaluation', function () {

    it('creates revaluation JE for open USD invoices with rate increase (gain)', function () {
        // Invoice booked at 15,000 IDR/USD, $1000 outstanding
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Closing rate: 15,500 IDR/USD
        $entries = $this->service->revalue(now()->toDateString(), ['USD' => 15500.0]);

        expect($entries)->toHaveCount(1);

        $je = $entries->first()->load('lines');
        expect($je->source_type)->toBe(JournalEntry::SOURCE_FX_REVALUATION)
            ->and($je->is_posted)->toBeTrue()
            ->and($je->isBalanced())->toBeTrue();

        // AR adjustment: 1000 * (15500 - 15000) = 500,000 debit
        $arLine = $je->lines->firstWhere('account_id', $this->arAccount->id);
        expect($arLine->debit)->toBe(500_000);

        // Unrealized gain: 500,000 credit
        $gainLine = $je->lines->firstWhere('account_id', $this->unrealizedGainAccount->id);
        expect($gainLine->credit)->toBe(500_000);
    });

    it('creates revaluation JE with rate decrease (loss)', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Closing rate: 14,500 IDR/USD (rate decreased = loss)
        $entries = $this->service->revalue(now()->toDateString(), ['USD' => 14500.0]);

        expect($entries)->toHaveCount(1);

        $je = $entries->first()->load('lines');

        // Unrealized loss: 1000 * (15000 - 14500) = 500,000 debit
        $lossLine = $je->lines->firstWhere('account_id', $this->unrealizedLossAccount->id);
        expect($lossLine->debit)->toBe(500_000);

        // AR adjustment: 500,000 credit
        $arLine = $je->lines->firstWhere('account_id', $this->arAccount->id);
        expect($arLine->credit)->toBe(500_000);

        expect($je->isBalanced())->toBeTrue();
    });

    it('revalues open bills (AP) with rate increase as loss', function () {
        // Bill booked at 15,000 IDR/USD, $500 outstanding
        Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);

        // Rate increase: we owe MORE IDR → unrealized loss
        $entries = $this->service->revalue(now()->toDateString(), ['USD' => 16000.0]);

        expect($entries)->toHaveCount(1);

        $je = $entries->first()->load('lines');

        // AP rate increase = loss: 500 * (16000 - 15000) = 500,000
        $lossLine = $je->lines->firstWhere('account_id', $this->unrealizedLossAccount->id);
        expect($lossLine->debit)->toBe(500_000);

        $apLine = $je->lines->firstWhere('account_id', $this->apAccount->id);
        expect($apLine->credit)->toBe(500_000);

        expect($je->isBalanced())->toBeTrue();
    });

    it('excludes IDR invoices from revaluation', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $preview = $this->service->preview(now()->toDateString(), ['USD' => 15000.0]);

        expect($preview['items'])->toBeEmpty()
            ->and($preview['total_gain'])->toBe(0)
            ->and($preview['total_loss'])->toBe(0);
    });

    it('excludes paid invoices from revaluation', function () {
        Invoice::factory()->create([
            'status' => 'paid',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 1000,
        ]);

        $preview = $this->service->preview(now()->toDateString(), ['USD' => 16000.0]);

        expect($preview['items'])->toBeEmpty();
    });

    it('excludes cancelled invoices from revaluation', function () {
        Invoice::factory()->create([
            'status' => 'cancelled',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        $preview = $this->service->preview(now()->toDateString(), ['USD' => 16000.0]);

        expect($preview['items'])->toBeEmpty();
    });

    it('dry run (preview) creates no journal entries', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        $jeBefore = JournalEntry::count();
        $preview = $this->service->preview(now()->toDateString(), ['USD' => 16000.0]);
        $jeAfter = JournalEntry::count();

        expect($preview['items'])->toHaveCount(1)
            ->and($preview['total_gain'])->toBe(1_000_000) // 1000 * (16000 - 15000)
            ->and($jeBefore)->toBe($jeAfter); // no JE created
    });

    it('creates auto-reversal JE when withReversal is true', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        $date = now()->toDateString();
        $entries = $this->service->revalue($date, ['USD' => 15500.0], withReversal: true);

        expect($entries)->toHaveCount(2);

        $revaluation = $entries->first();
        $reversal = $entries->last();

        expect($revaluation->entry_date->toDateString())->toBe($date)
            ->and($reversal->entry_date->toDateString())->toBe(now()->addDay()->toDateString())
            ->and($reversal->source_type)->toBe(JournalEntry::SOURCE_REVERSAL)
            ->and($reversal->isBalanced())->toBeTrue();
    });

    it('handles partial payments correctly (only outstanding amount revalued)', function () {
        Invoice::factory()->create([
            'status' => 'partial',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 600, // $400 outstanding
        ]);

        $preview = $this->service->preview(now()->toDateString(), ['USD' => 16000.0]);

        expect($preview['items'])->toHaveCount(1);

        $item = $preview['items'][0];
        expect($item['outstanding'])->toBe(400)
            ->and($item['booked_base'])->toBe(6_000_000) // 400 * 15000
            ->and($item['revalued_base'])->toBe(6_400_000) // 400 * 16000
            ->and($item['unrealized_fx'])->toBe(400_000); // gain for AR
    });

    it('returns empty collection when no FX difference exists', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Same rate → no difference
        $entries = $this->service->revalue(now()->toDateString(), ['USD' => 15000.0]);

        expect($entries)->toBeEmpty();
    });

    it('handles multiple currencies in a single revaluation', function () {
        Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->sent()->create([
            'currency' => 'EUR',
            'exchange_rate' => 17000,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);

        $preview = $this->service->preview(now()->toDateString(), [
            'USD' => 15500.0,
            'EUR' => 17200.0,
        ]);

        // USD: 1000 * (15500 - 15000) = 500,000 gain
        // EUR: 500 * (17200 - 17000) = 100,000 gain
        expect($preview['items'])->toHaveCount(2)
            ->and($preview['total_gain'])->toBe(600_000);
    });
});
