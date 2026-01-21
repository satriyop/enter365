<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\Support\Results\CreateResult;
use App\Support\Results\DeleteResult;
use App\Support\Results\UpdateResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for document lifecycle operations.
 *
 * Documents include: Quotations, Invoices, Bills, PurchaseOrders, WorkOrders, etc.
 * This interface defines common CRUD operations shared across document services.
 *
 * Returns result objects for consistent error handling and response patterns.
 */
interface DocumentLifecycleInterface
{
    /**
     * Create a new document with items.
     *
     * @param  array<string, mixed>  $data
     * @return CreateResult<Model>
     */
    public function create(array $data): CreateResult;

    /**
     * Update an existing document.
     *
     * @param  array<string, mixed>  $data
     * @return UpdateResult<Model>
     */
    public function update(Model $document, array $data): UpdateResult;

    /**
     * Delete a document (soft delete if applicable).
     */
    public function delete(Model $document): DeleteResult;
}
