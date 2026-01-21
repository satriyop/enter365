# Phase 2: Repository Pattern

> **Goal**: Abstract data access layer for better testability, flexibility, and adherence to Dependency Inversion Principle.

## Why Repository Pattern?

### Current Issues
1. **Direct Eloquent in Services**: Services directly use `Model::query()`, `Model::find()`, etc.
2. **Hard to Test**: Can't easily mock database queries in unit tests
3. **Coupled to Eloquent**: Changing data source requires modifying services
4. **Query Logic Scattered**: Same queries duplicated across services

### Benefits
1. **Testability**: Swap Eloquent repos with in-memory repos for unit tests
2. **Single Responsibility**: Services focus on business logic, repos on data access
3. **Centralized Queries**: One place for all queries related to an entity
4. **Flexibility**: Can swap data sources (Eloquent, API, Cache) easily

---

## Deliverables

- [ ] Base repository interface and implementation
- [ ] Domain-specific repositories for key entities
- [ ] Query object pattern for complex queries
- [ ] Specification pattern for reusable filters
- [ ] In-memory repository implementations for testing

---

## Part 1: Base Repository Infrastructure

### 1.1 Base Repository Interface

```php
<?php
// File: app/Contracts/Repositories/RepositoryInterface.php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Base repository interface for all repositories.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID.
     *
     * @return TModel|null
     */
    public function find(int $id): ?object;

    /**
     * Find entity by ID or throw exception.
     *
     * @return TModel
     * @throws \App\Exceptions\Domain\EntityNotFoundException
     */
    public function findOrFail(int $id): object;

    /**
     * Get all entities.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * Get paginated entities.
     *
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 25): LengthAwarePaginator;

    /**
     * Create new entity.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): object;

    /**
     * Update entity.
     *
     * @param TModel|int $entity
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function update(object|int $entity, array $data): object;

    /**
     * Delete entity.
     *
     * @param TModel|int $entity
     */
    public function delete(object|int $entity): bool;

    /**
     * Find entities matching criteria.
     *
     * @param array<string, mixed> $criteria
     * @return Collection<int, TModel>
     */
    public function findBy(array $criteria): Collection;

    /**
     * Find single entity matching criteria.
     *
     * @param array<string, mixed> $criteria
     * @return TModel|null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Count entities matching criteria.
     *
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;

    /**
     * Check if entity exists.
     *
     * @param array<string, mixed> $criteria
     */
    public function exists(array $criteria): bool;

    /**
     * Apply specification to query.
     *
     * @param SpecificationInterface $specification
     * @return Collection<int, TModel>
     */
    public function match(SpecificationInterface $specification): Collection;
}
```

### 1.2 Specification Interface

```php
<?php
// File: app/Contracts/Repositories/SpecificationInterface.php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Builder;

/**
 * Specification pattern for reusable query conditions.
 */
interface SpecificationInterface
{
    /**
     * Apply specification to query builder.
     */
    public function apply(Builder $query): Builder;

    /**
     * Combine with AND.
     */
    public function and(SpecificationInterface $other): SpecificationInterface;

    /**
     * Combine with OR.
     */
    public function or(SpecificationInterface $other): SpecificationInterface;

    /**
     * Negate this specification.
     */
    public function not(): SpecificationInterface;
}
```

### 1.3 Abstract Eloquent Repository

