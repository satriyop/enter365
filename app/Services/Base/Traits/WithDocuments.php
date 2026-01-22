<?php

declare(strict_types=1);

namespace App\Services\Base\Traits;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Use Model/bool returns instead. Will be removed in Phase 2.3.
 */

/**
 * @deprecated Use Model/bool returns instead. Will be removed in Phase 2.3.
 */

/**
 * @deprecated Use Model/bool returns instead. Will be removed in Phase 2.3.
 */

/**
 * Document management for services.
 *
 * Provides CRUD operations, document numbering, items management,
 * status validation, and totals calculation.
 *
 * Services using this trait must implement:
 * - getDocumentNumberField()
 * - getDocumentNumberPrefix()
 * - getDocumentNumberConfig()
 * - getItemRelation()
 */
trait WithDocuments
{
    protected ?RepositoryInterface $repository = null;

    protected ?DocumentNumberGeneratorInterface $numberGenerator = null;

    /**
     * Set repository for document operations.
     */
    public function withRepository(RepositoryInterface $repository): static
    {
        $clone = clone $this;
        $clone->repository = $repository;

        return $clone;
    }

    /**
     * Set number generator for document operations.
     */
    public function withNumberGenerator(DocumentNumberGeneratorInterface $generator): static
    {
        $clone = clone $this;
        $clone->numberGenerator = $generator;

        return $clone;
    }

    /**
     * Get document number field name.
     */
    abstract protected function getDocumentNumberField(): string;

    /**
     * Get document number prefix.
     */
    abstract protected function getDocumentNumberPrefix(): string;

    /**
     * Get document number table and column for generation.
     *
     * @return array{table: string, column: string}
     */
    abstract protected function getDocumentNumberConfig(): array;

    /**
     * Get item relationship name.
     */
    abstract protected function getItemRelation(): string;

    /**
     * Generate a unique document number.
     */
    protected function generateDocumentNumber(?Model $context = null): string
    {
        $config = $this->getDocumentNumberConfig();

        return $this->numberGenerator->generate(
            $this->getDocumentNumberPrefix(),
            $config['table'],
            $config['column']
        );
    }

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
            'total_amount' => 0,
            'base_currency_total' => 0,
        ];
    }

    /**
     * Get initial status for new documents.
     */
    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
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
     * Create a new document.
     *
     * Override in child class for public access.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createDocument(array $data): Model
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Merge defaults
            $data = array_merge($this->getDefaultData(), $data);
            $data['created_by'] = $data['created_by'] ?? $this->getUserId();
            $data['status'] = $this->getInitialStatus();

            // Generate document number
            $numberField = $this->getDocumentNumberField();
            if (empty($data[$numberField])) {
                $data[$numberField] = $this->generateDocumentNumber();
            }

            // Create document (repository or model fallback)
            $document = $this->repository !== null
                ? $this->repository->create($data)
                : $this->getModelClass()::create($data);

            // Create items
            if (! empty($items)) {
                $this->createItems($document, $items);
            }

            // Calculate totals
            $this->recalculateTotals($document);

            return $this->loadRelations($document);
        }, ['contact_id' => $data['contact_id'] ?? null]);
    }

    /**
     * Update a document.
     *
     * Override in child class for public access.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DocumentLockedException
     */
    protected function updateDocument(Model $document, array $data): Model
    {
        $this->validateEditable($document);

        return $this->executeInTransaction('update', function () use ($document, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            // Update document (repository or model fallback)
            if ($this->repository !== null) {
                $this->repository->update($document, $data);
            } else {
                $document->update($data);
            }

            if ($items !== null) {
                $document->{$this->getItemRelation()}()->delete();
                $this->createItems($document, $items);
            }

            $this->recalculateTotals($document);

            return $this->loadRelations($document);
        }, ['document_id' => $document->id]);
    }

    /**
     * Delete a document.
     *
     * Override in child class for public access.
     *
     *
     * @throws DocumentLockedException
     */
    protected function deleteDocument(Model $document): bool
    {
        $this->validateDeletable($document);

        return $this->executeInTransaction('delete', function () use ($document) {
            $document->{$this->getItemRelation()}()->delete();

            // Delete document (repository or model fallback)
            if ($this->repository !== null) {
                $this->repository->delete($document);
            } else {
                $document->delete();
            }

            return true;
        }, ['document_id' => $document->id]);
    }

    /**
     * Create items for document.
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
     * Load standard relations on document.
     */
    protected function loadRelations(Model $document): Model
    {
        return $document->fresh($this->getEagerLoadRelations());
    }

    /**
     * Validate document is editable.
     *
     *
     * @throws DocumentLockedException
     */
    protected function validateEditable(Model $document): void
    {
        if ($document->status !== DocumentStatus::Draft) {
            throw DocumentLockedException::cannotEdit(
                $document,
                'Hanya dokumen draft yang dapat diubah.'
            );
        }
    }

    /**
     * Validate document is deletable.
     *
     *
     * @throws DocumentLockedException
     */
    protected function validateDeletable(Model $document): void
    {
        if ($document->status !== DocumentStatus::Draft) {
            throw DocumentLockedException::cannotDelete(
                $document,
                'Hanya dokumen draft yang dapat dihapus.'
            );
        }
    }

    /**
     * Get model class name (for backward compatibility with services not using repository).
     *
     * Override in services that don't use repository injection.
     *
     * @return class-string<Model>
     */
    protected function getModelClass(): string
    {
        // Should be overridden by services that don't inject repository
        throw new \RuntimeException('getModelClass() must be overridden when not using repository injection.');
    }
}
