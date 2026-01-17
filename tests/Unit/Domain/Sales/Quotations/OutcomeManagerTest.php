<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\OutcomeManager;
use App\Models\Sales\Quotation;

describe('OutcomeManager', function () {

    beforeEach(function () {
        $this->outcomeManager = new OutcomeManager;
    });

    describe('getOutcomeLabel', function () {

        it('returns correct label for won', function () {
            expect($this->outcomeManager->getOutcomeLabel('won'))->toBe('Menang');
        });

        it('returns correct label for lost', function () {
            expect($this->outcomeManager->getOutcomeLabel('lost'))->toBe('Kalah');
        });

        it('returns correct label for cancelled', function () {
            expect($this->outcomeManager->getOutcomeLabel('cancelled'))->toBe('Dibatalkan');
        });

        it('returns null for unknown outcome', function () {
            expect($this->outcomeManager->getOutcomeLabel('unknown'))->toBeNull();
        });

        it('returns null for null outcome', function () {
            expect($this->outcomeManager->getOutcomeLabel(null))->toBeNull();
        });
    });

    describe('hasOutcome', function () {

        it('returns true when outcome is set', function () {
            $quotation = new class(['outcome' => 'won']) extends Quotation {};
            expect($this->outcomeManager->hasOutcome($quotation))->toBeTrue();
        });

        it('returns false when outcome is null', function () {
            $quotation = new class(['outcome' => null]) extends Quotation {};
            expect($this->outcomeManager->hasOutcome($quotation))->toBeFalse();
        });
    });

    describe('WON_REASONS constant', function () {

        it('contains expected won reasons', function () {
            $reasons = OutcomeManager::WON_REASONS;

            expect($reasons)->toHaveKey('harga_kompetitif');
            expect($reasons)->toHaveKey('kualitas_produk');
            expect($reasons)->toHaveKey('layanan_baik');
        });

        it('has Indonesian labels', function () {
            $reasons = OutcomeManager::WON_REASONS;

            expect($reasons['harga_kompetitif'])->toBe('Harga Kompetitif');
        });
    });

    describe('LOST_REASONS constant', function () {

        it('contains expected lost reasons', function () {
            $reasons = OutcomeManager::LOST_REASONS;

            expect($reasons)->toHaveKey('harga_tinggi');
            expect($reasons)->toHaveKey('kalah_kompetitor');
        });

        it('has Indonesian labels', function () {
            $reasons = OutcomeManager::LOST_REASONS;

            expect($reasons['harga_tinggi'])->toBe('Harga Terlalu Tinggi');
        });
    });

    describe('constants', function () {

        it('defines OUTCOME_WON correctly', function () {
            expect(OutcomeManager::OUTCOME_WON)->toBe('won');
        });

        it('defines OUTCOME_LOST correctly', function () {
            expect(OutcomeManager::OUTCOME_LOST)->toBe('lost');
        });

        it('defines OUTCOME_CANCELLED correctly', function () {
            expect(OutcomeManager::OUTCOME_CANCELLED)->toBe('cancelled');
        });
    });
});
