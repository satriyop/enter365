<?php

declare(strict_types=1);

use App\Domain\Purchasing\Bills\BillStateMachine;
use App\Domain\Purchasing\Bills\Events\BillReceived;
use App\Domain\Purchasing\Bills\Events\BillStatusChanged;
use App\Domain\Purchasing\Bills\Events\BillVoided;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| BillStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the bill state machine including transitions, guards,
| actions, workflow metadata, and business rule helpers.
|
*/

describe('BillStateMachine transitions', function () {
    it('allows transition from draft to received when bill has items', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Received))->toBeTrue();
    });

    it('blocks transition from draft to received when bill has no items', function () {
        $bill = Bill::factory()->draft()->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Received))->toBeFalse();
    });

    it('allows transition from draft to cancelled', function () {
        $bill = Bill::factory()->draft()->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from received to partial with partial payment', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $bill->update(['paid_amount' => (int) ($bill->total_amount * 0.5)]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });

    it('allows transition from received to paid when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $bill->update(['paid_amount' => $bill->total_amount]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeTrue();
    });

    it('allows transition from received to overdue when past due date', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->subDay()]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeTrue();
    });

    it('allows transition from partial to paid when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->partial()
            ->create();

        $bill->update(['paid_amount' => $bill->total_amount]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeTrue();
    });

    it('allows transition from partial to overdue when past due date', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->partial()
            ->create(['due_date' => now()->subDay()]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeTrue();
    });

    it('allows transition from overdue to partial with partial payment', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->overdue()
            ->create();

        $bill->update(['paid_amount' => (int) ($bill->total_amount * 0.5)]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });

    it('allows transition from overdue to paid when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->overdue()
            ->create();

        $bill->update(['paid_amount' => $bill->total_amount]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeTrue();
    });

    it('allows transition from paid to cancelled', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->paid()
            ->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('blocks transition from cancelled to any other status', function () {
        $bill = Bill::factory()->cancelled()->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Received))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = BillStateMachine::fromBill($bill, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Received, ['user_id' => $user->id]);

        expect($bill->fresh()->status)->toBe(DocumentStatus::Received);
    });

    it('throws exception on invalid transition', function () {
        $bill = Bill::factory()->draft()->create();

        $stateMachine = BillStateMachine::fromBill($bill);

        $stateMachine->transitionTo(DocumentStatus::Paid);
    })->throws(StateTransitionException::class);
});

describe('BillStateMachine guards', function () {
    it('requires items to post bill', function () {
        $bill = Bill::factory()->draft()->create();

        $stateMachine = BillStateMachine::fromBill($bill);
        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Received);

        expect($reasons)->toContain('Tagihan tidak memiliki item.');
    });

    it('requires full payment to mark as paid from received', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['paid_amount' => 0]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeFalse();

        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Paid);
        expect($reasons)->toContain('Jumlah pembayaran belum mencukupi.');
    });

    it('allows paid transition when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $bill->update(['paid_amount' => $bill->total_amount]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeTrue();
    });

    it('requires past due date for overdue transition', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->addDays(30)]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeFalse();
    });

    it('allows overdue transition when past due date', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->subDay()]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeTrue();
    });

    it('requires partial payment for partial status', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['paid_amount' => 0]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeFalse();
    });

    it('blocks partial transition when fully paid', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $bill->update(['paid_amount' => $bill->total_amount]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeFalse();
    });

    it('allows partial transition with partial payment', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $bill->update(['paid_amount' => (int) ($bill->total_amount * 0.5)]);

        $stateMachine = BillStateMachine::fromBill($bill);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });
});

