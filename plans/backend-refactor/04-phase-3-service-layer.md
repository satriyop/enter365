# Phase 3: Service Layer Refinement

> **Goal**: Standardize all services to follow SOLID principles, consistent patterns, and clean architecture boundaries.

## Current Issues

1. **Inconsistent Inheritance**: Some services extend `AbstractDocumentService`, others don't
2. **Mixed Responsibilities**: Some services handle CRUD, workflows, and calculations
3. **Inconsistent Validation**: Validation spread across controllers, services, and models
4. **Hard Dependencies**: Some services directly instantiate dependencies

---

## Deliverables

- [ ] Application Services vs Domain Services distinction
- [ ] Consistent service structure
- [ ] Command/Query separation (CQRS-lite)
- [ ] Service result objects
- [ ] Transaction management standardization

---

## Part 1: Service Layer Architecture

### 1.1 Service Types

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  ┌─────────────────────┐  ┌─────────────────────────────┐   │
│  │  Application Service │  │     Query Service           │   │
│  │  (Commands/Actions)  │  │  (Read-only operations)     │   │
│  │  - InvoiceService    │  │  - InvoiceQueryService      │   │
│  │  - WorkOrderService  │  │  - ReportService            │   │
│  └─────────────────────┘  └─────────────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│                      Domain Layer                            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              Domain Services                         │    │
│  │  - InvoiceCalculator    - BomExploder               │    │
│  │  - TaxCalculator        - CostCalculator            │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Directory Structure

```
app/
├── Services/                    # Application Services (Commands)
│   ├── Sales/
│   │   ├── InvoiceService.php
│   │   ├── QuotationService.php
│   │   └── Commands/           # Optional: for complex services
│   │       ├── CreateInvoiceCommand.php
│   │       └── PostInvoiceCommand.php
│   └── Manufacturing/
│       └── WorkOrderService.php
│
├── QueryServices/               # Query Services (Reads)
│   ├── Sales/
│   │   ├── InvoiceQueryService.php
│   │   └── SalesReportService.php
│   └── Manufacturing/
│       └── WorkOrderQueryService.php
│
└── Domain/                      # Domain Services
    └── Sales/
        └── Invoices/
            └── InvoiceCalculator.php
```

---

## Part 2: Service Result Objects

### 2.1 Base Result Class

```php
<?php
// File: app/Support/Results/ServiceResult.php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Base result object for service operations.
 *
 * @template T
 */
class ServiceResult
{
    private bool $success;
    private ?object $data;
    private ?string $message;
    private array $errors;
    private array $metadata;

    private function __construct(
        bool $success,
        ?object $data = null,
        ?string $message = null,
        array $errors = [],
        array $metadata = []
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    /**
     * Create success result.
     *
     * @template TData of object
     * @param TData|null $data
     * @return self<TData>
     */
    public static function success(?object $data = null, ?string $message = null): self
    {
        return new self(true, $data, $message);
    }

    /**
     * Create failure result.
     *
     * @return self<null>
     */
    public static function failure(string $message, array $errors = []): self
    {
        return new self(false, null, $message, $errors);
    }

    /**
     * Create validation failure result.
     *
     * @param array<string, array<string>> $errors
     * @return self<null>
     */
    public static function validationFailed(array $errors): self
    {
        return new self(false, null, 'Validasi gagal.', $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * @return T|null
     */
    public function getData(): ?object
    {
        return $this->data;
    }

    /**
     * Get data or throw exception if failed.
     *
     * @return T
     * @throws \RuntimeException
     */
    public function getDataOrFail(): object
    {
        if ($this->isFailure()) {
            throw new \RuntimeException($this->message ?? 'Operation failed');
        }

        if ($this->data === null) {
            throw new \RuntimeException('No data available');
        }

        return $this->data;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->message) {
            $result['message'] = $this->message;
        }

        if ($this->success && $this->data) {
            $result['data'] = $this->data;
        }

        if (! $this->success && ! empty($this->errors)) {
            $result['errors'] = $this->errors;
        }

        return $result;
    }
}
```

