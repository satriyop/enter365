<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Domain\Sales\Quotations\FollowUpManager;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use Carbon\Carbon;
use Mockery;

describe('FollowUpManager', function () {

    $followUpManager = new FollowUpManager;

    describe('scheduleFollowUp', function () use ($followUpManager) {

        it('schedules follow-up for specified days', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = null;
            $quotation->shouldReceive('save')->once();

            $followUpManager->scheduleFollowUp($quotation, 5);

            expect($quotation->next_follow_up_at)->not->toBeNull();
            $days = abs(round($quotation->next_follow_up_at->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(4);
            expect($days)->toBeLessThanOrEqual(6);
        });

        it('defaults to 3 days', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = null;
            $quotation->shouldReceive('save')->once();

            $followUpManager->scheduleFollowUp($quotation);

            $days = abs(round($quotation->next_follow_up_at->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(2);
            expect($days)->toBeLessThanOrEqual(4);
        });
    });

    describe('recordContact', function () use ($followUpManager) {

        it('records contact and increments count', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->last_contacted_at = null;
            $quotation->follow_up_count = 2;
            $quotation->shouldReceive('save')->once();

            $followUpManager->recordContact($quotation);

            expect($quotation->last_contacted_at)->not->toBeNull();
            expect($quotation->follow_up_count)->toBe(3);
        });

        it('starts count from 0 if null', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->last_contacted_at = null;
            $quotation->follow_up_count = null;
            $quotation->shouldReceive('save')->once();

            $followUpManager->recordContact($quotation);

            expect($quotation->follow_up_count)->toBe(1);
        });
    });

    describe('calculateAutoFollowUpDate', function () use ($followUpManager) {

        it('returns null when outcome is set', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->outcome = 'won';

            $result = $followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->toBeNull();
        });

        it('returns 3 days for submitted status', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->outcome = null;
            $quotation->status = DocumentStatus::Submitted;

            $result = $followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->not->toBeNull();
            $days = abs(round($result->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(2);
            expect($days)->toBeLessThanOrEqual(4);
        });

        it('returns 7 days for approved status', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->outcome = null;
            $quotation->status = DocumentStatus::Approved;

            $result = $followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->not->toBeNull();
            $days = abs(round($result->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(6);
            expect($days)->toBeLessThanOrEqual(8);
        });

        it('returns null for draft status', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->outcome = null;
            $quotation->status = DocumentStatus::Draft;

            $result = $followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->toBeNull();
        });
    });

    describe('needsFollowUp', function () use ($followUpManager) {

        it('returns false when next_follow_up_at is null', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = null;
            $quotation->outcome = null;

            expect($followUpManager->needsFollowUp($quotation))->toBeFalse();
        });

        it('returns true when date is past and no outcome', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = Carbon::now()->subDays(1);
            $quotation->outcome = null;

            expect($followUpManager->needsFollowUp($quotation))->toBeTrue();
        });

        it('returns false when outcome is set', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = Carbon::now()->subDays(1);
            $quotation->outcome = 'won';

            expect($followUpManager->needsFollowUp($quotation))->toBeFalse();
        });

        it('returns false when date is in future', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = Carbon::now()->addDays(5);
            $quotation->outcome = null;

            expect($followUpManager->needsFollowUp($quotation))->toBeFalse();
        });
    });

    describe('getDaysSinceLastContact', function () use ($followUpManager) {

        it('returns null when last_contacted_at is null', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->last_contacted_at = null;

            expect($followUpManager->getDaysSinceLastContact($quotation))->toBeNull();
        });

        it('returns correct days when last contacted', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->last_contacted_at = Carbon::now()->subDays(5);

            expect($followUpManager->getDaysSinceLastContact($quotation))->toBe(5);
        });
    });

    describe('isOverdue', function () use ($followUpManager) {

        it('returns false when next_follow_up_at is null', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = null;

            expect($followUpManager->isOverdue($quotation))->toBeFalse();
        });

        it('returns true when date is past', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = Carbon::now()->subDays(1);

            expect($followUpManager->isOverdue($quotation))->toBeTrue();
        });

        it('returns false when date is in future', function () use ($followUpManager) {
            $quotation = Mockery::mock(Quotation::class)->makePartial();
            $quotation->next_follow_up_at = Carbon::now()->addDays(5);

            expect($followUpManager->isOverdue($quotation))->toBeFalse();
        });
    });

    describe('getPriorityLabel', function () use ($followUpManager) {

        it('returns correct label for low', function () use ($followUpManager) {
            expect($followUpManager->getPriorityLabel('low'))->toBe('Rendah');
        });

        it('returns correct label for normal', function () use ($followUpManager) {
            expect($followUpManager->getPriorityLabel('normal'))->toBe('Normal');
        });

        it('returns correct label for high', function () use ($followUpManager) {
            expect($followUpManager->getPriorityLabel('high'))->toBe('Tinggi');
        });

        it('returns correct label for urgent', function () use ($followUpManager) {
            expect($followUpManager->getPriorityLabel('urgent'))->toBe('Mendesak');
        });

        it('returns default for unknown', function () use ($followUpManager) {
            expect($followUpManager->getPriorityLabel('unknown'))->toBe('Normal');
        });
    });

    describe('isHighPriority', function () use ($followUpManager) {

        it('returns true for high priority', function () use ($followUpManager) {
            expect($followUpManager->isHighPriority('high'))->toBeTrue();
        });

        it('returns true for urgent priority', function () use ($followUpManager) {
            expect($followUpManager->isHighPriority('urgent'))->toBeTrue();
        });

        it('returns false for normal priority', function () use ($followUpManager) {
            expect($followUpManager->isHighPriority('normal'))->toBeFalse();
        });

        it('returns false for low priority', function () use ($followUpManager) {
            expect($followUpManager->isHighPriority('low'))->toBeFalse();
        });
    });

    describe('constants moved to QuotationPriority enum', function () {

        it('PRIORITY_LOW is now QuotationPriority::Low', function () {
            expect(QuotationPriority::Low->value)->toBe('low');
        });

        it('PRIORITY_NORMAL is now QuotationPriority::Normal', function () {
            expect(QuotationPriority::Normal->value)->toBe('normal');
        });

        it('PRIORITY_HIGH is now QuotationPriority::High', function () {
            expect(QuotationPriority::High->value)->toBe('high');
        });

        it('PRIORITY_URGENT is now QuotationPriority::Urgent', function () {
            expect(QuotationPriority::Urgent->value)->toBe('urgent');
        });

        it('ALL priorities available via QuotationPriority::ALL', function () {
            expect(QuotationPriority::ALL)->toEqual(['low', 'normal', 'high', 'urgent']);
        });
    });
});
