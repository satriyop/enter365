<?php

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;

/**
 * Interface for Stock Opname service operations.
 */
interface StockOpnameServiceInterface
{
    /**
     * Create a new stock opname.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockOpname;

    /**
     * Update a stock opname.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(StockOpname $opname, array $data): StockOpname;

    /**
     * Delete a stock opname.
     */
    public function delete(StockOpname $opname): void;

    /**
     * Generate stock opname items from current inventory.
     */
    public function generateItems(StockOpname $opname): StockOpname;

    /**
     * Add an item to the stock opname.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(StockOpname $opname, array $data): StockOpnameItem;

    /**
     * Update a stock opname item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(StockOpnameItem $item, array $data): StockOpnameItem;

    /**
     * Remove an item from the stock opname.
     */
    public function removeItem(StockOpnameItem $item): void;

    /**
     * Start counting (change status to in_progress).
     */
    public function startCounting(StockOpname $opname, int $userId): StockOpname;

    /**
     * Submit for review.
     */
    public function submitForReview(StockOpname $opname, int $userId): StockOpname;

    /**
     * Approve stock opname and apply adjustments.
     */
    public function approve(StockOpname $opname, int $userId): StockOpname;

    /**
     * Reject stock opname.
     */
    public function reject(StockOpname $opname, int $userId, ?string $reason = null): StockOpname;

    /**
     * Cancel a stock opname.
     */
    public function cancel(StockOpname $opname, int $userId): StockOpname;

    /**
     * Get variance report for stock opname.
     *
     * @return array<string, mixed>
     */
    public function getVarianceReport(StockOpname $opname): array;
}