### 2.2 Typed Result Objects

```php
<?php
// File: app/Support/Results/CreateResult.php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for create operations.
 *
 * @template T of \Illuminate\Database\Eloquent\Model
 * @extends ServiceResult<T>
 */
class CreateResult extends ServiceResult
{
    /**
     * @param T $entity
     */
    public static function created(object $entity): self
    {
        return self::success($entity, 'Data berhasil dibuat.');
    }
}
```

```php
<?php
// File: app/Support/Results/UpdateResult.php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for update operations.
 *
 * @template T of \Illuminate\Database\Eloquent\Model
 * @extends ServiceResult<T>
 */
class UpdateResult extends ServiceResult
{
    /**
     * @param T $entity
     */
    public static function updated(object $entity): self
    {
        return self::success($entity, 'Data berhasil diperbarui.');
    }
}
```

```php
<?php
// File: app/Support/Results/DeleteResult.php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Result for delete operations.
 */
class DeleteResult extends ServiceResult
{
    public static function deleted(): self
    {
        return self::success(null, 'Data berhasil dihapus.');
    }
}
```

---

## Part 3: Base Application Service

### 3.1 Abstract Application Service

```php
<?php
// File: app/Services/Base/AbstractApplicationService.php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Support\Results\ServiceResult;
use Illuminate\Support\Facades\DB;

/**
 * Base class for application services.
 *
 * Provides:
 * - Transaction management
 * - Logging
 * - Event dispatching
 * - Error handling
 */
abstract class AbstractApplicationService
{
    protected EventDispatcherInterface $eventDispatcher;
    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Execute operation within transaction with logging.
     *
     * @template T
     * @param string $operation Operation name for logging
     * @param callable(): T $callback
     * @param array<string, mixed> $context Additional context for logging
     * @return T
     */
    protected function executeInTransaction(string $operation, callable $callback, array $context = []): mixed
    {
        $this->logger->logEntry(static::class, $operation, $context);
        $start = microtime(true);

        try {
            $result = DB::transaction($callback);

            $this->logger->logPerformance(
                $operation,
                microtime(true) - $start,
                ['status' => 'success', ...$context]
            );

            $this->logger->logExit(static::class, $operation, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->logError($e, [
                'operation' => $operation,
                ...$context,
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch domain event.
     */
    protected function dispatch(object $event): void
    {
        $this->eventDispatcher->dispatch($event);
        $this->logger->logDomainEvent($event);
    }

    /**
     * Get authenticated user ID.
     */
    protected function getUserId(): ?int
    {
        return auth()->id();
    }
}
```

### 3.2 Abstract Document Service (Refactored)

