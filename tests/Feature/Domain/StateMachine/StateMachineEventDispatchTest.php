<?php

use App\Domain\Sales\SalesReturns\Events\SalesReturnApproved;
use App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged;
use App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted;
use App\Domain\Sales\SalesReturns\SalesReturnStateMachine;
use App\Enums\DocumentStatus;
use App\Infrastructure\Events\NullEventDispatcher;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('State machine with injectable event dispatcher', function () {

    it('dispatches events via NullEventDispatcher for test isolation', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Draft,
        ]);
        SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

        // Create a NullEventDispatcher to capture events
        $dispatcher = new NullEventDispatcher;

        // Create state machine with injected dispatcher
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $dispatcher);

        // Transition to submitted
        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        // Verify events were captured
        expect($dispatcher->count())->toBeGreaterThan(0);
        expect($dispatcher->hasDispatched(SalesReturnSubmitted::class))->toBeTrue();
        expect($dispatcher->hasDispatched(SalesReturnStatusChanged::class))->toBeTrue();
    });

    it('allows asserting specific events were dispatched', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Submitted,
        ]);
        SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

        $dispatcher = new NullEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $dispatcher);

        // Transition to approved
        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        // Verify events via hasDispatched
        expect($dispatcher->hasDispatched(SalesReturnApproved::class))->toBeTrue();
        expect($dispatcher->hasDispatched(SalesReturnStatusChanged::class))->toBeTrue();
    });

    it('provides access to dispatched events for inspection', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Draft,
        ]);
        SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

        $dispatcher = new NullEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $dispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        // Get all submitted events
        $submittedEvents = $dispatcher->getDispatched(SalesReturnSubmitted::class);

        expect($submittedEvents)->toHaveCount(1);

        $event = $submittedEvents[0];
        expect($event->salesReturnId)->toBe($salesReturn->id);
        expect($event->returnNumber)->toBe($salesReturn->return_number);
    });

    it('uses LaravelEventDispatcher by default when none provided', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Draft,
        ]);
        SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

        // Use Laravel's Event::fake() to verify integration
        Event::fake();

        // Create state machine WITHOUT explicit dispatcher
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        // Laravel's Event::fake captures the events
        Event::assertDispatched(SalesReturnSubmitted::class);
        Event::assertDispatched(SalesReturnStatusChanged::class);
    });

    it('allows clearing captured events in NullEventDispatcher', function () {
        $dispatcher = new NullEventDispatcher;

        // Dispatch some test event
        $event = new \stdClass;
        $dispatcher->dispatch($event);

        expect($dispatcher->count())->toBe(1);

        // Clear
        $dispatcher->clear();

        expect($dispatcher->count())->toBe(0);
        expect($dispatcher->all())->toBe([]);
    });

    it('can assert events were NOT dispatched', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create(['status' => DocumentStatus::Sent]);
        $salesReturn = SalesReturn::factory()->create([
            'invoice_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'status' => DocumentStatus::Draft,
        ]);
        SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

        $dispatcher = new NullEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $dispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        // Approved event should NOT be dispatched (only Submitted was called)
        expect($dispatcher->hasDispatched(SalesReturnApproved::class))->toBeFalse();
    });

});
