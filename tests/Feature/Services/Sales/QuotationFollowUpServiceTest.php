<?php

declare(strict_types=1);

use App\Contracts\Sales\QuotationFollowUpServiceInterface;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(QuotationFollowUpServiceInterface::class);
});

describe('scheduleFollowUp', function () {
    it('schedules follow-up with default 3 days', function () {
        $quotation = Quotation::factory()->create(['status' => DocumentStatus::Submitted]);

        $result = $this->service->scheduleFollowUp($quotation);

        expect($result->next_follow_up_at)->not->toBeNull()
            ->and($result->next_follow_up_at->isFuture())->toBeTrue();
    });

    it('schedules follow-up with custom days', function () {
        $quotation = Quotation::factory()->create(['status' => DocumentStatus::Submitted]);

        $result = $this->service->scheduleFollowUp($quotation, 7);

        expect($result->next_follow_up_at)->not->toBeNull()
            ->and($result->next_follow_up_at->isFuture())->toBeTrue();
    });
});

describe('scheduleFollowUpAt', function () {
    it('schedules follow-up at specific date', function () {
        $quotation = Quotation::factory()->create(['status' => DocumentStatus::Submitted]);
        $targetDate = now()->addDays(10)->toDateString();

        $result = $this->service->scheduleFollowUpAt($quotation, $targetDate);

        expect($result->next_follow_up_at)->not->toBeNull();
    });
});

describe('clearFollowUp', function () {
    it('clears scheduled follow-up', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'next_follow_up_at' => now()->addDays(3),
        ]);

        $result = $this->service->clearFollowUp($quotation);

        expect($result->next_follow_up_at)->toBeNull();
    });
});

describe('recordContact', function () {
    it('records contact activity and increments count', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'follow_up_count' => 0,
            'last_contacted_at' => null,
        ]);

        $result = $this->service->recordContact($quotation);

        expect($result->follow_up_count)->toBe(1)
            ->and($result->last_contacted_at)->not->toBeNull();
    });

    it('increments existing follow-up count', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'follow_up_count' => 3,
            'last_contacted_at' => now()->subDays(5),
        ]);

        $result = $this->service->recordContact($quotation);

        expect($result->follow_up_count)->toBe(4);
    });
});

describe('storeActivity', function () {
    it('creates activity, increments contact tracking, and schedules follow-up', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'follow_up_count' => 0,
            'last_contacted_at' => null,
            'next_follow_up_at' => null,
        ]);

        $nextFollowUp = now()->addDays(5)->toDateTimeString();

        $activity = $this->service->storeActivity($quotation, [
            'type' => QuotationActivity::TYPE_CALL,
            'description' => 'Follow-up call',
            'activity_at' => now()->toDateTimeString(),
            'next_follow_up_at' => $nextFollowUp,
        ], $this->user->id);

        expect($activity)->toBeInstanceOf(QuotationActivity::class)
            ->and($activity->type)->toBe(QuotationActivity::TYPE_CALL)
            ->and($activity->user_id)->toBe($this->user->id);

        $quotation->refresh();
        expect($quotation->follow_up_count)->toBe(1)
            ->and($quotation->last_contacted_at)->not->toBeNull()
            ->and($quotation->next_follow_up_at)->not->toBeNull();
    });
});

describe('scheduleFollowUpWithNotes', function () {
    it('schedules follow-up and records activity when notes provided', function () {
        $quotation = Quotation::factory()->create(['status' => DocumentStatus::Submitted]);
        $target = now()->addDays(4)->toDateTimeString();

        $result = $this->service->scheduleFollowUpWithNotes($quotation, [
            'next_follow_up_at' => $target,
            'notes' => 'Call again next week',
        ], $this->user->id);

        expect($result->next_follow_up_at)->not->toBeNull();

        $activity = QuotationActivity::where('quotation_id', $quotation->id)->first();
        expect($activity)->not->toBeNull()
            ->and($activity->type)->toBe(QuotationActivity::TYPE_FOLLOW_UP_SCHEDULED)
            ->and($activity->description)->toBe('Call again next week')
            ->and($activity->user_id)->toBe($this->user->id);
    });

    it('schedules follow-up without activity when notes empty', function () {
        $quotation = Quotation::factory()->create(['status' => DocumentStatus::Submitted]);

        $this->service->scheduleFollowUpWithNotes($quotation, [
            'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
        ], $this->user->id);

        expect(QuotationActivity::where('quotation_id', $quotation->id)->count())->toBe(0);
    });
});