```php
<?php
// File: app/Infrastructure/Repositories/EloquentRepository.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Repositories\SpecificationInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Base Eloquent repository implementation.
 *
 * @template TModel of Model
 * @implements RepositoryInterface<TModel>
 */
abstract class EloquentRepository implements RepositoryInterface
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    /** @var array<string> Relations to eager load */
    protected array $with = [];

    public function find(int $id): ?object
    {
        return $this->newQuery()->find($id);
    }

    public function findOrFail(int $id): object
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw new EntityNotFoundException($this->getEntityName(), $id);
        }

        return $entity;
    }

    public function all(): Collection
    {
        return $this->newQuery()->get();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $data): object
    {
        $model = new $this->modelClass($data);
        $model->save();

        return $model->fresh($this->with);
    }

    public function update(object|int $entity, array $data): object
    {
        $model = is_int($entity) ? $this->findOrFail($entity) : $entity;
        $model->update($data);

        return $model->fresh($this->with);
    }

    public function delete(object|int $entity): bool
    {
        $model = is_int($entity) ? $this->findOrFail($entity) : $entity;

        return $model->delete();
    }

    public function findBy(array $criteria): Collection
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->get();
    }

    public function findOneBy(array $criteria): ?object
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    public function count(array $criteria = []): int
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->count();
    }

    public function exists(array $criteria): bool
    {
        $query = $this->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->exists();
    }

    public function match(SpecificationInterface $specification): Collection
    {
        return $specification->apply($this->newQuery())->get();
    }

    /**
     * Create new query builder.
     *
     * @return Builder<TModel>
     */
    protected function newQuery(): Builder
    {
        $query = $this->modelClass::query();

        if (! empty($this->with)) {
            $query->with($this->with);
        }

        return $query;
    }

    /**
     * Get entity name for error messages.
     */
    protected function getEntityName(): string
    {
        return class_basename($this->modelClass);
    }
}
```

### 1.4 Abstract Specification

```php
<?php
// File: app/Infrastructure/Repositories/Specifications/AbstractSpecification.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use App\Contracts\Repositories\SpecificationInterface;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractSpecification implements SpecificationInterface
{
    public function and(SpecificationInterface $other): SpecificationInterface
    {
        return new AndSpecification($this, $other);
    }

    public function or(SpecificationInterface $other): SpecificationInterface
    {
        return new OrSpecification($this, $other);
    }

    public function not(): SpecificationInterface
    {
        return new NotSpecification($this);
    }
}
```

```php
<?php
// File: app/Infrastructure/Repositories/Specifications/AndSpecification.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Specifications;

use Illuminate\Database\Eloquent\Builder;

class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private AbstractSpecification $left,
        private AbstractSpecification $right
    ) {}

    public function apply(Builder $query): Builder
    {
        $this->left->apply($query);
        $this->right->apply($query);

        return $query;
    }
}
```

---

## Part 2: Domain-Specific Repositories

### 2.1 Invoice Repository

```php
<?php
// File: app/Contracts/Repositories/Sales/InvoiceRepositoryInterface.php

declare(strict_types=1);

namespace App\Contracts\Repositories\Sales;

use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Invoice>
 */
interface InvoiceRepositoryInterface extends RepositoryInterface
{
    /**
     * Find invoices by status.
     *
     * @return Collection<int, Invoice>
     */
    public function findByStatus(DocumentStatus $status): Collection;

    /**
     * Find overdue invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function findOverdue(): Collection;

    /**
     * Find invoices for contact.
     *
     * @return Collection<int, Invoice>
     */
    public function findByContact(int $contactId): Collection;

    /**
     * Find invoices in date range.
     *
     * @return Collection<int, Invoice>
     */
    public function findByDateRange(DateRange $range): Collection;

    /**
     * Get total outstanding amount for contact.
     */
    public function getOutstandingForContact(int $contactId): int;

    /**
     * Get invoice with all relations loaded.
     */
    public function findWithRelations(int $id): ?Invoice;

    /**
     * Find by invoice number.
     */
    public function findByNumber(string $invoiceNumber): ?Invoice;
}
```

```php
<?php
// File: app/Infrastructure/Repositories/Sales/EloquentInvoiceRepository.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<Invoice>
 */
class EloquentInvoiceRepository extends EloquentRepository implements InvoiceRepositoryInterface
{
    protected string $modelClass = Invoice::class;
    protected array $with = ['contact', 'items'];

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->newQuery()
            ->where('status', $status)
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function findOverdue(): Collection
    {
        return $this->newQuery()
            ->where('due_date', '<', now())
            ->whereNotIn('status', [
                DocumentStatus::Paid,
                DocumentStatus::Cancelled,
                DocumentStatus::Draft,
            ])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->newQuery()
            ->where('contact_id', $contactId)
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->newQuery()
            ->whereBetween('invoice_date', [
                $range->start->toDateString(),
                $range->end->toDateString(),
            ])
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function getOutstandingForContact(int $contactId): int
    {
        return (int) $this->newQuery()
            ->where('contact_id', $contactId)
            ->whereNotIn('status', [DocumentStatus::Paid, DocumentStatus::Cancelled, DocumentStatus::Draft])
            ->selectRaw('SUM(total_amount - paid_amount) as outstanding')
            ->value('outstanding') ?? 0;
    }

    public function findWithRelations(int $id): ?Invoice
    {
        return $this->newQuery()
            ->with([
                'contact',
                'items.product',
                'items.revenueAccount',
                'journalEntry.lines.account',
                'payments',
            ])
            ->find($id);
    }

    public function findByNumber(string $invoiceNumber): ?Invoice
    {
        return $this->findOneBy(['invoice_number' => $invoiceNumber]);
    }
}
```

