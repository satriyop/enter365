<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\WorkOrder;

/**
 * Interface for Material Requisition service operations.
 */
interface MaterialRequisitionServiceInterface
{
    /**
     * Create a new material requisition.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(WorkOrder $wo, array $data = []): MaterialRequisition;

    /**
     * Update an existing material requisition.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MaterialRequisition $mr, array $data): MaterialRequisition;

    /**
     * Delete a material requisition.
     */
    public function delete(MaterialRequisition $mr): bool;

    /**
     * Approve a material requisition.
     */
    public function approve(MaterialRequisition $mr, ?int $userId = null): MaterialRequisition;

    /**
     * Issue materials (deduct from inventory).
     *
     * @param  array<string, mixed>  $items
     */
    public function issue(MaterialRequisition $mr, array $items, ?int $userId = null): MaterialRequisition;

    /**
     * Cancel a material requisition.
     */
    public function cancel(MaterialRequisition $mr): MaterialRequisition;

    /**
     * Populate requisition items from work order BOM.
     */
    public function populateFromWorkOrder(MaterialRequisition $mr, WorkOrder $wo): void;
}
