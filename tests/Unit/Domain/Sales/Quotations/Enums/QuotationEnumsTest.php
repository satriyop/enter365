<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Domain\Sales\Quotations\Enums\QuotationType;

describe('QuotationEnums', function () {

    describe('QuotationOutcome', function () {

        it('defines correct enum values', function () {
            expect(QuotationOutcome::Won->value)->toBe('won');
            expect(QuotationOutcome::Lost->value)->toBe('lost');
            expect(QuotationOutcome::Cancelled->value)->toBe('cancelled');
        });

        it('returns correct labels', function () {
            expect(QuotationOutcome::Won->label())->toBe('Menang');
            expect(QuotationOutcome::Lost->label())->toBe('Kalah');
            expect(QuotationOutcome::Cancelled->label())->toBe('Dibatalkan');
        });

        it('contains won reasons', function () {
            $reasons = QuotationOutcome::WON_REASONS;

            expect($reasons)->toHaveKey('harga_kompetitif');
            expect($reasons)->toHaveKey('kualitas_produk');
            expect($reasons)->toHaveKey('layanan_baik');
            expect($reasons['harga_kompetitif'])->toBe('Harga Kompetitif');
        });

        it('contains lost reasons', function () {
            $reasons = QuotationOutcome::LOST_REASONS;

            expect($reasons)->toHaveKey('harga_tinggi');
            expect($reasons)->toHaveKey('kalah_kompetitor');
            expect($reasons['harga_tinggi'])->toBe('Harga Terlalu Tinggi');
        });

        it('can cast from string', function () {
            $outcome = QuotationOutcome::from('won');

            expect($outcome)->toBe(QuotationOutcome::Won);
        });
    });

    describe('QuotationPriority', function () {

        it('defines correct enum values', function () {
            expect(QuotationPriority::Low->value)->toBe('low');
            expect(QuotationPriority::Normal->value)->toBe('normal');
            expect(QuotationPriority::High->value)->toBe('high');
            expect(QuotationPriority::Urgent->value)->toBe('urgent');
        });

        it('returns correct labels', function () {
            expect(QuotationPriority::Low->label())->toBe('Rendah');
            expect(QuotationPriority::Normal->label())->toBe('Normal');
            expect(QuotationPriority::High->label())->toBe('Tinggi');
            expect(QuotationPriority::Urgent->label())->toBe('Mendesak');
        });

        it('identifies high priority correctly', function () {
            expect(QuotationPriority::High->isHigh())->toBeTrue();
            expect(QuotationPriority::Urgent->isHigh())->toBeTrue();
            expect(QuotationPriority::Normal->isHigh())->toBeFalse();
            expect(QuotationPriority::Low->isHigh())->toBeFalse();
        });

        it('defines ALL constant', function () {
            expect(QuotationPriority::ALL)->toEqual(['low', 'normal', 'high', 'urgent']);
        });

        it('can cast from string', function () {
            $priority = QuotationPriority::from('high');

            expect($priority)->toBe(QuotationPriority::High);
        });
    });

    describe('QuotationType', function () {

        it('defines correct enum values', function () {
            expect(QuotationType::Single->value)->toBe('single');
            expect(QuotationType::MultiOption->value)->toBe('multi_option');
        });

        it('returns correct labels', function () {
            expect(QuotationType::Single->label())->toBe('Single');
            expect(QuotationType::MultiOption->label())->toBe('Multi Option');
        });

        it('can cast from string', function () {
            $type = QuotationType::from('single');

            expect($type)->toBe(QuotationType::Single);
        });
    });
});