### 2.2 Work Order Repository

```php
<?php
// File: app/Contracts/Repositories/Manufacturing/WorkOrderRepositoryInterface.php

declare(strict_types=1);

namespace App\Contracts\Repositories\Manufacturing;

use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<WorkOrder>
 */
interface WorkOrderRepositoryInterface extends RepositoryInterface
{
    /**
     * Find work orders by status.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByStatus(DocumentStatus $status): Collection;

    /**
     * Find work orders for project.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByProject(int $projectId): Collection;

    /**
     * Find work orders for product.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findByProduct(int $productId): Collection;

    /**
     * Find in-progress work orders.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findInProgress(): Collection;

    /**
     * Find overdue work orders.
     *
     * @return Collection<int, WorkOrder>
     */
    public function findOverdue(): Collection;

    /**
     * Get work order with all relations.
     */
    public function findWithRelations(int $id): ?WorkOrder;

    /**
     * Get material requirements for work order.
     *
     * @return Collection<int, array{product_id: int, quantity: float, unit: string}>
     */
    public function getMaterialRequirements(int $workOrderId): Collection;

    /**
     * Get statistics for date range.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(DateRange $range): array;
}
```

### 2.3 Product Stock Repository

```php
<?php
// File: app/Contracts/Repositories/Inventory/ProductStockRepositoryInterface.php

declare(strict_types=1);

namespace App\Contracts\Repositories\Inventory;

use App\Contracts\Repositories\RepositoryInterface;
use App\Models\Inventory\ProductStock;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<ProductStock>
 */
interface ProductStockRepositoryInterface extends RepositoryInterface
{
    /**
     * Get stock for product at warehouse.
     */
    public function getStock(int $productId, int $warehouseId): ?ProductStock;

    /**
     * Get stock for product at warehouse, create if not exists.
     */
    public function getOrCreateStock(int $productId, int $warehouseId): ProductStock;

    /**
     * Get all stock for product across warehouses.
     *
     * @return Collection<int, ProductStock>
     */
    public function getStockByProduct(int $productId): Collection;

    /**
     * Get all stock for warehouse.
     *
     * @return Collection<int, ProductStock>
     */
    public function getStockByWarehouse(int $warehouseId): Collection;

    /**
     * Get total available quantity for product.
     */
    public function getTotalAvailable(int $productId): float;

    /**
     * Check if sufficient stock available.
     */
    public function hasSufficientStock(int $productId, int $warehouseId, float $required): bool;

    /**
     * Get low stock products (below reorder level).
     *
     * @return Collection<int, ProductStock>
     */
    public function findLowStock(): Collection;

    /**
     * Reserve stock.
     */
    public function reserve(int $productId, int $warehouseId, float $quantity): bool;

    /**
     * Release reserved stock.
     */
    public function releaseReservation(int $productId, int $warehouseId, float $quantity): bool;
}
```

---

## Part 3: In-Memory Repository for Testing

### 3.1 In-Memory Repository Base

