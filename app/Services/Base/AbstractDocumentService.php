<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Shared\DocumentLifecycleInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abstract base class for document services.
 *
 * Provides common CRUD operations for documents with items:
 * Quotations, Invoices, Bills, PurchaseOrders, etc.
 *
 * Subclasses must implement:
 * - getModelClass(): Model class name
 * - getItemRelation(): Relationship name for items
 * - generateDocumentNumber(): Generate unique document number
 * - getDefaultData(): Default values for new documents
 */
abstract class AbstractDocumentService implements DocumentLifecycleInterface
{
    /**
     * Get the model class name.
     *
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    /**
     * Get the item relationship name.
     */
    abstract protected function getItemRelation(): string;

    /**
     * Generate a unique document number.
     */
    abstract protected function generateDocumentNumber(?Model $context = null): string;

    /**
     * Get default data for new documents.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultData(): array
    {
        return [
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'base_currency_total' => 0,
        ];
    }

    /**
     * Get relations to load after create/update.
     *
     * @return array<string>
     */
    protected function getEagerLoadRelations(): array
    {
        return [$this->getItemRelation(), 'contact'];
    }

    /**
     * Create a new document with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Merge defaults
            $data = array_merge($this->getDefaultData(), $data);
            $data['created_by'] = $data['created_by'] ?? auth()->id();

            // Generate document number if not provided
            if (empty($data[$this->getDocumentNumberField()])) {
                $data[$this->getDocumentNumberField()] = $this->generateDocumentNumber();
            }

            // Set initial status
            $data['status'] = $data['status'] ?? $this->getInitialStatus();

            // Create document
            $modelClass = $this->getModelClass();
            $document = $modelClass::create($data);

            // Create items
            if (! empty($items)) {
                $this->createItems($document, $items);
            }

            // Calculate totals if document has financial data
            $this->recalculateTotals($document);

            return $document->load($this->getEagerLoadRelations());
        });
    }

    /**
     * Update an existing document.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Model
    {
        $this->validateEditable($document);

        return DB::transaction(function () use ($document, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $document->update($data);

            if ($items !== null) {
                // Delete existing items and recreate
                $document->{$this->getItemRelation()}()->delete();
                $this->createItems($document, $items);
            }

            // Recalculate totals
            $this->recalculateTotals($document);

            return $document->load($this->getEagerLoadRelations());
        });
    }

    /**
     * Delete a document.
     */
    public function delete(Model $document): bool
    {
        $this->validateDeletable($document);

        return DB::transaction(function () use ($document) {
            // Delete items first
            $document->{$this->getItemRelation()}()->delete();

            return $document->delete();
        });
    }

    /**
     * Create items for a document.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $itemData) {
            $document->{$this->getItemRelation()}()->create($itemData);
        }
    }

    /**
     * Recalculate document totals.
     */
    protected function recalculateTotals(Model $document): void
    {
        $document->refresh();

        if (method_exists($document, 'calculateTotals')) {
            $document->calculateTotals();
            $document->save();
        }
    }

    /**
     * Get the document number field name.
     */
    protected function getDocumentNumberField(): string
    {
        return 'document_number';
    }

    /**
     * Get the initial status for new documents.
     */
    protected function getInitialStatus(): string
    {
        return 'draft';
    }

    /**
     * Validate that document can be edited.
     *
     * @throws InvalidArgumentException
     */
    protected function validateEditable(Model $document): void
    {
        if (method_exists($document, 'isEditable') && ! $document->isEditable()) {
            throw new InvalidArgumentException('Dokumen tidak dapat diubah.');
        }
    }

    /**
     * Validate that document can be deleted.
     *
     * @throws InvalidArgumentException
     */
    protected function validateDeletable(Model $document): void
    {
        if (method_exists($document, 'isDeletable') && ! $document->isDeletable()) {
            throw new InvalidArgumentException('Dokumen tidak dapat dihapus.');
        }
    }
}
