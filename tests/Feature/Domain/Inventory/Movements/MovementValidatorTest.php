<?php

use App\Domain\Inventory\Movements\MovementValidator;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('MovementValidator', function () {

    beforeEach(function () {
        $this->validator = new MovementValidator;
        $this->warehouse = Warehouse::factory()->create(['is_active' => true]);
        $this->product = Product::factory()->create(['type' => 'goods']);
    });

    describe('validateReceipt', function () {

        it('passes for valid receipt', function () {
            $this->validator->validateReceipt(
                $this->product->id,
                $this->warehouse->id,
                10
            );

            expect(true)->toBeTrue(); // No exception = pass
        });

        it('fails for non-existent product', function () {
            expect(fn () => $this->validator->validateReceipt(
                99999,
                $this->warehouse->id,
                10
            ))->toThrow(EntityNotFoundException::class);
        });

        it('fails for non-existent warehouse', function () {
            expect(fn () => $this->validator->validateReceipt(
                $this->product->id,
                99999,
                10
            ))->toThrow(EntityNotFoundException::class);
        });

        it('fails for inactive warehouse', function () {
            $inactiveWarehouse = Warehouse::factory()->create(['is_active' => false]);

            expect(fn () => $this->validator->validateReceipt(
                $this->product->id,
                $inactiveWarehouse->id,
                10
            ))->toThrow(BusinessRuleException::class, 'tidak aktif');
        });

        it('fails for zero quantity', function () {
            expect(fn () => $this->validator->validateReceipt(
                $this->product->id,
                $this->warehouse->id,
                0
            ))->toThrow(BusinessRuleException::class, 'lebih dari 0');
        });

        it('fails for negative quantity', function () {
            expect(fn () => $this->validator->validateReceipt(
                $this->product->id,
                $this->warehouse->id,
                -5
            ))->toThrow(BusinessRuleException::class, 'lebih dari 0');
        });

        it('fails for service products', function () {
            $serviceProduct = Product::factory()->create(['type' => 'service']);

            expect(fn () => $this->validator->validateReceipt(
                $serviceProduct->id,
                $this->warehouse->id,
                10
            ))->toThrow(BusinessRuleException::class, 'jasa tidak memiliki inventori');
        });
    });

    describe('validateIssue', function () {

        it('passes for valid issue with sufficient stock', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 100,
                'reserved_quantity' => 0,
            ]);

            $this->validator->validateIssue(
                $this->product->id,
                $this->warehouse->id,
                50
            );

            expect(true)->toBeTrue();
        });

        it('fails for insufficient stock', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'reserved_quantity' => 0,
            ]);

            expect(fn () => $this->validator->validateIssue(
                $this->product->id,
                $this->warehouse->id,
                50
            ))->toThrow(BusinessRuleException::class);
        });

        it('fails when stock does not exist', function () {
            expect(fn () => $this->validator->validateIssue(
                $this->product->id,
                $this->warehouse->id,
                10
            ))->toThrow(BusinessRuleException::class);
        });

        it('considers reserved quantity', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 100,
                'reserved_quantity' => 80,
            ]);

            expect(fn () => $this->validator->validateIssue(
                $this->product->id,
                $this->warehouse->id,
                30
            ))->toThrow(BusinessRuleException::class);
        });
    });

    describe('validateAdjustment', function () {

        it('passes for positive adjustment', function () {
            $this->validator->validateAdjustment(
                $this->product->id,
                $this->warehouse->id,
                50
            );

            expect(true)->toBeTrue();
        });

        it('passes for negative adjustment with sufficient stock', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 100,
                'reserved_quantity' => 0,
            ]);

            $this->validator->validateAdjustment(
                $this->product->id,
                $this->warehouse->id,
                -50
            );

            expect(true)->toBeTrue();
        });

        it('fails for zero adjustment', function () {
            expect(fn () => $this->validator->validateAdjustment(
                $this->product->id,
                $this->warehouse->id,
                0
            ))->toThrow(BusinessRuleException::class, 'tidak boleh 0');
        });

        it('fails for negative adjustment exceeding stock', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 30,
                'reserved_quantity' => 0,
            ]);

            expect(fn () => $this->validator->validateAdjustment(
                $this->product->id,
                $this->warehouse->id,
                -50
            ))->toThrow(BusinessRuleException::class);
        });
    });

    describe('validateTransfer', function () {

        beforeEach(function () {
            $this->toWarehouse = Warehouse::factory()->create(['is_active' => true]);
        });

        it('passes for valid transfer', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 100,
                'reserved_quantity' => 0,
            ]);

            $this->validator->validateTransfer(
                $this->product->id,
                $this->warehouse->id,
                $this->toWarehouse->id,
                50
            );

            expect(true)->toBeTrue();
        });

        it('fails when source and destination are the same', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 100,
                'reserved_quantity' => 0,
            ]);

            expect(fn () => $this->validator->validateTransfer(
                $this->product->id,
                $this->warehouse->id,
                $this->warehouse->id,
                50
            ))->toThrow(BusinessRuleException::class, 'tidak boleh sama');
        });

        it('fails for insufficient source stock', function () {
            ProductStock::factory()->create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 20,
                'reserved_quantity' => 0,
            ]);

            expect(fn () => $this->validator->validateTransfer(
                $this->product->id,
                $this->warehouse->id,
                $this->toWarehouse->id,
                50
            ))->toThrow(BusinessRuleException::class);
        });
    });
});