```php
<?php
// File: app/Infrastructure/Repositories/InMemory/InMemoryRepository.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\InMemory;

use App\Contracts\Repositories\RepositoryInterface;
use App\Contracts\Repositories\SpecificationInterface;
use App\Exceptions\Domain\EntityNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * In-memory repository for unit testing.
 *
 * @template TModel
 * @implements RepositoryInterface<TModel>
 */
abstract class InMemoryRepository implements RepositoryInterface
{
    /** @var Collection<int, TModel> */
    protected Collection $items;

    protected int $lastId = 0;

    protected string $entityName = 'Entity';

    public function __construct()
    {
        $this->items = collect();
    }

    public function find(int $id): ?object
    {
        return $this->items->get($id);
    }

    public function findOrFail(int $id): object
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw new EntityNotFoundException($this->entityName, $id);
        }

        return $entity;
    }

    public function all(): Collection
    {
        return $this->items->values();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $items = $this->items->values();
        $page = request()->get('page', 1);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $items->count(), $perPage, $page);
    }

    public function create(array $data): object
    {
        $this->lastId++;
        $entity = $this->hydrate($data, $this->lastId);
        $this->items->put($this->lastId, $entity);

        return $entity;
    }

    public function update(object|int $entity, array $data): object
    {
        $id = is_int($entity) ? $entity : $entity->id;
        $existing = $this->findOrFail($id);

        foreach ($data as $key => $value) {
            $existing->{$key} = $value;
        }

        return $existing;
    }

    public function delete(object|int $entity): bool
    {
        $id = is_int($entity) ? $entity : $entity->id;
        $this->items->forget($id);

        return true;
    }

    public function findBy(array $criteria): Collection
    {
        return $this->items->filter(function ($item) use ($criteria) {
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    if (! in_array($item->{$field}, $value)) {
                        return false;
                    }
                } elseif ($item->{$field} !== $value) {
                    return false;
                }
            }
            return true;
        })->values();
    }

    public function findOneBy(array $criteria): ?object
    {
        return $this->findBy($criteria)->first();
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return $this->items->count();
        }

        return $this->findBy($criteria)->count();
    }

    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    public function match(SpecificationInterface $specification): Collection
    {
        // For in-memory, we need to implement specification matching differently
        // This is a simplified version that filters in PHP
        return $this->items->filter(function ($item) use ($specification) {
            return $this->matchesSpecification($item, $specification);
        })->values();
    }

    /**
     * Clear all items (useful in tests).
     */
    public function clear(): void
    {
        $this->items = collect();
        $this->lastId = 0;
    }

    /**
     * Seed with items (useful in tests).
     *
     * @param array<TModel> $items
     */
    public function seed(array $items): void
    {
        foreach ($items as $item) {
            $this->items->put($item->id, $item);
            $this->lastId = max($this->lastId, $item->id);
        }
    }

    /**
     * Hydrate entity from data.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    abstract protected function hydrate(array $data, int $id): object;

    /**
     * Check if item matches specification.
     */
    protected function matchesSpecification(object $item, SpecificationInterface $specification): bool
    {
        // Override in subclasses for specification support
        return true;
    }
}
```

### 3.2 In-Memory Invoice Repository

```php
<?php
// File: app/Infrastructure/Repositories/InMemory/InMemoryInvoiceRepository.php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\InMemory;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * In-memory invoice repository for testing.
 *
 * @extends InMemoryRepository<Invoice>
 */
class InMemoryInvoiceRepository extends InMemoryRepository implements InvoiceRepositoryInterface
{
    protected string $entityName = 'Invoice';

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->findBy(['status' => $status]);
    }

    public function findOverdue(): Collection
    {
        return $this->items->filter(function (Invoice $invoice) {
            return $invoice->due_date < now()
                && ! in_array($invoice->status, [
                    DocumentStatus::Paid,
                    DocumentStatus::Cancelled,
                    DocumentStatus::Draft,
                ]);
        })->values();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->findBy(['contact_id' => $contactId]);
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->items->filter(function (Invoice $invoice) use ($range) {
            return $range->contains($invoice->invoice_date);
        })->values();
    }

    public function getOutstandingForContact(int $contactId): int
    {
        return $this->items
            ->filter(function (Invoice $invoice) use ($contactId) {
                return $invoice->contact_id === $contactId
                    && ! in_array($invoice->status, [
                        DocumentStatus::Paid,
                        DocumentStatus::Cancelled,
                        DocumentStatus::Draft,
                    ]);
            })
            ->sum(fn (Invoice $invoice) => $invoice->total_amount - $invoice->paid_amount);
    }

    public function findWithRelations(int $id): ?Invoice
    {
        return $this->find($id);
    }

    public function findByNumber(string $invoiceNumber): ?Invoice
    {
        return $this->findOneBy(['invoice_number' => $invoiceNumber]);
    }

    protected function hydrate(array $data, int $id): object
    {
        $invoice = new Invoice($data);
        $invoice->id = $id;
        $invoice->exists = true;

        return $invoice;
    }
}
```

