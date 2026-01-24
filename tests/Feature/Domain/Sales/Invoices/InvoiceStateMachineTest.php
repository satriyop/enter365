<?php

declare(strict_types=1);

use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Domain\Sales\Invoices\InvoiceStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| InvoiceStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the invoice state machine including transitions, guards,
| actions, workflow metadata, and business rule helpers.
|
*/

describe('InvoiceStateMachine transitions', function () {
    it('allows transition from draft to sent when invoice has items', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeTrue();
    });

    it('blocks transition from draft to sent when invoice has no items', function () {
        $invoice = Invoice::factory()->draft()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeFalse();
    });

    it('allows transition from sent to cancelled', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from draft to cancelled', function () {
        $invoice = Invoice::factory()->draft()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('blocks transition from cancelled to any other status', function () {
        $invoice = Invoice::factory()->cancelled()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Sent))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        expect($invoice->fresh()->status)->toBe(DocumentStatus::Sent);
    });

    it('throws exception on invalid transition', function () {
        $invoice = Invoice::factory()->draft()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        $stateMachine->transitionTo(DocumentStatus::Paid);
    })->throws(StateTransitionException::class);
});

describe('InvoiceStateMachine guards', function () {
    it('requires items to post invoice', function () {
        $invoice = Invoice::factory()->draft()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Sent);

        expect($reasons)->toContain('Faktur tidak memiliki item.');
    });

    it('requires full payment to mark as paid from sent', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['paid_amount' => 0]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeFalse();

        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Paid);
        expect($reasons)->toContain('Jumlah pembayaran belum mencukupi.');
    });

    it('allows paid transition when fully paid', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $invoice->update(['paid_amount' => $invoice->total_amount]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Paid))->toBeTrue();
    });

    it('requires past due date for overdue transition', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['due_date' => now()->addDays(30)]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeFalse();
    });

    it('allows overdue transition when past due date', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['due_date' => now()->subDay()]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Overdue))->toBeTrue();
    });

    it('requires partial payment for partial status', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['paid_amount' => 0]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeFalse();
    });

    it('allows partial transition with partial payment', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $invoice->update(['paid_amount' => (int) ($invoice->total_amount * 0.5)]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });
});

describe('InvoiceStateMachine actions', function () {
    it('dispatches InvoiceSent event when posting', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(InvoiceSent::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(InvoiceSent::class);
        expect($event->invoiceId)->toBe($invoice->id);
    });

    it('dispatches InvoiceVoided event when cancelling from sent', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(InvoiceVoided::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(InvoiceVoided::class);
        expect($event->invoiceId)->toBe($invoice->id);
    });

    it('dispatches InvoiceVoided event when cancelling from partial', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->partial()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(InvoiceVoided::class))->toBe(1);
    });
});

describe('InvoiceStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Sent->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for sent invoice', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['due_date' => now()->subDay()]);

        $invoice->update(['paid_amount' => (int) ($invoice->total_amount * 0.5)]);

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Sent->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Partial->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Overdue->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for cancelled invoice', function () {
        $invoice = Invoice::factory()->cancelled()->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Cancelled->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
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

describe('InvoiceStateMachine business helpers', function () {
    it('reports canPost correctly', function () {
        $invoiceWithItems = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();
        $invoiceWithoutItems = Invoice::factory()->draft()->create();

        expect(InvoiceStateMachine::fromInvoice($invoiceWithItems)->canPost())->toBeTrue();
        expect(InvoiceStateMachine::fromInvoice($invoiceWithoutItems)->canPost())->toBeFalse();
    });

    it('reports canMarkAsPaid correctly', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $unpaidMachine = InvoiceStateMachine::fromInvoice($invoice);
        expect($unpaidMachine->canMarkAsPaid())->toBeFalse();

        $invoice->update(['paid_amount' => $invoice->total_amount]);
        $paidMachine = InvoiceStateMachine::fromInvoice($invoice->fresh());
        expect($paidMachine->canMarkAsPaid())->toBeTrue();
    });

    it('reports canMarkAsPartial correctly', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        $noPay = InvoiceStateMachine::fromInvoice($invoice);
        expect($noPay->canMarkAsPartial())->toBeFalse();

        $invoice->update(['paid_amount' => (int) ($invoice->total_amount * 0.5)]);
        $partialPay = InvoiceStateMachine::fromInvoice($invoice->fresh());
        expect($partialPay->canMarkAsPartial())->toBeTrue();
    });

    it('reports canMarkAsOverdue correctly', function () {
        $futureInvoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['due_date' => now()->addDays(30)]);

        $pastInvoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create(['due_date' => now()->subDay()]);

        expect(InvoiceStateMachine::fromInvoice($futureInvoice)->canMarkAsOverdue())->toBeFalse();
        expect(InvoiceStateMachine::fromInvoice($pastInvoice)->canMarkAsOverdue())->toBeTrue();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draftInvoice = Invoice::factory()->draft()->create();
        $sentInvoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        expect(InvoiceStateMachine::fromInvoice($draftInvoice)->canCancel())->toBeTrue();
        expect(InvoiceStateMachine::fromInvoice($sentInvoice)->canCancel())->toBeTrue();
    });

    it('reports canEdit correctly', function () {
        $draftInvoice = Invoice::factory()->draft()->create();
        $sentInvoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        expect(InvoiceStateMachine::fromInvoice($draftInvoice)->canEdit())->toBeTrue();
        expect(InvoiceStateMachine::fromInvoice($sentInvoice)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftInvoice = Invoice::factory()->draft()->create();
        $sentInvoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create();

        expect(InvoiceStateMachine::fromInvoice($draftInvoice)->canDelete())->toBeTrue();
        expect(InvoiceStateMachine::fromInvoice($sentInvoice)->canDelete())->toBeFalse();
    });
});

describe('InvoiceStateMachine status history', function () {
    it('records status history on transition', function () {
        $user = User::factory()->create();
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Sent, ['user_id' => $user->id, 'reason' => 'Ready for sending']);

        $invoice->refresh();

        expect($invoice->statusHistories)->toHaveCount(1);
        expect($invoice->statusHistories->first()->from_status)->toBe(DocumentStatus::Draft);
        expect($invoice->statusHistories->first()->to_status)->toBe(DocumentStatus::Sent);
        expect($invoice->statusHistories->first()->user_id)->toBe($user->id);
    });
});
