<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresRowLock;

describe('Inventory stock-out under a held row lock', function () {
    it('cannot stock out the last unit while another session holds lockForStock', function () {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['track_inventory' => true]);
        $warehouse = Warehouse::factory()->create();
        $service = app(InventoryServiceInterface::class);
        $service->stockIn($product, $warehouse, 1, 10000);

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        DB::beginTransaction();

        try {
            ProductStock::query()->where('id', $stock->id)->lockForUpdate()->firstOrFail();

            PostgresRowLock::onPeer(function () use ($service, $product, $warehouse): void {
                try {
                    $service->stockOut($product, $warehouse, 1);
                    test()->fail('stockOut completed while the product_stocks row was locked by another session.');
                } catch (QueryException $exception) {
                    expect(PostgresRowLock::isLockTimeout($exception))->toBeTrue(
                        $exception->getMessage()
                    );
                }
            });
        } finally {
            DB::rollBack();
        }

        $service->stockOut($product, $warehouse, 1);
        expect((int) $stock->fresh()->quantity)->toBe(0);
    });
});