---

## Part 4: Register Repositories

### 4.1 Create Repository Service Provider

```php
<?php
// File: app/Providers/RepositoryServiceProvider.php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Sales Repositories
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Infrastructure\Repositories\Sales\EloquentInvoiceRepository;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Infrastructure\Repositories\Sales\EloquentQuotationRepository;

// Purchasing Repositories
use App\Contracts\Repositories\Purchasing\BillRepositoryInterface;
use App\Infrastructure\Repositories\Purchasing\EloquentBillRepository;

use App\Contracts\Repositories\Purchasing\PurchaseOrderRepositoryInterface;
use App\Infrastructure\Repositories\Purchasing\EloquentPurchaseOrderRepository;

// Manufacturing Repositories
use App\Contracts\Repositories\Manufacturing\WorkOrderRepositoryInterface;
use App\Infrastructure\Repositories\Manufacturing\EloquentWorkOrderRepository;

use App\Contracts\Repositories\Manufacturing\BomRepositoryInterface;
use App\Infrastructure\Repositories\Manufacturing\EloquentBomRepository;

// Inventory Repositories
use App\Contracts\Repositories\Inventory\ProductRepositoryInterface;
use App\Infrastructure\Repositories\Inventory\EloquentProductRepository;

use App\Contracts\Repositories\Inventory\ProductStockRepositoryInterface;
use App\Infrastructure\Repositories\Inventory\EloquentProductStockRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository bindings.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        // Sales
        InvoiceRepositoryInterface::class => EloquentInvoiceRepository::class,
        QuotationRepositoryInterface::class => EloquentQuotationRepository::class,

        // Purchasing
        BillRepositoryInterface::class => EloquentBillRepository::class,
        PurchaseOrderRepositoryInterface::class => EloquentPurchaseOrderRepository::class,

        // Manufacturing
        WorkOrderRepositoryInterface::class => EloquentWorkOrderRepository::class,
        BomRepositoryInterface::class => EloquentBomRepository::class,

        // Inventory
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        ProductStockRepositoryInterface::class => EloquentProductStockRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
```

### 4.2 Register Provider

```php
// Add to bootstrap/providers.php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AccountingServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,  // Add this
];
```

---

## Part 5: Refactor Services to Use Repositories

### 5.1 Example: InvoiceService Refactored