describe('BillStateMachine actions', function () {
    it('dispatches BillReceived event when posting', function () {
        $user = User::factory()->create();
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = BillStateMachine::fromBill($bill, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Received, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(BillReceived::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(BillReceived::class);
        expect($event->billId)->toBe($bill->id);
    });

    it('dispatches BillVoided event when cancelling from received', function () {
        $user = User::factory()->create();
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = BillStateMachine::fromBill($bill, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(BillVoided::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(BillVoided::class);
        expect($event->billId)->toBe($bill->id);
    });

    it('dispatches BillVoided event when cancelling from partial', function () {
        $user = User::factory()->create();
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->partial()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = BillStateMachine::fromBill($bill, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(BillVoided::class))->toBe(1);
    });

    it('dispatches BillStatusChanged event for all transitions', function () {
        $user = User::factory()->create();
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = BillStateMachine::fromBill($bill, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Received, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(BillStatusChanged::class))->toBeGreaterThanOrEqual(1);
    });
});

describe('BillStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = BillStateMachine::fromBill($bill);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Received->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for received bill', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->subDay()]);

        $bill->update(['paid_amount' => (int) ($bill->total_amount * 0.5)]);

        $stateMachine = BillStateMachine::fromBill($bill);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Received->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Partial->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Overdue->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for cancelled bill', function () {
        $bill = Bill::factory()->cancelled()->create();

        $stateMachine = BillStateMachine::fromBill($bill);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Cancelled->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = BillStateMachine::fromBill($bill);
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

describe('BillStateMachine business helpers', function () {
    it('reports canPost correctly', function () {
        $billWithItems = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->draft()
            ->create();
        $billWithoutItems = Bill::factory()->draft()->create();
        $receivedBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        expect(BillStateMachine::fromBill($billWithItems)->canPost())->toBeTrue();
        expect(BillStateMachine::fromBill($billWithoutItems)->canPost())->toBeFalse();
        expect(BillStateMachine::fromBill($receivedBill)->canPost())->toBeFalse();
    });

    it('reports canMarkAsPaid correctly', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $unpaidMachine = BillStateMachine::fromBill($bill);
        expect($unpaidMachine->canMarkAsPaid())->toBeFalse();

        $bill->update(['paid_amount' => $bill->total_amount]);
        $paidMachine = BillStateMachine::fromBill($bill->fresh());
        expect($paidMachine->canMarkAsPaid())->toBeTrue();
    });

    it('reports canMarkAsPartial correctly', function () {
        $bill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        $noPay = BillStateMachine::fromBill($bill);
        expect($noPay->canMarkAsPartial())->toBeFalse();

        $bill->update(['paid_amount' => (int) ($bill->total_amount * 0.5)]);
        $partialPay = BillStateMachine::fromBill($bill->fresh());
        expect($partialPay->canMarkAsPartial())->toBeTrue();
    });

    it('reports canMarkAsOverdue correctly', function () {
        $futureBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->addDays(30)]);

        $pastBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create(['due_date' => now()->subDay()]);

        expect(BillStateMachine::fromBill($futureBill)->canMarkAsOverdue())->toBeFalse();
        expect(BillStateMachine::fromBill($pastBill)->canMarkAsOverdue())->toBeTrue();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draftBill = Bill::factory()->draft()->create();
        $receivedBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();
        $paidBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->paid()
            ->create();

        expect(BillStateMachine::fromBill($draftBill)->canCancel())->toBeTrue();
        expect(BillStateMachine::fromBill($receivedBill)->canCancel())->toBeTrue();
        expect(BillStateMachine::fromBill($paidBill)->canCancel())->toBeTrue();
    });

    it('reports canEdit correctly', function () {
        $draftBill = Bill::factory()->draft()->create();
        $receivedBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        expect(BillStateMachine::fromBill($draftBill)->canEdit())->toBeTrue();
        expect(BillStateMachine::fromBill($receivedBill)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftBill = Bill::factory()->draft()->create();
        $receivedBill = Bill::factory()
            ->has(BillItem::factory(), 'items')
            ->received()
            ->create();

        expect(BillStateMachine::fromBill($draftBill)->canDelete())->toBeTrue();
        expect(BillStateMachine::fromBill($receivedBill)->canDelete())->toBeFalse();
    });
});
