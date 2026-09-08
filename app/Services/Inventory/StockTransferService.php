<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Services\Base\BaseService;

class StockTransferService extends BaseService
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockTransfer
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $lines = $data['items'] ?? [];
            unset($data['items']);

            $transfer = StockTransfer::query()->create([
                'operation_type' => $data['operation_type'],
                'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'scheduled_date' => $data['scheduled_date'] ?? now()->toDateString(),
                'source_document' => $data['source_document'] ?? null,
                'status' => DocumentStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? $this->getUserId(),
            ]);

            foreach ($lines as $line) {
                $product = Product::query()->findOrFail($line['product_id']);
                $transfer->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'] ?? $product->unit,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $transfer->fresh(['items.product', 'fromWarehouse', 'toWarehouse', 'contact']);
        });
    }

    public function confirm(StockTransfer $transfer): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw StateTransitionException::wrongStateForOperation(
                'Stock Transfer',
                'selesai',
                $transfer->status->value,
                'draft'
            );
        }

        if ($transfer->items()->count() === 0) {
            throw DocumentLockedException::cannotEdit($transfer, 'Transfer harus memiliki minimal satu baris produk.');
        }

        return $this->executeInTransaction('confirm', function () use ($transfer) {
            $from = $transfer->from_warehouse_id ? Warehouse::query()->findOrFail($transfer->from_warehouse_id) : null;
            $to = $transfer->to_warehouse_id ? Warehouse::query()->findOrFail($transfer->to_warehouse_id) : null;

            foreach ($transfer->items()->with('product')->get() as $item) {
                $this->postLine($transfer, $item->product, $item->quantity, $item->notes, $from, $to);
            }

            $transfer->update([
                'status' => DocumentStatus::Completed,
                'completed_at' => now(),
            ]);

            return $transfer->fresh(['items.product', 'fromWarehouse', 'toWarehouse', 'contact']);
        }, ['transfer_id' => $transfer->id]);
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw StateTransitionException::wrongStateForOperation(
                'Stock Transfer',
                'batal',
                $transfer->status->value,
                'draft'
            );
        }

        $transfer->update([
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $transfer->fresh(['items.product', 'fromWarehouse', 'toWarehouse']);
    }

    private function postLine(
        StockTransfer $transfer,
        Product $product,
        int $quantity,
        ?string $notes,
        ?Warehouse $from,
        ?Warehouse $to
    ): void {
        $note = $notes ?? $transfer->transfer_number;

        if ($transfer->operation_type === StockTransfer::OPERATION_INTERNAL) {
            $movements = $this->inventoryService->transfer($product, $from, $to, $quantity, $note);
            InventoryMovement::query()
                ->whereKey([$movements['out']->id, $movements['in']->id])
                ->update([
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                ]);

            return;
        }

        if ($transfer->operation_type === StockTransfer::OPERATION_RECEIPT) {
            $this->inventoryService->stockIn(
                $product,
                $to,
                $quantity,
                (int) $product->purchase_price,
                $note,
                StockTransfer::class,
                $transfer->id,
            );

            return;
        }

        $this->inventoryService->stockOut(
            $product,
            $from,
            $quantity,
            $note,
            StockTransfer::class,
            $transfer->id,
        );
    }
}
