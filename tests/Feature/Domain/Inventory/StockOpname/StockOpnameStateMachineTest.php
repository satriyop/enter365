<?php

use App\Domain\Inventory\StockOpname\StockOpnameStateMachine;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\LaravelEventDispatcher;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('StockOpnameStateMachine', function () {

    beforeEach(function () {
        $this->eventDispatcher = app(LaravelEventDispatcher::class);
    });

    it('creates state machine from stock opname model', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->getCurrentStatus())->toBe(StockOpname::STATUS_DRAFT);
    });

    it('can transition from draft to counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);
        StockOpnameItem::factory()->create(['stock_opname_id' => $stockOpname->id]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_COUNTING);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_COUNTING);
    });

    it('can transition from counting to reviewed', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_COUNTING]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_REVIEWED);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_REVIEWED);
    });

    it('can transition from reviewed to approved', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_REVIEWED]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_APPROVED);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_APPROVED);
    });

    it('can transition from approved to completed', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_APPROVED]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_COMPLETED);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_COMPLETED);
    });

    it('can transition from reviewed back to counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_REVIEWED]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_COUNTING);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_COUNTING);
    });

    it('can be cancelled from draft', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_CANCELLED);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_CANCELLED);
    });

    it('can be cancelled from counting', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_COUNTING]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $stateMachine->transitionTo(StockOpname::STATUS_CANCELLED);

        expect($stockOpname->fresh()->status)->toBe(StockOpname::STATUS_CANCELLED);
    });

    it('cannot cancel completed stock opname', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_COMPLETED]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect(fn () => $stateMachine->transitionTo(StockOpname::STATUS_CANCELLED))
            ->toThrow(StateTransitionException::class);
    });

    it('cannot transition from draft to approved directly', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect(fn () => $stateMachine->transitionTo(StockOpname::STATUS_APPROVED))
            ->toThrow(StateTransitionException::class);
    });

    it('returns available transitions for draft', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);
        $transitions = $stateMachine->getNextValidStatuses();

        expect($transitions)->toContain(StockOpname::STATUS_COUNTING);
        expect($transitions)->toContain(StockOpname::STATUS_CANCELLED);
        expect($transitions)->not->toContain(StockOpname::STATUS_APPROVED);
    });

    it('checks can transition correctly', function () {
        $stockOpname = StockOpname::factory()->create(['status' => StockOpname::STATUS_DRAFT]);

        $stateMachine = StockOpnameStateMachine::fromStockOpname($stockOpname, $this->eventDispatcher);

        expect($stateMachine->canTransitionTo(StockOpname::STATUS_COUNTING))->toBeTrue();
        expect($stateMachine->canTransitionTo(StockOpname::STATUS_APPROVED))->toBeFalse();
    });
});
