<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('HasStatusHistory trait', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->invoice = Invoice::factory()->create([
            'status' => DocumentStatus::Draft,
        ]);
    });

    it('records status change', function () {
        $history = $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id,
            ['note' => 'Test note']
        );

        expect($history)->not->toBeNull();
        expect($history->from_status)->toBe(DocumentStatus::Draft);
        expect($history->to_status)->toBe(DocumentStatus::Sent);
        expect($history->user_id)->toBe($this->user->id);
        expect($history->context)->toBe(['note' => 'Test note']);
    });

    it('uses auth user when user_id not provided', function () {
        $this->actingAs($this->user);

        $history = $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value
        );

        expect($history->user_id)->toBe($this->user->id);
    });

    it('returns status histories ordered by created_at desc', function () {
        // Create first history entry
        $firstHistory = $this->invoice->statusHistories()->create([
            'from_status' => DocumentStatus::Draft->value,
            'to_status' => DocumentStatus::Sent->value,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        // Create second history entry (more recent)
        $secondHistory = $this->invoice->statusHistories()->create([
            'from_status' => DocumentStatus::Sent->value,
            'to_status' => DocumentStatus::Partial->value,
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $histories = $this->invoice->statusHistories()->get();

        expect($histories)->toHaveCount(2);
        expect($histories->first()->to_status)->toBe(DocumentStatus::Partial);
        expect($histories->last()->to_status)->toBe(DocumentStatus::Sent);
    });

    it('returns formatted status timeline', function () {
        $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id,
            ['note' => 'Sent to customer']
        );

        $timeline = $this->invoice->getStatusTimeline();

        expect($timeline)->toHaveCount(1);
        expect($timeline[0])->toHaveKeys(['from', 'to', 'description', 'user', 'at']);
        expect($timeline[0]['from'])->toBe(DocumentStatus::Draft->label());
        expect($timeline[0]['to'])->toBe(DocumentStatus::Sent->label());
        expect($timeline[0]['user'])->toBe($this->user->name);
    });

    it('returns latest status change', function () {
        // Create first history entry (older)
        $this->invoice->statusHistories()->create([
            'from_status' => DocumentStatus::Draft->value,
            'to_status' => DocumentStatus::Sent->value,
            'user_id' => $this->user->id,
            'created_at' => now()->subMinutes(5),
        ]);

        // Create second history entry (more recent)
        $this->invoice->statusHistories()->create([
            'from_status' => DocumentStatus::Sent->value,
            'to_status' => DocumentStatus::Paid->value,
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $latest = $this->invoice->getLatestStatusChange();

        expect($latest->to_status)->toBe(DocumentStatus::Paid);
    });

    it('checks if transitioned to status', function () {
        $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id
        );

        expect($this->invoice->hasTransitionedTo(DocumentStatus::Sent->value))->toBeTrue();
        expect($this->invoice->hasTransitionedTo(DocumentStatus::Paid->value))->toBeFalse();
    });

    it('returns status change count', function () {
        $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id
        );

        $this->invoice->recordStatusChange(
            DocumentStatus::Sent->value,
            DocumentStatus::Partial->value,
            $this->user->id
        );

        expect($this->invoice->getStatusChangeCount())->toBe(2);
    });

    it('handles null context', function () {
        $history = $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id
        );

        expect($history->context)->toBeNull();
    });

    it('belongs to a user', function () {
        $history = $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id
        );

        expect($history->user->id)->toBe($this->user->id);
    });

    it('returns statusable model', function () {
        $history = $this->invoice->recordStatusChange(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            $this->user->id
        );

        expect($history->statusable->id)->toBe($this->invoice->id);
        expect($history->statusable)->toBeInstanceOf(Invoice::class);
    });
});
