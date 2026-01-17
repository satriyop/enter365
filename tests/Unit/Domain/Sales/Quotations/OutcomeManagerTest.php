<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\OutcomeManager;
use App\Models\Sales\Quotation;

describe('OutcomeManager', function () {

    $outcomeManager = new OutcomeManager;

    describe('getOutcomeLabel', function () use ($outcomeManager) {

        it('returns correct label for won', function () use ($outcomeManager) {
            expect($outcomeManager->getOutcomeLabel('won'))->toBe('Menang');
        });

        it('returns correct label for lost', function () use ($outcomeManager) {
            expect($outcomeManager->getOutcomeLabel('lost'))->toBe('Kalah');
        });

        it('returns correct label for cancelled', function () use ($outcomeManager) {
            expect($outcomeManager->getOutcomeLabel('cancelled'))->toBe('Dibatalkan');
        });

        it('returns null for unknown outcome', function () use ($outcomeManager) {
            expect($outcomeManager->getOutcomeLabel('unknown'))->toBeNull();
        });

        it('returns null for null outcome', function () use ($outcomeManager) {
            expect($outcomeManager->getOutcomeLabel(null))->toBeNull();
        });
    });

    describe('hasOutcome', function () use ($outcomeManager) {

        it('returns true when outcome is set', function () use ($outcomeManager) {
            $quotation = new class(['outcome' => 'won']) extends Quotation {};
            expect($outcomeManager->hasOutcome($quotation))->toBeTrue();
        });

        it('returns false when outcome is null', function () use ($outcomeManager) {
            $quotation = new class(['outcome' => null]) extends Quotation {};
            expect($outcomeManager->hasOutcome($quotation))->toBeFalse();
        });
    });

    describe('wonReasons helper', function () use ($outcomeManager) {

        it('returns WON_REASONS from QuotationOutcome enum', function () use ($outcomeManager) {
            $reasons = $outcomeManager->wonReasons();

            expect($reasons)->toHaveKey('harga_kompetitif');
            expect($reasons)->toHaveKey('kualitas_produk');
            expect($reasons)->toHaveKey('layanan_baik');
        });

        it('has Indonesian labels', function () use ($outcomeManager) {
            $reasons = $outcomeManager->wonReasons();

            expect($reasons['harga_kompetitif'])->toBe('Harga Kompetitif');
        });
    });

    describe('lostReasons helper', function () use ($outcomeManager) {

        it('returns LOST_REASONS from QuotationOutcome enum', function () use ($outcomeManager) {
            $reasons = $outcomeManager->lostReasons();

            expect($reasons)->toHaveKey('harga_tinggi');
            expect($reasons)->toHaveKey('kalah_kompetitor');
        });

        it('has Indonesian labels', function () use ($outcomeManager) {
            $reasons = $outcomeManager->lostReasons();

            expect($reasons['harga_tinggi'])->toBe('Harga Terlalu Tinggi');
        });
    });

    describe('constants moved to QuotationOutcome enum', function () {

        it('OUTCOME_WON is now QuotationOutcome::Won', function () {
            expect(QuotationOutcome::Won->value)->toBe('won');
        });

        it('OUTCOME_LOST is now QuotationOutcome::Lost', function () {
            expect(QuotationOutcome::Lost->value)->toBe('lost');
        });

        it('OUTCOME_CANCELLED is now QuotationOutcome::Cancelled', function () {
            expect(QuotationOutcome::Cancelled->value)->toBe('cancelled');
        });

        it('WON_REASONS is now QuotationOutcome::WON_REASONS', function () {
            expect(QuotationOutcome::WON_REASONS)->toBeArray();
            expect(QuotationOutcome::WON_REASONS)->toHaveKey('harga_kompetitif');
        });

        it('LOST_REASONS is now QuotationOutcome::LOST_REASONS', function () {
            expect(QuotationOutcome::LOST_REASONS)->toBeArray();
            expect(QuotationOutcome::LOST_REASONS)->toHaveKey('harga_tinggi');
        });
    });
});
