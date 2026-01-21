<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Support\Results\CreateResult;
use App\Support\Results\DeleteResult;
use App\Support\Results\UpdateResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Abstract base class for document services.
 *
 * Documents: Invoices, Bills, Quotations, PurchaseOrders, WorkOrders, etc.
 *
 * Provides common CRUD operations with:
 * - Repository-based data access
 * - Result objects for consistent returns
 * - Transaction management
 * - Logging and event dispatching
 *
 * @template TModel of Model
 * @template TRepository of RepositoryInterface<TModel>
 */
abstract class AbstractDocumentService extends AbstractApplicationService
{
    protected ?RepositoryInterface $repository = null;

    protected ?DocumentNumberGeneratorInterface $numberGenerator = null;

    /**
     * Constructor with strict dependency injection.
     *
     * EventDispatcher and Logger are REQUIRED (inherited from AbstractApplicationService).
     * Repository and NumberGenerator are optional (some services use model directly).
     *
     * @param  EventDispatcherInterface  $eventDispatcher  Required - for domain events
     * @param  ContextualLoggerInterface  $logger  Required - for operation logging
     * @param  RepositoryInterface|null  $repository  Optional - for data access
     * @param  DocumentNumberGeneratorInterface|null  $numberGenerator  Optional - for document numbering
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        ?RepositoryInterface $repository = null,
        ?DocumentNumberGeneratorInterface $numberGenerator = null
    ) {
        parent::__construct($eventDispatcher, $logger);

        $this->repository = $repository;
        $this->numberGenerator = $numberGenerator;
    }

    /**
     * Get the document number field name.
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
     * Get the item relationship name.
     */
    abstract protected function getItemRelation(): string;

    /**
     * Get the model class name (for backward compatibility with legacy services).
     *
     * Override in services that don't use repository injection.
     *
     * @return class-string<TModel>
     */
    protected function getModelClass(): string
    {
        // Should be overridden by legacy services that don't inject repository
        throw new \RuntimeException('getModelClass() must be overridden when not using repository injection.');
    }

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
     * Get the initial status for new documents.
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
     * Create a new document (Result pattern).
     *
     * Override in child class for public access with Result return type.
     *
     * @param  array<string, mixed>  $data
     * @return CreateResult<TModel>
     */
    protected function createDocument(array $data): CreateResult
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

            return CreateResult::created($this->loadRelations($document));
        }, ['contact_id' => $data['contact_id'] ?? null]);
    }

    /**
     * Update a document (Result pattern).
     *
     * Override in child class for public access with Result return type.
     *
     * @param  TModel  $document
     * @param  array<string, mixed>  $data
     * @return UpdateResult<TModel>
     *
     * @throws DocumentLockedException
     */
    protected function updateDocument(Model $document, array $data): UpdateResult
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

            return UpdateResult::updated($this->loadRelations($document));
        }, ['document_id' => $document->id]);
    }

    /**
     * Delete a document (Result pattern).
     *
     * Override in child class for public access with Result return type.
     *
     * @param  TModel  $document
     *
     * @throws DocumentLockedException
     */
    protected function deleteDocument(Model $document): DeleteResult
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

            return DeleteResult::deleted();
        }, ['document_id' => $document->id]);
    }

    /**
     * Create items for document.
     *
     * @param  TModel  $document
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
     *
     * @param  TModel  $document
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
     *
     * @param  TModel  $document
     * @return TModel
     */
    protected function loadRelations(Model $document): Model
    {
        return $document->fresh($this->getEagerLoadRelations());
    }

    /**
     * Validate document is editable.
     *
     * @param  TModel  $document
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
     * @param  TModel  $document
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
}
