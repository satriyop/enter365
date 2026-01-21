# Repository Pattern Reference

Repository pattern implementation for data access abstraction and testability.

---

## Overview

Repositories provide a consistent API for data access, enabling:
- **Testability**: Swap Eloquent with in-memory implementations
- **Domain queries**: Encapsulate business logic in named methods
- **Decoupling**: Services don't depend on Eloquent directly

### Directory Structure

```
app/Contracts/Repositories/
├── RepositoryInterface.php           # Base interface
├── SpecificationInterface.php        # Query specification
├── Sales/
│   ├── InvoiceRepositoryInterface.php
│   └── QuotationRepositoryInterface.php
└── Manufacturing/
    └── WorkOrderRepositoryInterface.php

app/Infrastructure/Repositories/
├── EloquentRepository.php            # Base implementation
├── Sales/
│   ├── EloquentInvoiceRepository.php
│   └── EloquentQuotationRepository.php
└── InMemory/
    ├── InMemoryRepository.php        # For testing
    └── InMemoryInvoiceRepository.php
```

---

## Creating a New Repository

### Step 1: Create Interface

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\Sales;

use App\Contracts\Repositories\RepositoryInterface;
use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Quotation>
 */
interface QuotationRepositoryInterface extends RepositoryInterface
{
    // Domain-specific queries
    public function findByNumber(string $number): ?Quotation;
    public function findExpiringSoon(int $days = 7): Collection;
    public function findPendingApproval(): Collection;
    public function findNeedingFollowUp(): Collection;

    // Statistics (use DB::table for performance)
    public function getWinRateStats(): array;
}
```

### Step 2: Create Implementation

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Sales\Quotation;

/**
 * @extends EloquentRepository<Quotation>
 */
class EloquentQuotationRepository extends EloquentRepository implements QuotationRepositoryInterface
{
    protected string $modelClass = Quotation::class;

    // Default eager loads
    protected array $with = ['contact', 'items'];

    public function findByNumber(string $number): ?Quotation
    {
        return $this->findOneBy(['quotation_number' => $number]);
    }

    public function findExpiringSoon(int $days = 7): Collection
    {
        return $this->newQuery()
            ->whereIn('status', [
                DocumentStatus::Draft,
                DocumentStatus::Submitted,
                DocumentStatus::Approved,
            ])
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>', now())
            ->orderBy('valid_until', 'asc')
            ->get();
    }
}
```

### Step 3: Register Binding

```php
// app/Providers/RepositoryServiceProvider.php

public array $bindings = [
    QuotationRepositoryInterface::class => EloquentQuotationRepository::class,
];
```

---

## Base Interface Methods

`RepositoryInterface` provides these methods (inherited by all repositories):

| Method | Description |
|--------|-------------|
| `find(int $id)` | Find by ID, returns null if not found |
| `findOrFail(int $id)` | Find by ID or throw `EntityNotFoundException` |
| `all()` | Get all entities |
| `paginate(int $perPage)` | Paginated results |
| `create(array $data)` | Create new entity |
| `update($entity, array $data)` | Update entity |
| `delete($entity)` | Delete entity |
| `findBy(array $criteria)` | Find by criteria |
| `findOneBy(array $criteria)` | Find single entity by criteria |
| `count(array $criteria)` | Count matching entities |
| `exists(array $criteria)` | Check if entity exists |
| `match(SpecificationInterface $spec)` | Apply specification pattern |

---

## Domain Query Method Naming

Use descriptive names that express business intent:

| Bad | Good | Why |
|-----|------|-----|
| `getByStatus('submitted')` | `findPendingApproval()` | Expresses business concept |
| `getWhereFollowUpPast()` | `findNeedingFollowUp()` | Action-oriented |
| `getAllActive()` | `findActive()` | Consistent `find` prefix |
| `getStats()` | `getWinRateStats()` | Specific about what stats |

### Common Patterns

```php
// By identifier
public function findByNumber(string $number): ?Model;
public function findByContact(int $contactId): Collection;

// By status/state
public function findPendingApproval(): Collection;
public function findActive(): Collection;
public function findOverdue(): Collection;

// Time-based
public function findExpiringSoon(int $days = 7): Collection;
public function findByDateRange(DateRange $range): Collection;

// Assignment
public function findAssignedTo(int $userId): Collection;
public function findUnassigned(): Collection;

// With relations
public function findWithRelations(int $id): ?Model;
```

