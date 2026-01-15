<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for document lifecycle operations.
 *
 * Documents include: Quotations, Invoices, Bills, PurchaseOrders, WorkOrders, etc.
 * This interface defines common CRUD operations shared across document services.
 */
interface DocumentLifecycleInterface
{
    /**
     * Create a new document with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model;

    /**
     * Update an existing document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Model;

    /**
     * Delete a document (soft delete if applicable).
     */
    public function delete(Model $document): bool;
}