describe('calculateAutoFollowUpDate', function () {
    it('returns 3 days for submitted quotation', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'outcome' => null,
        ]);

        $date = $this->service->calculateAutoFollowUpDate($quotation);

        expect($date)->not->toBeNull();
    });

    it('returns 7 days for approved quotation', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Approved,
            'outcome' => null,
        ]);

        $date = $this->service->calculateAutoFollowUpDate($quotation);

        expect($date)->not->toBeNull();
    });

    it('returns null for quotation with outcome', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'outcome' => 'won',
        ]);

        $date = $this->service->calculateAutoFollowUpDate($quotation);

        expect($date)->toBeNull();
    });

    it('returns null for draft quotation', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Draft,
            'outcome' => null,
        ]);

        $date = $this->service->calculateAutoFollowUpDate($quotation);

        expect($date)->toBeNull();
    });
});

describe('setAutoFollowUpIfNeeded', function () {
    it('sets auto follow-up for submitted quotation without existing follow-up', function () {
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'outcome' => null,
            'next_follow_up_at' => null,
        ]);

        $result = $this->service->setAutoFollowUpIfNeeded($quotation);

        expect($result->next_follow_up_at)->not->toBeNull();
    });

    it('does not override existing follow-up', function () {
        $existingDate = now()->addDays(10);
        $quotation = Quotation::factory()->create([
            'status' => DocumentStatus::Submitted,
            'next_follow_up_at' => $existingDate,
        ]);

        $result = $this->service->setAutoFollowUpIfNeeded($quotation);

        expect($result->next_follow_up_at->toDateString())->toBe($existingDate->toDateString());
    });
});

describe('assignTo', function () {
    it('assigns quotation to a user', function () {
        $quotation = Quotation::factory()->create();
        $assignee = User::factory()->create();

        $result = $this->service->assignTo($quotation, $assignee->id);

        expect($result->assigned_to)->toBe($assignee->id);
    });
});

describe('updatePriority', function () {
    it('updates quotation priority', function () {
        $quotation = Quotation::factory()->create(['priority' => QuotationPriority::Normal]);

        $result = $this->service->updatePriority($quotation, 'high');

        expect($result->priority)->toBe(QuotationPriority::High);
    });
});

describe('needsFollowUp', function () {
    it('returns true when follow-up is past due', function () {
        $quotation = Quotation::factory()->create([
            'next_follow_up_at' => now()->subDay(),
            'outcome' => null,
        ]);

        expect($this->service->needsFollowUp($quotation))->toBeTrue();
    });

    it('returns false when no follow-up scheduled', function () {
        $quotation = Quotation::factory()->create([
            'next_follow_up_at' => null,
        ]);

        expect($this->service->needsFollowUp($quotation))->toBeFalse();
    });

    it('returns false when follow-up is in the future', function () {
        $quotation = Quotation::factory()->create([
            'next_follow_up_at' => now()->addDays(3),
            'outcome' => null,
        ]);

        expect($this->service->needsFollowUp($quotation))->toBeFalse();
    });
});

describe('getDaysSinceLastContact', function () {
    it('returns null when never contacted', function () {
        $quotation = Quotation::factory()->create([
            'last_contacted_at' => null,
        ]);

        expect($this->service->getDaysSinceLastContact($quotation))->toBeNull();
    });

    it('returns days since last contact', function () {
        $quotation = Quotation::factory()->create([
            'last_contacted_at' => now()->subDays(5),
        ]);

        expect($this->service->getDaysSinceLastContact($quotation))->toBe(5);
    });
});