```php
<?php
// File: app/Services/Sales/InvoiceService.php (refactored)

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;
use App\Traits\LogsPerformance;
use Illuminate\Support\Facades\DB;

class InvoiceService implements InvoiceServiceInterface
{
    use LogsPerformance;

    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private JournalServiceInterface $journalService,
        private COGSRecognitionStrategy $cogsStrategy,
        private DocumentNumberGeneratorInterface $numberGenerator
    ) {}

    public function create(array $data): Invoice
    {
        return $this->loggedExecution(__METHOD__, function () use ($data) {
            return DB::transaction(function () use ($data) {
                $items = $data['items'] ?? [];
                unset($data['items']);

                // Set defaults
                $data = array_merge($this->getDefaultData(), $data);
                $data['created_by'] = $data['created_by'] ?? auth()->id();

                // Generate document number
                if (empty($data['invoice_number'])) {
                    $prefix = 'INV-' . now()->format('Ym') . '-';
                    $data['invoice_number'] = $this->numberGenerator->generate(
                        $prefix,
                        'invoices',
                        'invoice_number'
                    );
                }

                // Set initial status
                $data['status'] = DocumentStatus::Draft;

                // Create via repository
                $invoice = $this->repository->create($data);

                // Create items
                if (! empty($items)) {
                    $this->createItems($invoice, $items);
                }

                // Calculate totals
                $invoice->calculateTotals();
                $invoice->save();

                return $this->repository->findWithRelations($invoice->id);
            });
        }, ['contact_id' => $data['contact_id'] ?? null]);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $this->validateEditable($invoice);

        return $this->loggedExecution(__METHOD__, function () use ($invoice, $data) {
            return DB::transaction(function () use ($invoice, $data) {
                $items = $data['items'] ?? null;
                unset($data['items']);

                $this->repository->update($invoice, $data);

                if ($items !== null) {
                    $invoice->items()->delete();
                    $this->createItems($invoice, $items);
                }

                $invoice->calculateTotals();
                $invoice->save();

                return $this->repository->findWithRelations($invoice->id);
            });
        }, ['invoice_id' => $invoice->id]);
    }

    public function delete(Invoice $invoice): bool
    {
        $this->validateDeletable($invoice);

        return $this->loggedExecution(__METHOD__, function () use ($invoice) {
            return DB::transaction(function () use ($invoice) {
                $invoice->items()->delete();
                return $this->repository->delete($invoice);
            });
        }, ['invoice_id' => $invoice->id]);
    }

    public function post(Invoice $invoice): Invoice
    {
        return $this->loggedExecution(__METHOD__, function () use ($invoice) {
            return $this->measurePerformance('invoice.post', function () use ($invoice) {
                if (! $invoice->stateMachine()->canPost()) {
                    throw StateTransitionException::actionNotAvailable(
                        'posting',
                        $invoice->status->label()
                    );
                }

                $this->journalService->postInvoice($invoice);
                $this->cogsStrategy->onInvoicePost($invoice);
                $invoice->transitionTo(DocumentStatus::Sent, auth()->id());

                return $this->repository->findWithRelations($invoice->id);
            }, ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount]);
        }, ['invoice_id' => $invoice->id]);
    }

    // ... rest of methods
}
```

---

## Verification Checklist

After completing this phase, verify:

- [ ] Base repository interface and implementation complete
- [ ] Specification pattern implemented
- [ ] All key domain repositories created (Invoice, Bill, WorkOrder, Product, etc.)
- [ ] In-memory repositories for testing created
- [ ] RepositoryServiceProvider registered
- [ ] Services refactored to use repositories
- [ ] All existing tests still pass
- [ ] New repository tests written

---

## Tests to Add

```php
<?php
// File: tests/Unit/Repositories/InvoiceRepositoryTest.php

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;

describe('InvoiceRepository', function () {

    beforeEach(function () {
        $this->repository = app(InvoiceRepositoryInterface::class);
    });

    it('finds invoice by id', function () {
        $invoice = Invoice::factory()->create();

        $found = $this->repository->find($invoice->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($invoice->id);
    });

    it('finds invoices by status', function () {
        Invoice::factory()->count(3)->create(['status' => DocumentStatus::Draft]);
        Invoice::factory()->count(2)->create(['status' => DocumentStatus::Sent]);

        $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

        expect($drafts)->toHaveCount(3);
    });

    it('finds overdue invoices', function () {
        Invoice::factory()->create([
            'status' => DocumentStatus::Sent,
            'due_date' => now()->subDays(5),
        ]);
        Invoice::factory()->create([
            'status' => DocumentStatus::Sent,
            'due_date' => now()->addDays(5),
        ]);

        $overdue = $this->repository->findOverdue();

        expect($overdue)->toHaveCount(1);
    });

    it('calculates outstanding for contact', function () {
        $contactId = 1;
        Invoice::factory()->create([
            'contact_id' => $contactId,
            'status' => DocumentStatus::Sent,
            'total_amount' => 100000,
            'paid_amount' => 30000,
        ]);
        Invoice::factory()->create([
            'contact_id' => $contactId,
            'status' => DocumentStatus::Partial,
            'total_amount' => 50000,
            'paid_amount' => 20000,
        ]);

        $outstanding = $this->repository->getOutstandingForContact($contactId);

        expect($outstanding)->toBe(100000); // (100000-30000) + (50000-20000)
    });
});
```

---

## Next Phase

Once Phase 2 is complete and verified, proceed to [Phase 3: Service Layer Refinement](./04-phase-3-service-layer.md).
