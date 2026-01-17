<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\FollowUpManager;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use Carbon\Carbon;

describe('FollowUpManager', function () {

    beforeEach(function () {
        $this->followUpManager = new FollowUpManager;
    });

    describe('scheduleFollowUp', function () {

        it('schedules follow-up for specified days', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at = null;

                public function save(): void {}
            };

            $this->followUpManager->scheduleFollowUp($quotation, 5);

            expect($quotation->next_follow_up_at)->not->toBeNull();
            expect($quotation->next_follow_up_at->diffInDays(now()))->toBe(5);
        });

        it('defaults to 3 days', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at = null;

                public function save(): void {}
            };

            $this->followUpManager->scheduleFollowUp($quotation);

            expect($quotation->next_follow_up_at->diffInDays(now()))->toBe(3);
        });
    });

    describe('recordContact', function () {

        it('records contact and increments count', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $last_contacted_at = null;

                public ?int $follow_up_count = 2;

                public function save(): void {}
            };

            $this->followUpManager->recordContact($quotation);

            expect($quotation->last_contacted_at)->not->toBeNull();
            expect($quotation->follow_up_count)->toBe(3);
        });

        it('starts count from 0 if null', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $last_contacted_at = null;

                public ?int $follow_up_count = null;

                public function save(): void {}
            };

            $this->followUpManager->recordContact($quotation);

            expect($quotation->follow_up_count)->toBe(1);
        });
    });

    describe('calculateAutoFollowUpDate', function () {

        it('returns null when outcome is set', function () {
            $quotation = new class(['outcome' => 'won']) extends Quotation {};

            $result = $this->followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->toBeNull();
        });

        it('returns 3 days for submitted status', function () {
            $quotation = new class extends Quotation
            {
                public ?string $outcome = null;

                public DocumentStatus $status = DocumentStatus::Submitted;
            };

            $result = $this->followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->not->toBeNull();
            $days = abs(round($result->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(2);
            expect($days)->toBeLessThanOrEqual(4);
        });

        it('returns 7 days for approved status', function () {
            $quotation = new class extends Quotation
            {
                public ?string $outcome = null;

                public DocumentStatus $status = DocumentStatus::Approved;
            };

            $result = $this->followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->not->toBeNull();
            $days = abs(round($result->diffInDays(now())));
            expect($days)->toBeGreaterThanOrEqual(6);
            expect($days)->toBeLessThanOrEqual(8);
        });

        it('returns null for draft status', function () {
            $quotation = new class extends Quotation
            {
                public ?string $outcome = null;

                public DocumentStatus $status = DocumentStatus::Draft;
            };

            $result = $this->followUpManager->calculateAutoFollowUpDate($quotation);

            expect($result)->toBeNull();
        });
    });

    describe('needsFollowUp', function () {

        it('returns false when next_follow_up_at is null', function () {
            $quotation = new class(['next_follow_up_at' => null, 'outcome' => null]) extends Quotation {};

            expect($this->followUpManager->needsFollowUp($quotation))->toBeFalse();
        });

        it('returns true when date is past and no outcome', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at;

                public ?string $outcome = null;

                public function __construct()
                {
                    $this->next_follow_up_at = Carbon::now()->subDays(1);
                }
            };

            expect($this->followUpManager->needsFollowUp($quotation))->toBeTrue();
        });

        it('returns false when outcome is set', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at;

                public ?string $outcome;

                public function __construct()
                {
                    $this->next_follow_up_at = Carbon::now()->subDays(1);
                    $this->outcome = 'won';
                }
            };

            expect($this->followUpManager->needsFollowUp($quotation))->toBeFalse();
        });

        it('returns false when date is in future', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at;

                public ?string $outcome = null;

                public function __construct()
                {
                    $this->next_follow_up_at = Carbon::now()->addDays(5);
                }
            };

            expect($this->followUpManager->needsFollowUp($quotation))->toBeFalse();
        });
    });

    describe('getDaysSinceLastContact', function () {

        it('returns null when last_contacted_at is null', function () {
            $quotation = new class(['last_contacted_at' => null]) extends Quotation {};

            expect($this->followUpManager->getDaysSinceLastContact($quotation))->toBeNull();
        });

        it('returns correct days when last contacted', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $last_contacted_at;

                public function __construct()
                {
                    $this->last_contacted_at = Carbon::now()->subDays(5);
                }
            };

            expect($this->followUpManager->getDaysSinceLastContact($quotation))->toBe(5);
        });
    });

    describe('isOverdue', function () {

        it('returns false when next_follow_up_at is null', function () {
            $quotation = new class(['next_follow_up_at' => null]) extends Quotation {};

            expect($this->followUpManager->isOverdue($quotation))->toBeFalse();
        });

        it('returns true when date is past', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at;

                public function __construct()
                {
                    $this->next_follow_up_at = Carbon::now()->subDays(1);
                }
            };

            expect($this->followUpManager->isOverdue($quotation))->toBeTrue();
        });

        it('returns false when date is in future', function () {
            $quotation = new class extends Quotation
            {
                public ?\DateTime $next_follow_up_at;

                public function __construct()
                {
                    $this->next_follow_up_at = Carbon::now()->addDays(5);
                }
            };

            expect($this->followUpManager->isOverdue($quotation))->toBeFalse();
        });
    });

    describe('getPriorityLabel', function () {

        it('returns correct label for low', function () {
            expect($this->followUpManager->getPriorityLabel('low'))->toBe('Rendah');
        });

        it('returns correct label for normal', function () {
            expect($this->followUpManager->getPriorityLabel('normal'))->toBe('Normal');
        });

        it('returns correct label for high', function () {
            expect($this->followUpManager->getPriorityLabel('high'))->toBe('Tinggi');
        });

        it('returns correct label for urgent', function () {
            expect($this->followUpManager->getPriorityLabel('urgent'))->toBe('Mendesak');
        });

        it('returns default for unknown', function () {
            expect($this->followUpManager->getPriorityLabel('unknown'))->toBe('Normal');
        });
    });

    describe('isHighPriority', function () {

        it('returns true for high priority', function () {
            expect($this->followUpManager->isHighPriority('high'))->toBeTrue();
        });

        it('returns true for urgent priority', function () {
            expect($this->followUpManager->isHighPriority('urgent'))->toBeTrue();
        });

        it('returns false for normal priority', function () {
            expect($this->followUpManager->isHighPriority('normal'))->toBeFalse();
        });

        it('returns false for low priority', function () {
            expect($this->followUpManager->isHighPriority('low'))->toBeFalse();
        });
    });

    describe('constants', function () {

        it('defines PRIORITY_LOW correctly', function () {
            expect(FollowUpManager::PRIORITY_LOW)->toBe('low');
        });

        it('defines PRIORITY_NORMAL correctly', function () {
            expect(FollowUpManager::PRIORITY_NORMAL)->toBe('normal');
        });

        it('defines PRIORITY_HIGH correctly', function () {
            expect(FollowUpManager::PRIORITY_HIGH)->toBe('high');
        });

        it('defines PRIORITY_URGENT correctly', function () {
            expect(FollowUpManager::PRIORITY_URGENT)->toBe('urgent');
        });

        it('defines PRIORITIES array', function () {
            expect(FollowUpManager::PRIORITIES)->toEqual(['low', 'normal', 'high', 'urgent']);
        });
    });
});