---

## Performance: DB::table() for Aggregations

**Key Insight:** Use `DB::table()` for read-only aggregation queries to avoid Eloquent hydration overhead.

### When to Use DB::table()

| Use Case | Method | Why |
|----------|--------|-----|
| Aggregations (SUM, COUNT, AVG) | `DB::table()` | No model needed for numbers |
| Dashboard statistics | `DB::table()` | Performance critical |
| Reports with 100+ rows | `DB::table()` | Avoids memory overhead |
| Simple existence checks | Either | Low impact |
| CRUD operations | Eloquent | Need events, casts, mutators |

### Example: Statistics Query

```php
/**
 * Get win/loss rate statistics.
 *
 * Uses DB::table for performance - no model hydration needed.
 */
public function getWinRateStats(?DateRange $range = null): array
{
    $query = DB::table('quotations')
        ->whereIn('status', [
            DocumentStatus::Approved->value,
            DocumentStatus::Converted->value,
        ]);

    if ($range !== null) {
        $query->whereBetween('quotation_date', [
            $range->start->toDateString(),
            $range->end->toDateString(),
        ]);
    }

    $results = $query
        ->selectRaw('COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN outcome = 'won' THEN 1 ELSE 0 END) as won")
        ->selectRaw("SUM(CASE WHEN outcome = 'lost' THEN 1 ELSE 0 END) as lost")
        ->first();

    $won = (int) ($results->won ?? 0);
    $lost = (int) ($results->lost ?? 0);
    $decided = $won + $lost;

    return [
        'total' => (int) $results->total,
        'won' => $won,
        'lost' => $lost,
        'win_rate' => $decided > 0 ? round(($won / $decided) * 100, 2) : 0.0,
    ];
}
```

### Example: Grouped Totals

```php
public function getValueByStatus(): array
{
    $results = DB::table('quotations')
        ->select('status')
        ->selectRaw('COUNT(*) as count')
        ->selectRaw('COALESCE(SUM(total), 0) as total_value')
        ->groupBy('status')
        ->get();

    return $results->map(fn ($row) => [
        'status' => DocumentStatus::from($row->status),
        'count' => (int) $row->count,
        'total_value' => (int) $row->total_value,
    ])->all();
}
```

---

## Using Model Scopes in Repository

Leverage existing model scopes for consistency:

```php
// Model has scope
class Quotation extends Model
{
    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now());
    }
}

// Repository uses it
public function findNeedingFollowUp(): Collection
{
    return $this->newQuery()
        ->needsFollowUp()  // Use model scope
        ->orderBy('next_follow_up_at', 'asc')
        ->get();
}
```

---

## Testing Repositories

### Feature Test Pattern

```php
<?php

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Infrastructure\Repositories\Sales\EloquentQuotationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(QuotationRepositoryInterface::class);
});

test('repository is bound to eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentQuotationRepository::class);
});

test('findExpiringSoon returns quotations expiring within days', function () {
    // Expiring in 3 days (should be found)
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => now()->addDays(3),
    ]);

    // Expiring in 10 days (outside default 7 days)
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => now()->addDays(10),
    ]);

    // Already expired
    Quotation::factory()->create([
        'status' => DocumentStatus::Approved,
        'valid_until' => now()->subDays(1),
    ]);

    $expiring = $this->repository->findExpiringSoon(7);

    expect($expiring)->toHaveCount(1);
});

test('getWinRateStats returns correct statistics', function () {
    Quotation::factory()->count(3)->won()->create();
    Quotation::factory()->count(2)->lost()->create();

    $stats = $this->repository->getWinRateStats();

    expect($stats['won'])->toBe(3)
        ->and($stats['lost'])->toBe(2)
        ->and($stats['win_rate'])->toBe(60.0);
});
```

### In-Memory Repository for Unit Tests

For true unit tests without database, use in-memory repositories. Located in `tests/Support/`.

**Key Design Principles:**

