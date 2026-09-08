<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Pos\PosSale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    FiscalPeriod::query()->forceDelete();
});

it('stores odoo-style lock dates on an open period', function () {
    $period = FiscalPeriod::factory()->current()->create();

    $response = $this->putJson("/api/v1/fiscal-periods/{$period->id}", [
        'lock_sales_until' => '2026-03-31',
        'lock_purchases_until' => '2026-02-28',
        'lock_tax_until' => '2026-01-31',
        'lock_everything_until' => '2026-01-15',
        'hard_lock_until' => '2025-12-31',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.lock_sales_until', '2026-03-31')
        ->assertJsonPath('data.lock_purchases_until', '2026-02-28')
        ->assertJsonPath('data.lock_tax_until', '2026-01-31')
        ->assertJsonPath('data.lock_everything_until', '2026-01-15')
        ->assertJsonPath('data.hard_lock_until', '2025-12-31');
});

it('blocks sales sources on or before lock_sales_until but allows miscellaneous journals', function () {
    $period = FiscalPeriod::factory()->current()->create([
        'lock_sales_until' => '2026-06-15',
    ]);

    expect(fn () => FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-15'),
        JournalEntry::SOURCE_INVOICE,
    ))->toThrow(BusinessRuleException::class);

    expect(fn () => FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-15'),
        PosSale::class,
    ))->toThrow(BusinessRuleException::class);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-15'),
        JournalEntry::SOURCE_MANUAL,
    )->id)->toBe($period->id);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-16'),
        JournalEntry::SOURCE_INVOICE,
    )->id)->toBe($period->id);
});

it('blocks bills on lock_purchases_until without blocking invoices', function () {
    FiscalPeriod::factory()->current()->create([
        'lock_purchases_until' => '2026-04-30',
    ]);

    expect(fn () => FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-04-30'),
        JournalEntry::SOURCE_BILL,
    ))->toThrow(BusinessRuleException::class);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-04-30'),
        JournalEntry::SOURCE_INVOICE,
    ))->toBeInstanceOf(FiscalPeriod::class);
});

it('blocks invoice, bill, and manual on lock_tax_until while allowing the day after and closing', function () {
    $period = FiscalPeriod::factory()->current()->create([
        'lock_tax_until' => '2026-05-31',
    ]);

    foreach ([
        JournalEntry::SOURCE_INVOICE,
        JournalEntry::SOURCE_BILL,
        JournalEntry::SOURCE_MANUAL,
        JournalEntry::SOURCE_PAYMENT,
    ] as $source) {
        expect(fn () => FiscalPeriod::assertOpenForPosting(
            new DateTimeImmutable('2026-05-31'),
            $source,
        ))->toThrow(BusinessRuleException::class);
    }

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-01'),
        JournalEntry::SOURCE_INVOICE,
    )->id)->toBe($period->id);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-06-01'),
        JournalEntry::SOURCE_MANUAL,
    )->id)->toBe($period->id);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-05-31'),
        JournalEntry::SOURCE_CLOSING,
    ))->toBeInstanceOf(FiscalPeriod::class);
});

it('soft lock everything still allows closing entries while hard lock does not', function () {
    FiscalPeriod::factory()->current()->create([
        'lock_everything_until' => '2026-08-31',
        'hard_lock_until' => null,
    ]);

    expect(fn () => FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-08-31'),
        JournalEntry::SOURCE_MANUAL,
    ))->toThrow(BusinessRuleException::class);

    expect(FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-08-31'),
        JournalEntry::SOURCE_CLOSING,
    ))->toBeInstanceOf(FiscalPeriod::class);

    FiscalPeriod::query()->forceDelete();
    FiscalPeriod::factory()->current()->create([
        'hard_lock_until' => '2026-08-31',
    ]);

    expect(fn () => FiscalPeriod::assertOpenForPosting(
        new DateTimeImmutable('2026-08-31'),
        JournalEntry::SOURCE_CLOSING,
    ))->toThrow(BusinessRuleException::class);
});