```php
<?php
// File: app/Services/Base/AbstractDocumentService.php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Support\Results\CreateResult;
use App\Support\Results\DeleteResult;
use App\Support\Results\UpdateResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Base service for document-type entities.
 *
 * Documents: Invoices, Bills, Quotations, PurchaseOrders, WorkOrders, etc.
 *
 * @template TModel of Model
 * @template TRepository of RepositoryInterface<TModel>
 */
abstract class AbstractDocumentService extends AbstractApplicationService
{
    protected RepositoryInterface $repository;
    protected DocumentNumberGeneratorInterface $numberGenerator;

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
     * Create a new document.
     *
     * @param array<string, mixed> $data
     * @return CreateResult<TModel>
     */
    public function create(array $data): CreateResult
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
                $config = $this->getDocumentNumberConfig();
                $data[$numberField] = $this->numberGenerator->generate(
                    $this->getDocumentNumberPrefix(),
                    $config['table'],
                    $config['column']
                );
            }

            // Create document
            $document = $this->repository->create($data);

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
     * Update a document.
     *
     * @param TModel $document
     * @param array<string, mixed> $data
     * @return UpdateResult<TModel>
     * @throws DocumentLockedException
     */
    public function update(Model $document, array $data): UpdateResult
    {
        $this->validateEditable($document);

        return $this->executeInTransaction('update', function () use ($document, $data) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $this->repository->update($document, $data);

            if ($items !== null) {
                $document->{$this->getItemRelation()}()->delete();
                $this->createItems($document, $items);
            }

            $this->recalculateTotals($document);

            return UpdateResult::updated($this->loadRelations($document));
        }, ['document_id' => $document->id]);
    }

    /**
     * Delete a document.
     *
     * @param TModel $document
     * @throws DocumentLockedException
     */
    public function delete(Model $document): DeleteResult
    {
        $this->validateDeletable($document);

        return $this->executeInTransaction('delete', function () use ($document) {
            $document->{$this->getItemRelation()}()->delete();
            $this->repository->delete($document);

            return DeleteResult::deleted();
        }, ['document_id' => $document->id]);
    }

    /**
     * Create items for document.
     *
     * @param TModel $document
     * @param array<int, array<string, mixed>> $items
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
     * @param TModel $document
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
     * @param TModel $document
     * @return TModel
     */
    protected function loadRelations(Model $document): Model
    {
        return $document->fresh([$this->getItemRelation(), 'contact']);
    }

    /**
     * Validate document is editable.
     *
     * @param TModel $document
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
     * @param TModel $document
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
```

---

## Part 4: Query Services

### 4.1 Base Query Service

```php
<?php
// File: app/QueryServices/Base/AbstractQueryService.php

declare(strict_types=1);

namespace App\QueryServices\Base;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Base class for read-only query services.
 *
 * Query services are optimized for reads and do not modify data.
 * They can use repositories or direct DB queries for performance.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class AbstractQueryService
{
    protected RepositoryInterface $repository;
    protected ContextualLoggerInterface $logger;

    public function __construct(
        RepositoryInterface $repository,
        ContextualLoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * Get single entity by ID.
     *
     * @return TModel|null
     */
    public function find(int $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * Get paginated list with filters.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->paginate($perPage);
    }

    /**
     * Get collection with filters.
     *
     * @param array<string, mixed> $filters
     * @return Collection<int, TModel>
     */
    public function list(array $filters = []): Collection
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->get();
    }

    /**
     * Count records matching filters.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->count();
    }

    /**
     * Get statistics for date range.
     *
     * @return array<string, mixed>
     */
    abstract public function getStatistics(DateRange $range): array;

    /**
     * Build filtered query.
     *
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function buildFilteredQuery(array $filters): mixed;
}
```

### 4.2 Invoice Query Service

