<?php

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Inventory\StockOpname\StockOpnameStateMachine;
use App\Enums\DocumentStatus;
use App\Models\Inventory\StockOpname;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->eventDispatcher = app(EventDispatcherInterface::class);
});

describe('StockOpnameStateMachine', function () {

    it('creates state machine from stock opname model', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->getCurrentStatus())->toBe(DocumentStatus::Draft);
    });

    it('can transition from draft to counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);
        \App\Models\Inventory\StockOpnameItem::factory()->forStockOpname($stockOpname)->create();

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Counting);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Counting);
    });

    it('can transition from counting to reviewed', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Counting]);
        \App\Models\Inventory\StockOpnameItem::factory()->forStockOpname($stockOpname)->counted()->create();

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Reviewed);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Reviewed);
    });

    it('can transition from reviewed to approved', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Reviewed]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Approved);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Approved);
    });

    it('can transition from approved to completed', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Approved]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Completed);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Completed);
    });

    it('can transition from reviewed back to counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Reviewed]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Counting);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Counting);
    });

    it('can be cancelled from draft', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Cancelled);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('can be cancelled from counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Counting]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(DocumentStatus::Cancelled);

        expect($stockOpname->fresh()->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cannot cancel completed stock opname', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Completed]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
    });

    it('cannot transition from draft to approved directly', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('returns available transitions for draft', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);
        \App\Models\Inventory\StockOpnameItem::factory()->forStockOpname($stockOpname)->create();

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $transitions = $stateMachine->getNextValidStatuses();

        expect($transitions)->toContain(DocumentStatus::Counting->value)
            ->toContain(DocumentStatus::Cancelled->value)
            ->not->toContain(DocumentStatus::Approved->value);
    });

    it('checks can transition correctly', function () {
        $stockOpname = StockOpname::factory()->create(['status' => DocumentStatus::Draft]);
        \App\Models\Inventory\StockOpnameItem::factory()->forStockOpname($stockOpname)->create();

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->canTransitionTo(DocumentStatus::Counting))->toBeTrue()
            ->and($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

});
