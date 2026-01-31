<?php

declare(strict_types=1);

use App\Domain\Purchasing\GoodsReceiptNotes\Events\GoodsReceiptNoteStatusChanged;
use App\Domain\Purchasing\GoodsReceiptNotes\GoodsReceiptNoteStateMachine;
use App\Enums\DocumentStatus;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GoodsReceiptNoteStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the goods receipt note state machine including transitions,
| guards, actions, workflow metadata, and business rule helpers.
|
*/

describe('GoodsReceiptNoteStateMachine transitions', function () {
    it('allows transition from draft to receiving when GRN has items', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Receiving))->toBeTrue();
    });

    it('throws exception when transitioning from draft to receiving without items', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        $stateMachine->transitionTo(DocumentStatus::Receiving, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'GRN tidak memiliki item untuk diterima.');

    it('allows transition from draft to completed when items received', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('throws exception when transitioning from draft to completed without received items', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 0]), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'GRN tidak dapat diselesaikan. Pastikan ada item yang telah diterima.');

    it('allows transition from draft to cancelled', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from receiving to completed when items received', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->receiving()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('allows transition from receiving to cancelled', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->receiving()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('blocks transition from completed to any other status', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->completed()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Receiving))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
    });

    it('blocks transition from cancelled to any other status', function () {
        $grn = GoodsReceiptNote::factory()->cancelled()->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Receiving))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Receiving, ['user_id' => $user->id]);

        expect($grn->fresh()->status)->toBe(DocumentStatus::Receiving);
    });

    it('throws exception on invalid transition', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        $stateMachine->transitionTo(DocumentStatus::Receiving);
    })->throws(\InvalidArgumentException::class);
});

describe('GoodsReceiptNoteStateMachine guards', function () {
    it('requires items to start receiving', function () {
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        $stateMachine->transitionTo(DocumentStatus::Receiving, ['user_id' => 1]);
    })->throws(\InvalidArgumentException::class, 'GRN tidak memiliki item untuk diterima.');

    it('allows receiving transition with items', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Receiving))->toBeTrue();
    });

    it('requires at least one item with quantity received to complete', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 0]), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => 1]);
    })->throws(\InvalidArgumentException::class, 'GRN tidak dapat diselesaikan. Pastikan ada item yang telah diterima.');

    it('allows complete transition when items have received quantity', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });
});

describe('GoodsReceiptNoteStateMachine actions', function () {
    it('dispatches GoodsReceiptNoteStatusChanged event when transitioning', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Receiving, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(GoodsReceiptNoteStatusChanged::class))->toBeGreaterThanOrEqual(1);
    });

    it('updates received_by timestamp when starting receiving', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Receiving, ['user_id' => $user->id]);

        $grn->refresh();
        expect($grn->received_by)->toBe($user->id);
    });

    it('updates completed_at and checked_by when completing', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        $grn->refresh();
        expect($grn->checked_by)->toBe($user->id);
        expect($grn->completed_at)->not->toBeNull();
    });

    it('updates cancelled_at when cancelling', function () {
        $user = User::factory()->create();
        $grn = GoodsReceiptNote::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, ['user_id' => $user->id]);

        $grn->refresh();
        expect($grn->cancelled_at)->not->toBeNull();
    });
});

describe('GoodsReceiptNoteStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Receiving->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for receiving GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->receiving()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Receiving->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Completed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for completed GRN', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->completed()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Completed->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $grn = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata)->toHaveKey('current_status');
        expect($metadata)->toHaveKey('next_statuses');
        expect($metadata)->toHaveKey('all_statuses');
        expect($metadata)->toHaveKey('transitions');
        expect($metadata['current_status'])->toHaveKey('value');
        expect($metadata['current_status'])->toHaveKey('label');
        expect($metadata['current_status'])->toHaveKey('color');
    });
});

describe('GoodsReceiptNoteStateMachine business helpers', function () {
    it('reports canStartReceiving correctly', function () {
        $grnWithItems = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->draft()
            ->create();
        $grnWithoutItems = GoodsReceiptNote::factory()->draft()->create();
        $receivingGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->receiving()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithItems)->canStartReceiving())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithoutItems)->canStartReceiving())->toBeFalse();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($receivingGRN)->canStartReceiving())->toBeFalse();
    });

    it('reports canComplete correctly from draft', function () {
        $grnWithReceivedItems = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->draft()
            ->create();
        $grnWithoutReceivedItems = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 0]), 'items')
            ->draft()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithReceivedItems)->canComplete())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithoutReceivedItems)->canComplete())->toBeFalse();
    });

    it('reports canComplete correctly from receiving', function () {
        $grnWithReceivedItems = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->receiving()
            ->create();
        $grnWithoutReceivedItems = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 0]), 'items')
            ->receiving()
            ->create();
        $completedGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->completed()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithReceivedItems)->canComplete())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($grnWithoutReceivedItems)->canComplete())->toBeFalse();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($completedGRN)->canComplete())->toBeFalse();
    });

    it('reports canCancel correctly', function () {
        $draftGRN = GoodsReceiptNote::factory()->draft()->create();
        $receivingGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->receiving()
            ->create();
        $completedGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->completed()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($draftGRN)->canCancel())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($receivingGRN)->canCancel())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($completedGRN)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draftGRN = GoodsReceiptNote::factory()->draft()->create();
        $receivingGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->receiving()
            ->create();
        $completedGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory()->state(['quantity_received' => 10]), 'items')
            ->completed()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($draftGRN)->canEdit())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($receivingGRN)->canEdit())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($completedGRN)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftGRN = GoodsReceiptNote::factory()->draft()->create();
        $receivingGRN = GoodsReceiptNote::factory()
            ->has(GoodsReceiptNoteItem::factory(), 'items')
            ->receiving()
            ->create();

        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($draftGRN)->canDelete())->toBeTrue();
        expect(GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($receivingGRN)->canDelete())->toBeFalse();
    });
});