```php
<?php
// File: app/QueryServices/Sales/InvoiceQueryService.php

declare(strict_types=1);

namespace App\QueryServices\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\QueryServices\Base\AbstractQueryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query service for invoice reads and reporting.
 *
 * @extends AbstractQueryService<Invoice>
 */
class InvoiceQueryService extends AbstractQueryService
{
    public function __construct(
        InvoiceRepositoryInterface $repository,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($repository, $logger);
    }

    /**
     * Get invoice with all relations.
     */
    public function findWithDetails(int $id): ?Invoice
    {
        return $this->repository->findWithRelations($id);
    }

    /**
     * Get overdue invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function getOverdue(): Collection
    {
        return $this->repository->findOverdue();
    }

    /**
     * Get invoices by status.
     *
     * @return Collection<int, Invoice>
     */
    public function getByStatus(DocumentStatus $status): Collection
    {
        return $this->repository->findByStatus($status);
    }

    /**
     * Get invoices for contact.
     *
     * @return Collection<int, Invoice>
     */
    public function getByContact(int $contactId): Collection
    {
        return $this->repository->findByContact($contactId);
    }

    /**
     * Get outstanding amount for contact.
     */
    public function getOutstandingForContact(int $contactId): int
    {
        return $this->repository->getOutstandingForContact($contactId);
    }

    /**
     * Get statistics for date range.
     */
    public function getStatistics(DateRange $range): array
    {
        // Use raw queries for performance
        $stats = DB::table('invoices')
            ->whereBetween('invoice_date', [$range->start, $range->end])
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as overdue_count,
                SUM(total_amount) as total_amount,
                SUM(paid_amount) as paid_amount,
                SUM(total_amount - paid_amount) as outstanding_amount
            ', [
                DocumentStatus::Draft->value,
                DocumentStatus::Sent->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
            ])
            ->first();

        return [
            'period' => $range->jsonSerialize(),
            'counts' => [
                'total' => $stats->total_count,
                'draft' => $stats->draft_count,
                'sent' => $stats->sent_count,
                'paid' => $stats->paid_count,
                'overdue' => $stats->overdue_count,
            ],
            'amounts' => [
                'total' => (int) $stats->total_amount,
                'paid' => (int) $stats->paid_amount,
                'outstanding' => (int) $stats->outstanding_amount,
            ],
        ];
    }

    /**
     * Get aging report.
     *
     * @return array<string, array{count: int, amount: int}>
     */
    public function getAgingReport(): array
    {
        $now = now();

        $aging = DB::table('invoices')
            ->whereNotIn('status', [
                DocumentStatus::Paid->value,
                DocumentStatus::Cancelled->value,
                DocumentStatus::Draft->value,
            ])
            ->selectRaw('
                SUM(CASE WHEN due_date >= ? THEN 1 ELSE 0 END) as current_count,
                SUM(CASE WHEN due_date >= ? THEN total_amount - paid_amount ELSE 0 END) as current_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_1_30_count,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END) as days_1_30_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_31_60_count,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END) as days_31_60_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_61_90_count,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END) as days_61_90_amount,
                SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as over_90_count,
                SUM(CASE WHEN due_date < ? THEN total_amount - paid_amount ELSE 0 END) as over_90_amount
            ', [
                $now,
                $now,
                $now, $now->copy()->subDays(30),
                $now, $now->copy()->subDays(30),
                $now->copy()->subDays(30), $now->copy()->subDays(60),
                $now->copy()->subDays(30), $now->copy()->subDays(60),
                $now->copy()->subDays(60), $now->copy()->subDays(90),
                $now->copy()->subDays(60), $now->copy()->subDays(90),
                $now->copy()->subDays(90),
                $now->copy()->subDays(90),
            ])
            ->first();

        return [
            'current' => [
                'count' => (int) $aging->current_count,
                'amount' => (int) $aging->current_amount,
            ],
            '1-30_days' => [
                'count' => (int) $aging->days_1_30_count,
                'amount' => (int) $aging->days_1_30_amount,
            ],
            '31-60_days' => [
                'count' => (int) $aging->days_31_60_count,
                'amount' => (int) $aging->days_31_60_amount,
            ],
            '61-90_days' => [
                'count' => (int) $aging->days_61_90_count,
                'amount' => (int) $aging->days_61_90_amount,
            ],
            'over_90_days' => [
                'count' => (int) $aging->over_90_count,
                'amount' => (int) $aging->over_90_amount,
            ],
        ];
    }

    protected function buildFilteredQuery(array $filters): mixed
    {
        $query = Invoice::query()->with(['contact', 'items']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('invoice_date', 'desc');
    }
}
```

---

## Part 5: Refactored Invoice Service

### 5.1 Complete Invoice Service