| Feature | Purpose |
|---------|---------|
| Auto-increment IDs | Simulate database behavior |
| `reset()` | Clear state between tests |
| `seed(array $entities)` | Pre-populate for test scenarios |
| `getCollection()` | Direct access for assertions |

```php
// tests/Support/InMemoryQuotationRepository.php

class InMemoryQuotationRepository implements QuotationRepositoryInterface
{
    private Collection $quotations;
    private int $nextId = 1;

    public function __construct()
    {
        $this->quotations = collect();
    }

    public function reset(): void
    {
        $this->quotations = collect();
        $this->nextId = 1;
    }

    public function seed(array $quotations): void
    {
        foreach ($quotations as $quotation) {
            if ($quotation->id) {
                $this->nextId = max($this->nextId, $quotation->id + 1);
            }
            $this->quotations->put($quotation->id ?? $this->nextId++, $quotation);
        }
    }

    public function find(int $id): ?Quotation
    {
        return $this->quotations->get($id);
    }

    public function findOrFail(int $id): Quotation
    {
        $quotation = $this->find($id);
        if ($quotation === null) {
            throw new EntityNotFoundException('Quotation', $id);
        }
        return $quotation;
    }

    public function create(array $data): Quotation
    {
        $quotation = new Quotation($data);
        $quotation->id = $this->nextId++;
        $quotation->created_at = Carbon::now();
        $quotation->updated_at = Carbon::now();

        if (! isset($quotation->status)) {
            $quotation->status = DocumentStatus::Draft;
        }

        $this->quotations->put($quotation->id, $quotation);
        return $quotation;
    }

    // Domain-specific methods use filtering
    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->status === $status)
            ->values();
    }

    public function getCollection(): Collection
    {
        return $this->quotations;
    }
}
```

### In-Memory Repository Gotchas

**1. SpecificationInterface Not Supported**

Specifications use Eloquent Builder, which doesn't work in-memory:

```php
public function match(SpecificationInterface $specification): Collection
{
    // ❌ Cannot use specifications in-memory
    throw new RuntimeException(
        'Specifications are not supported in InMemoryQuotationRepository. '
        . 'Use findBy() with explicit criteria for unit testing.'
    );
}
```

**2. DateRange Contains() Type Issue**

When filtering by DateRange, convert Carbon to string:

```php
public function findByDateRange(DateRange $range): Collection
{
    return $this->quotations
        ->filter(function (Quotation $q) use ($range) {
            $date = $q->quotation_date ?? $q->created_at;

            // Convert to string - DateRange expects string, not Carbon
            $dateString = $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date;

            return $range->contains($dateString);
        })
        ->values();
}
```

See: [SKILL.md#21](SKILL.md#21-daterange-value-object-expects-string-not-carbon)

### Available In-Memory Repositories

| Repository | Location | Tests |
|------------|----------|-------|
| `InMemoryQuotationRepository` | `tests/Support/` | `tests/Unit/Support/InMemoryQuotationRepositoryTest.php` |

Create additional in-memory repositories following this pattern when needed for unit testing other services.

---

## Available Repositories

| Interface | Implementation | Domain Methods |
|-----------|----------------|----------------|
| `InvoiceRepositoryInterface` | `EloquentInvoiceRepository` | `findOverdue()`, `findByContact()`, `getOutstandingForContact()` |
| `QuotationRepositoryInterface` | `EloquentQuotationRepository` | `findExpiringSoon()`, `findPendingApproval()`, `findNeedingFollowUp()`, `getWinRateStats()` |
| `WorkOrderRepositoryInterface` | `EloquentWorkOrderRepository` | Work order specific queries |
| `ProductStockRepositoryInterface` | `EloquentProductStockRepository` | Stock level queries |

See: [SERVICE_BINDINGS.md](SERVICE_BINDINGS.md#repositories) for binding configuration.

---

## When NOT to Use Repository

Repositories add indirection. Skip them when:

| Scenario | Use Instead |
|----------|-------------|
| Simple controller CRUD | Direct Eloquent in controller |
| One-off admin queries | `Model::query()` directly |
| Tinker/debugging | Direct Eloquent |
| Prototype/spike | Direct Eloquent, refactor later |

Repositories are most valuable when:
- Service needs testing without database
- Multiple services share same query logic
- Query logic is complex enough to name
