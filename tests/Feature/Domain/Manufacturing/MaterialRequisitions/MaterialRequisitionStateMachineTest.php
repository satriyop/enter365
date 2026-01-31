<?php

declare(strict_types=1);

use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionApproved;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionCancelled;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionIssued;
use App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged;
use App\Domain\Manufacturing\MaterialRequisitions\MaterialRequisitionStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

/*
|--------------------------------------------------------------------------
| MaterialRequisitionStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the material requisition state machine including transitions,
| guards, actions, event dispatching, and business rule helpers.
|
*/

describe('MaterialRequisitionStateMachine transitions', function () {
    it('allows transition from draft to approved when has items', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('does not prevent transition from draft to approved in canTransitionTo when has no items', function () {
        // Note: MaterialRequisitionStateMachine uses beforeApproved hook instead of guards,
        // so canTransitionTo returns true, but actual transition will throw exception
        $mr = MaterialRequisition::factory()->draft()->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('allows transition from approved to issued', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Issued))->toBeTrue();
    });

    it('allows transition from approved to partial', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });

    it('allows transition from partial to issued', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->partial()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Issued))->toBeTrue();
    });

    it('allows cancellation from draft', function () {
        $mr = MaterialRequisition::factory()->draft()->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows cancellation from approved', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows cancellation from partial', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->partial()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        expect($mr->fresh()->status)->toBe(DocumentStatus::Approved);
    });

    it('throws exception on invalid transition', function () {
        $mr = MaterialRequisition::factory()->draft()->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);

        $stateMachine->transitionTo(DocumentStatus::Issued);
    })->throws(StateTransitionException::class);

    it('throws exception when approving without items', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Material requisition tidak dapat disetujui karena tidak memiliki item.');
});

describe('MaterialRequisitionStateMachine actions', function () {
    it('updates approved_by and approved_at when approving', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        $mr->refresh();
        expect($mr->approved_by)->toBe($user->id);
        expect($mr->approved_at)->not->toBeNull();
    });

    it('updates issued_by and issued_at when issuing', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Issued, ['user_id' => $user->id]);

        $mr->refresh();
        expect($mr->issued_by)->toBe($user->id);
        expect($mr->issued_at)->not->toBeNull();
    });
});

describe('MaterialRequisitionStateMachine event dispatching', function () {
    it('dispatches MaterialRequisitionApproved event when approving', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(MaterialRequisitionApproved::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(MaterialRequisitionApproved::class);
        expect($event->materialRequisitionId)->toBe($mr->id);
        expect($event->requisitionNumber)->toBe($mr->requisition_number);
    });

    it('dispatches MaterialRequisitionIssued event when issuing', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Issued, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(MaterialRequisitionIssued::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(MaterialRequisitionIssued::class);
        expect($event->materialRequisitionId)->toBe($mr->id);
    });

    it('dispatches MaterialRequisitionCancelled event when cancelling', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'Test cancellation',
        ]);

        expect($eventDispatcher->dispatchCount(MaterialRequisitionCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(MaterialRequisitionCancelled::class);
        expect($event->materialRequisitionId)->toBe($mr->id);
        expect($event->reason)->toBe('Test cancellation');
    });

    it('dispatches MaterialRequisitionStatusChanged event on every transition', function () {
        $user = User::factory()->create();
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(MaterialRequisitionStatusChanged::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(MaterialRequisitionStatusChanged::class);
        expect($event->materialRequisitionId)->toBe($mr->id);
        expect($event->fromStatus)->toBe(DocumentStatus::Draft);
        expect($event->toStatus)->toBe(DocumentStatus::Approved);
    });
});

describe('MaterialRequisitionStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for approved material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Issued->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Partial->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for partial material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->partial()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Partial->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Issued->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for issued material requisition', function () {
        $mr = MaterialRequisition::factory()->issued()->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Issued->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $mr = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = MaterialRequisitionStateMachine::fromMaterialRequisition($mr);
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

describe('MaterialRequisitionStateMachine business helpers', function () {
    it('reports canApprove correctly', function () {
        $withItems = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->draft()
            ->create();
        $withoutItems = MaterialRequisition::factory()->draft()->create();
        $approved = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($withItems)->canApprove())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($withoutItems)->canApprove())->toBeFalse();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($approved)->canApprove())->toBeFalse();
    });

    it('reports canIssue correctly', function () {
        $approved = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();
        $partial = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->partial()
            ->create();
        $draft = MaterialRequisition::factory()->draft()->create();

        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($approved)->canIssue())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($partial)->canIssue())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($draft)->canIssue())->toBeFalse();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draft = MaterialRequisition::factory()->draft()->create();
        $approved = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();
        $partial = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->partial()
            ->create();
        $issued = MaterialRequisition::factory()->issued()->create();

        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($draft)->canCancel())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($approved)->canCancel())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($partial)->canCancel())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($issued)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draft = MaterialRequisition::factory()->draft()->create();
        $approved = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($draft)->canEdit())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($approved)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draft = MaterialRequisition::factory()->draft()->create();
        $approved = MaterialRequisition::factory()
            ->has(MaterialRequisitionItem::factory(), 'items')
            ->approved()
            ->create();

        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($draft)->canDelete())->toBeTrue();
        expect(MaterialRequisitionStateMachine::fromMaterialRequisition($approved)->canDelete())->toBeFalse();
    });
});