```php
<?php
// File: app/Services/Sales/InvoiceService.php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Base\AbstractDocumentService;
use App\Support\Results\ServiceResult;
use Illuminate\Database\Eloquent\Model;

class InvoiceService extends AbstractDocumentService implements InvoiceServiceInterface
{
    private JournalServiceInterface $journalService;
    private COGSRecognitionStrategy $cogsStrategy;

    public function __construct(
        InvoiceRepositoryInterface $repository,
        DocumentNumberGeneratorInterface $numberGenerator,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        JournalServiceInterface $journalService,
        COGSRecognitionStrategy $cogsStrategy
    ) {
        parent::__construct($eventDispatcher, $logger);

        $this->repository = $repository;
        $this->numberGenerator = $numberGenerator;
        $this->journalService = $journalService;
        $this->cogsStrategy = $cogsStrategy;
    }

    protected function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'INV-' . now()->format('Ym') . '-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return ['table' => 'invoices', 'column' => 'invoice_number'];
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

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
            'paid_amount' => 0,
        ];
    }

    /**
     * Create items with calculated line total.
     */
    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $item) {
            $lineTotal = (int) round($item['quantity'] * $item['unit_price']);

            InvoiceItem::create([
                'invoice_id' => $document->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'line_total' => $lineTotal,
                'revenue_account_id' => $item['revenue_account_id'] ?? null,
            ]);
        }
    }

    protected function loadRelations(Model $document): Model
    {
        return $document->fresh(['items', 'contact', 'journalEntry.lines.account']);
    }

    /**
     * Post invoice - create journal entries and transition to Sent.
     *
     * @return ServiceResult<Invoice>
     * @throws StateTransitionException
     */
    public function post(Invoice $invoice): ServiceResult
    {
        return $this->executeInTransaction('post', function () use ($invoice) {
            if (! $invoice->stateMachine()->canPost()) {
                throw StateTransitionException::actionNotAvailable(
                    'posting',
                    $invoice->status->label()
                );
            }

            // Create AR/Revenue journal entry
            $this->journalService->postInvoice($invoice);

            // Create COGS journal entry (if configured)
            $this->cogsStrategy->onInvoicePost($invoice);

            // Transition status
            $invoice->transitionTo(DocumentStatus::Sent, $this->getUserId());

            // Dispatch event
            $this->dispatch(InvoiceSent::fromInvoice($invoice, $this->getUserId()));

            return ServiceResult::success(
                $this->loadRelations($invoice),
                'Faktur berhasil diposting.'
            );
        }, ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount]);
    }

    /**
     * Void/cancel posted invoice.
     *
     * @return ServiceResult<Invoice>
     * @throws StateTransitionException
     */
    public function void(Invoice $invoice, string $reason): ServiceResult
    {
        return $this->executeInTransaction('void', function () use ($invoice, $reason) {
            if (! $invoice->stateMachine()->canCancel()) {
                throw StateTransitionException::actionNotAvailable(
                    'void',
                    $invoice->status->label()
                );
            }

            // Reverse journal entry
            if ($invoice->journal_entry_id) {
                $this->journalService->reverseEntry($invoice->journalEntry);
            }

            // Transition status
            $invoice->transitionTo(DocumentStatus::Cancelled, $this->getUserId(), [
                'void_reason' => $reason,
            ]);

            // Dispatch event
            $this->dispatch(InvoiceVoided::fromInvoice($invoice, $reason, $this->getUserId()));

            return ServiceResult::success(
                $this->loadRelations($invoice),
                'Faktur berhasil dibatalkan.'
            );
        }, ['invoice_id' => $invoice->id, 'reason' => $reason]);
    }
}
```

---

## Verification Checklist

After completing this phase, verify:

- [ ] ServiceResult objects implemented and used
- [ ] AbstractApplicationService provides consistent base
- [ ] AbstractDocumentService refactored to use repositories
- [ ] Query services created for read operations
- [ ] All services follow consistent structure
- [ ] Transaction management standardized
- [ ] Logging integrated in all service methods
- [ ] All existing tests still pass
- [ ] New service tests written

---

## Next Phase

Once Phase 3 is complete and verified, proceed to [Phase 4: Event-Driven Architecture](./05-phase-4-event-driven.md).
