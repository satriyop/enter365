<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * Interface for Inventory service operations.
 */
interface InventoryServiceInterface
{
    /**
     * Record stock in (purchase/receiving).
     */
    public function stockIn(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        int $unitCost,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement;

    /**
     * Record stock out (sale/delivery).
     */
    public function stockOut(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement;

    /**
     * Record stock adjustment.
     */
    public function adjust(
        Product $product,
        Warehouse $warehouse,
        int $newQuantity,
        ?int $newUnitCost = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement;

    /**
     * Transfer stock between warehouses.
     *
     * @return array{out: InventoryMovement, in: InventoryMovement}
     */
    public function transfer(
        Product $product,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        int $quantity,
        ?string $notes = null
    ): array;

    /**
     * Process inventory for a posted invoice (stock out).
     */
    public function processInvoice(Invoice $invoice, Warehouse $warehouse): void;

    /**
     * Process inventory for a posted bill (stock in).
     */
    public function processBill(Bill $bill, Warehouse $warehouse): void;

    /**
     * Get stock card for a product.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getStockCard(
        Product $product,
        ?Warehouse $warehouse = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection;

    /**
     * Get stock valuation report.
     *
     * @return Collection<int, object>
     */
    public function getStockValuation(?Warehouse $warehouse = null): Collection;

    /**
     * Get inventory summary.
     *
     * @return array<string, mixed>
     */
    public function getInventorySummary(?Warehouse $warehouse = null): array;

    /**
     * Get movement summary for a period.
     *
     * @return array<string, mixed>
     */
    public function getMovementSummary(string $startDate, string $endDate, ?Warehouse $warehouse = null): array;
}
