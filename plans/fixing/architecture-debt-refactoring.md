# Architecture Debt Refactoring Plan

> **Created:** 2026-01-21
> **Updated:** 2026-01-22
> **Status:** Complete (All Phases Done)
> **Priority:** High
> **Estimated Total Effort:** 20-30 hours

---

## Completion Summary

### Phase 1: Quick Wins ✅ COMPLETED

| Task | Status | Date | Notes |
|------|--------|------|-------|
| 1.1 OperationContext | ✅ Done | 2026-01-21 | Full implementation with factory methods |
| 1.2 Interface Contracts | ✅ Done | 2026-01-21 | QuotationServiceInterface + QuotationConversionServiceInterface |
| Test Helpers | ✅ Done | 2026-01-21 | TestsWithOperationContext trait |
| Skills Documentation | ✅ Done | 2026-01-21 | ARCHITECTURE_PATTERNS.md created |

**Files Created:**
- `app/Support/OperationContext.php` - Immutable context with 6 factory methods
- `app/Contracts/Sales/QuotationConversionServiceInterface.php`
- `tests/Unit/Support/OperationContextTest.php` - 13 tests, all passing
- `tests/Traits/TestsWithOperationContext.php` - Test helper trait
- `.claude/skills/enter365/ARCHITECTURE_PATTERNS.md` - Architecture documentation

**Files Modified:**
- `app/Services/Base/AbstractApplicationService.php` - Added `withContext()`, `getContext()`, updated `getUserId()`
- `app/Contracts/Sales/QuotationServiceInterface.php` - Added `cancel()`, `markAsSent()`
- `app/Services/Sales/QuotationConversionService.php` - Now implements interface
- `app/Providers/AppServiceProvider.php` - New binding registered
- `.claude/skills/enter365/SERVICE_BINDINGS.md` - Added QuotationConversionServiceInterface
- `.claude/skills/enter365/TESTING_PATTERNS.md` - Added OperationContext section
- `.claude/skills/enter365/SKILL.md` - Added gotcha #6b, reference to new skill

### Phase 2: Model Slimming ✅ COMPLETED

| Task | Status | Date | Notes |
|------|--------|------|-------|
| 2.1 Create QuotationDomainFactory | ✅ Done | 2026-01-21 | Factory with proper DI for domain objects |
| 2.2 Update services to use factory | ✅ Done | 2026-01-21 | QuotationService + QuotationConversionService |
| 2.3 Remove business logic from model | ✅ Done | 2026-01-21 | Removed mutation methods, kept read-only accessors |

**Files Created:**
- `app/Domain/Sales/Quotations/QuotationDomainFactory.php` - Factory with stateMachine, outcomeManager, followUpManager, calculator

**Files Modified:**
- `app/Services/Sales/QuotationService.php` - Uses factory for state machine and calculations
- `app/Services/Sales/QuotationConversionService.php` - Uses factory for canConvert checks
- `app/Models/Sales/Quotation.php` - Reduced from 735 → 650 lines (~12%)
- `app/Providers/AppServiceProvider.php` - QuotationDomainFactory registered as singleton

**Methods Removed from Quotation Model:**
- `transitionTo()` - Use factory's stateMachine().transitionTo()
- `outcomeManager()` - Use factory.outcomeManager()
- `followUpManager()` - Use factory.followUpManager()
- `scheduleFollowUp()` - Use factory.followUpManager().scheduleFollowUp()
- `recordContact()` - Use factory.followUpManager().recordContact()
- `calculateAutoFollowUpDate()` - Use factory.followUpManager().calculateAutoFollowUpDate()
- `markAsWon()` - Use factory.outcomeManager().markAsWon()
- `markAsLost()` - Use factory.outcomeManager().markAsLost()
- `calculateTotals()` - Use factory.applyTotals()

**Methods Kept (Read-Only Accessors):**
- `stateMachine()` - For read-only state checks (with note about mutations)
- `isEditable()`, `canSubmit()`, `canApprove()`, etc. - Convenience accessors
- `needsFollowUp()`, `getDaysSinceLastContact()` - API resource accessors
- `getOutcomeLabel()`, `getPriorityLabel()`, `getStatusLabel()` - Label accessors

### Phase 3.1: Quotation Repository ✅ COMPLETED

| Task | Status | Date | Notes |
|------|--------|------|-------|
| 3.1.1 Create QuotationRepositoryInterface | ✅ Done | 2026-01-21 | With domain-specific query methods |
| 3.1.2 Create EloquentQuotationRepository | ✅ Done | 2026-01-21 | Full implementation |
| 3.1.3 Register binding | ✅ Done | 2026-01-21 | In RepositoryServiceProvider |
| 3.1.4 Create tests | ✅ Done | 2026-01-21 | 23 tests, all passing |

**Files Created:**
- `app/Contracts/Repositories/Sales/QuotationRepositoryInterface.php` - Interface with 15 domain-specific methods
- `app/Infrastructure/Repositories/Sales/EloquentQuotationRepository.php` - Eloquent implementation
- `tests/Feature/Infrastructure/Repositories/EloquentQuotationRepositoryTest.php` - 23 tests

**Files Modified:**
- `app/Providers/RepositoryServiceProvider.php` - Added QuotationRepositoryInterface binding

**Repository Methods:**
- Base CRUD: `find()`, `findOrFail()`, `create()`, `update()`, `delete()`, `count()`, `exists()`
- By identifier: `findByNumber()`, `findByContact()`, `findByStatus()`
- Pipeline queries: `findExpiringSoon()`, `findPendingApproval()`, `findNeedingFollowUp()`, `findActive()`
- Sales queries: `findByOutcome()`, `findAssignedTo()`, `findByDateRange()`
- Statistics: `getValueByStatus()`, `getWinRateStats()`

### Skills Documentation ✅ COMPLETED

Skills documentation created/updated across all phases:

| Skill File | Changes | Phase |
|------------|---------|-------|
| `ARCHITECTURE_PATTERNS.md` | Created - OperationContext, Domain Factory pattern, God Models solution | 1, 2 |
| `REPOSITORIES.md` | Created - Repository pattern, domain queries, DB::table() for stats | 3.1 |
| `SERVICE_BINDINGS.md` | Updated - Added Repositories section, Domain Factories section | 2, 3.1 |
| `SKILL.md` | Updated - Added gotchas #6b, #15, #16, #17; updated index | 1, 2, 3.1 |
| `CLAUDE.md` | Updated - Skills table with REPOSITORIES.md, ARCHITECTURE_PATTERNS.md | 3.1 |

**Gotchas Added:**
- #6b: Use `getUserId()` not `auth()->id()` in services
- #15: Decimal cast returns string, not float
- #16: Domain Factory for mutations, Model for reads
- #17: Use `DB::table()` for repository aggregations

### Phase 3.2: QuotationService Migration ✅ COMPLETED

| Task | Status | Date | Notes |
|------|--------|------|-------|
| 3.2.1 Create DocumentBasedQuotationService | ✅ Done | 2026-01-21 | Extends AbstractDocumentService |
| 3.2.2 Add feature flag | ✅ Done | 2026-01-21 | `config('features.services.new_quotation_service')` |
| 3.2.3 Update AppServiceProvider | ✅ Done | 2026-01-21 | Conditional binding based on feature flag |
| 3.2.4 Create comprehensive tests | ✅ Done | 2026-01-21 | 22 tests, all passing |

**Files Created:**
- `app/Services/Sales/DocumentBasedQuotationService.php` - New service extending AbstractDocumentService
- `tests/Feature/Services/Sales/DocumentBasedQuotationServiceTest.php` - 22 tests

**Files Modified:**
- `config/features.php` - Added `services.new_quotation_service` feature flag
- `app/Providers/AppServiceProvider.php` - Conditional binding for QuotationServiceInterface

**Key Patterns Implemented:**
- Extends `AbstractDocumentService` for consistent CRUD
- Uses `QuotationRepositoryInterface` for data access
- Uses `QuotationDomainFactory` for state machine and calculations
- Uses `OperationContext` for user tracking via `getUserId()`
- Feature-flagged for gradual rollout

**Methods Implemented:**
- CRUD: `create()`, `update()` (using parent's protected methods)
- State transitions: `submit()`, `approve()`, `reject()`, `cancel()`
- Quotation-specific: `createFromBom()`, `revise()`, `duplicate()`
- Status management: `markAsSent()`, `markExpired()`
- Statistics: `getStatistics()`

**Test Results:**
- 22 new tests for DocumentBasedQuotationService
- 291 total quotation tests passing (269 legacy + 22 new)
- Both implementations verified working

### Phase 4: Testing Infrastructure ✅ COMPLETED

| Task | Status | Date | Notes |
|------|--------|------|-------|
| 4.1 TestsWithOperationContext trait | ✅ Done | 2026-01-21 | Created in Phase 1 |
| 4.2 InMemoryQuotationRepository | ✅ Done | 2026-01-22 | Full implementation with 30 tests |

**Files Created:**
- `tests/Support/InMemoryQuotationRepository.php` - Full in-memory implementation of QuotationRepositoryInterface (389 lines)
- `tests/Unit/Support/InMemoryQuotationRepositoryTest.php` - 30 tests, 55 assertions, all passing

**Key Features:**
- Auto-increment ID management
- Helper methods: `reset()`, `seed()`, `getCollection()`
- All 15+ domain-specific query methods implemented
- Statistics methods: `getValueByStatus()`, `getWinRateStats()`

**Gotchas Discovered & Documented:**
- `EntityNotFoundException` uses constructor, not static factory (`new EntityNotFoundException('Quotation', $id)`)
- `DateRange::contains()` expects string, not Carbon - must convert with `->format('Y-m-d')`
- `SpecificationInterface` not supported in-memory (throws RuntimeException with helpful message)

**Skills Documentation Updated:**
- `SKILL.md` - Added gotchas #20 (EntityNotFoundException), #21 (DateRange types)
- `REPOSITORIES.md` - Expanded in-memory repository section with full implementation pattern
- `TESTING_PATTERNS.md` - Added "Unit Testing with In-Memory Repositories" section

---

## Executive Summary

This plan addresses three critical architectural issues identified during code review:

1. **God Models** - Models (especially Quotation) contain excessive business logic
2. **Global State Coupling** - `auth()->id()` scattered throughout services kills testability
3. **Inconsistent Service Layer** - Two competing patterns create confusion

## Problem Analysis

### Issue 1: God Models

**Affected Models:**
- `Quotation` (735 lines, 93 fillable, 20+ relationships)
- Likely others: `Invoice`, `PurchaseOrder`, `WorkOrder`

**Symptoms:**
- Business logic in models (`calculateTotals`, `scheduleFollowUp`, `markAsWon`)
- Service locator pattern inside models: `app(QuotationCalculatorInterface::class)`
- Models instantiate domain services: `new OutcomeManager()`, `new FollowUpManager()`
- Difficult to unit test business logic without Eloquent

### Issue 2: Global State via `auth()->id()`

**Locations Found:** 25+ direct calls in services

| File | Occurrences |
|------|-------------|
| `InventoryService.php` | 5 |
| `BillService.php` | 4 |
| `QuotationConversionService.php` | 1 |
| `MrpSuggestionService.php` | 4 |
| `PurchaseOrderService.php` | 1 |
| Others | 10+ |

**Problems:**
- Cannot unit test services without mocking Laravel auth
- Inconsistent: some methods accept `$userId` parameter, others don't
- Silent bugs in CLI/queue context where `auth()->id()` returns null

### Issue 3: Inconsistent Service Layer

**Pattern A - AbstractDocumentService users:**
- `InvoiceService`
- `BillService`
- `DeliveryOrderService`
- `SalesReturnService`
- `PurchaseOrderService`
- `PurchaseReturnService`

**Pattern B - Standalone services:**
- `QuotationService` (does NOT extend AbstractDocumentService)
- `QuotationConversionService` (no interface)
- Various other services

**Problems:**
- Cognitive overhead switching between patterns
- `AbstractDocumentService` has nullable constructor params for "backward compat" - code smell
- Interface contracts incomplete (missing methods)

---

## Implementation Plan

### Phase 1: Quick Wins (Low Risk, High Impact)

**Timeline:** 1-2 days
**Risk Level:** Low
**Dependencies:** None

#### 1.1 Create OperationContext for User Resolution

**Goal:** Centralize user context, enable dependency injection, maintain backward compatibility.

**Files to Create:**

```
app/Support/OperationContext.php
```

**Implementation:**

```php
<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Immutable context object for operations.
 *
 * Replaces scattered auth()->id() calls with explicit, injectable context.
 * Enables proper unit testing without mocking Laravel's auth system.
 */
final class OperationContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $ipAddress = null,
        public readonly ?DateTimeInterface $timestamp = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create context from current authenticated user.
     */
    public static function fromAuth(): self
    {
        return new self(
            userId: auth()->id(),
            ipAddress: request()?->ip(),
            timestamp: now(),
        );
    }

    /**
     * Create context for system operations (jobs, commands).
     */
    public static function system(): self
    {
        return new self(
            userId: null,
            timestamp: now(),
            metadata: ['source' => 'system'],
        );
    }

    /**
     * Create context for specific user (testing, impersonation).
     */
    public static function forUser(int $userId): self
    {
        return new self(
            userId: $userId,
            timestamp: now(),
        );
    }

    /**
     * Create context with additional metadata.
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            userId: $this->userId,
            ipAddress: $this->ipAddress,
            timestamp: $this->timestamp,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
```

**Files to Modify:**

```
app/Services/Base/AbstractApplicationService.php
```

**Changes:**

```php
// Add to AbstractApplicationService

protected ?OperationContext $operationContext = null;

/**
 * Set operation context for this service instance.
 *
 * Returns a clone to maintain immutability.
 */
public function withContext(OperationContext $context): static
{
    $clone = clone $this;
    $clone->operationContext = $context;
    return $clone;
}

/**
 * Get authenticated user ID.
 *
 * Prefers explicit context, falls back to auth() for backward compatibility.
 */
protected function getUserId(): ?int
{
    if ($this->operationContext !== null) {
        return $this->operationContext->userId;
    }

    // Backward compatibility - will be removed in future
    return auth()->id();
}

/**
 * Get operation context, creating from auth if not set.
 */
protected function getContext(): OperationContext
{
    return $this->operationContext ?? OperationContext::fromAuth();
}
```

**Testing:**

```php
// tests/Unit/Support/OperationContextTest.php
test('creates context from auth', function () {
    $this->actingAs(User::factory()->create(['id' => 42]));

    $context = OperationContext::fromAuth();

    expect($context->userId)->toBe(42);
    expect($context->timestamp)->not->toBeNull();
});

test('creates system context with null user', function () {
    $context = OperationContext::system();

    expect($context->userId)->toBeNull();
    expect($context->metadata)->toHaveKey('source', 'system');
});

test('creates context for specific user', function () {
    $context = OperationContext::forUser(123);

    expect($context->userId)->toBe(123);
});
```

**Acceptance Criteria:**
- [x] `OperationContext` class created ✅
- [x] `AbstractApplicationService` updated with `withContext()` method ✅
- [x] Backward compatible (existing code still works) ✅
- [x] Unit tests pass (13 tests) ✅

---

#### 1.2 Complete Interface Contracts

**Goal:** Ensure interfaces match their implementations.

**Files to Modify:**

```
app/Contracts/Sales/QuotationServiceInterface.php
```

**Add Missing Methods:**

```php
/**
 * Cancel a quotation.
 */
public function cancel(Quotation $quotation, ?string $reason = null, ?int $userId = null): Quotation;

/**
 * Mark quotation as sent to customer.
 */
public function markAsSent(Quotation $quotation, ?string $email = null, ?string $via = 'email'): Quotation;
```

**Files to Create:**

```
app/Contracts/Sales/QuotationConversionServiceInterface.php
```

**Implementation:**

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;

interface QuotationConversionServiceInterface
{
    /**
     * Convert an approved quotation to an invoice.
     */
    public function convertToInvoice(Quotation $quotation): Invoice;

    /**
     * Check if a quotation can be converted to invoice.
     */
    public function canConvertToInvoice(Quotation $quotation): bool;

    /**
     * Get conversion status for a quotation.
     *
     * @return array{can_convert: bool, converted: bool, converted_to: array|null, reason: string|null}
     */
    public function getConversionStatus(Quotation $quotation): array;
}
```

**Files to Modify:**

```
app/Services/Sales/QuotationConversionService.php  - Add implements
app/Providers/AppServiceProvider.php               - Add binding
```

**Acceptance Criteria:**
- [x] `QuotationServiceInterface` has all public methods from implementation ✅
- [x] `QuotationConversionServiceInterface` created ✅
- [x] `QuotationConversionService` implements the interface ✅
- [x] Binding added to `AppServiceProvider` ✅

---

### Phase 2: Model Slimming (Medium Risk, High Impact)

**Timeline:** 3-5 days
**Risk Level:** Medium
**Dependencies:** Phase 1 complete

#### 2.1 Extract Business Logic from Quotation Model

**Goal:** Transform Quotation from God Model to lean data container.

**Extraction Priority:**

| Method | Target Location | Priority |
|--------|-----------------|----------|
| `calculateTotals()` | Use existing `QuotationCalculator` | High |
| `scheduleFollowUp()` | Existing `FollowUpManager` | High |
| `recordContact()` | Existing `FollowUpManager` | High |
| `needsFollowUp()` | Existing `FollowUpManager` | High |
| `markAsWon()` | Existing `OutcomeManager` | Medium |
| `markAsLost()` | Existing `OutcomeManager` | Medium |
| `getDefaultTermsConditions()` | `QuotationDefaults` | Low |

**Strategy: Deprecation + Forwarding**

This allows gradual migration without breaking existing code.

**Step 1: Update Model Methods to Forward**

```php
// app/Models/Sales/Quotation.php

/**
 * @deprecated Use QuotationCalculatorInterface directly. Will be removed in v2.0.
 */
public function calculateTotals(?QuotationCalculatorInterface $calculator = null): void
{
    $calculator ??= app(QuotationCalculatorInterface::class);

    $totals = $calculator->calculateForModel($this);

    $this->subtotal = $totals->subtotal;
    $this->discount_amount = $totals->discountAmount;
    $this->tax_amount = $totals->taxAmount;
    $this->total = $totals->totalAmount;
    $this->base_currency_total = $totals->baseCurrencyTotal;
}

/**
 * @deprecated Use FollowUpManager::schedule() directly. Will be removed in v2.0.
 */
public function scheduleFollowUp(int $daysFromNow = 3): void
{
    trigger_error(
        'Quotation::scheduleFollowUp() is deprecated. Use FollowUpManager::schedule() instead.',
        E_USER_DEPRECATED
    );

    app(FollowUpManager::class)->schedule($this, $daysFromNow);
}
```

**Step 2: Update Callers Incrementally**

Search and replace callers one by one:

```php
// Before
$quotation->scheduleFollowUp(5);

// After
app(FollowUpManager::class)->schedule($quotation, 5);

// Or with DI
$this->followUpManager->schedule($quotation, 5);
```

**Step 3: Track Migration Progress**

Create tracking issue or use this checklist:

- [ ] `calculateTotals()` - forwarding added
- [ ] `calculateTotals()` - all callers updated
- [ ] `scheduleFollowUp()` - forwarding added
- [ ] `scheduleFollowUp()` - all callers updated
- [ ] `recordContact()` - forwarding added
- [ ] `recordContact()` - all callers updated
- [ ] `markAsWon()` - forwarding added
- [ ] `markAsWon()` - all callers updated
- [ ] `markAsLost()` - forwarding added
- [ ] `markAsLost()` - all callers updated

**Acceptance Criteria:**
- [ ] All business logic methods deprecated with forwarding
- [ ] No direct business logic execution in model
- [ ] Deprecation warnings logged (dev environment)
- [ ] All tests still pass

---

#### 2.2 Remove Service Locator from Model

**Goal:** Models should not call `app()` to resolve services.

**Current Problem:**

```php
// Inside Quotation model
public function stateMachine(): QuotationStateMachine
{
    return QuotationStateMachine::fromQuotation($this);
}

public function outcomeManager(): OutcomeManager
{
    return new OutcomeManager; // Creates new instance every call!
}
```

**Solution: Factory Methods in Service Layer**

```php
// app/Domain/Sales/Quotations/QuotationDomainFactory.php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Contracts\Events\EventDispatcherInterface;
use App\Models\Sales\Quotation;

class QuotationDomainFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function stateMachine(Quotation $quotation): QuotationStateMachine
    {
        return QuotationStateMachine::fromQuotation($quotation, $this->eventDispatcher);
    }

    public function outcomeManager(): OutcomeManager
    {
        return new OutcomeManager();
    }

    public function followUpManager(): FollowUpManager
    {
        return new FollowUpManager();
    }
}
```

**Register in ServiceProvider:**

```php
$this->app->singleton(QuotationDomainFactory::class);
```

**Update Service Usage:**

```php
// In QuotationService
public function __construct(
    // ... existing deps
    private QuotationDomainFactory $domainFactory,
) {}

public function submit(Quotation $quotation, ?int $userId = null): Quotation
{
    $stateMachine = $this->domainFactory->stateMachine($quotation);

    if (! $stateMachine->canSubmit()) {
        throw new InvalidArgumentException('...');
    }
    // ...
}
```

**Acceptance Criteria:**
- [ ] `QuotationDomainFactory` created
- [ ] Model methods deprecated for domain object access
- [ ] Services use factory instead of model methods
- [ ] No `app()` calls inside model

---

### Phase 3: Service Layer Consistency (Medium Risk)

**Timeline:** 5-7 days
**Risk Level:** Medium
**Dependencies:** Phase 1, Phase 2 recommended

#### 3.1 Create Missing Repository

**Goal:** `QuotationService` should use repository pattern like other services.

**Files to Create:**

```
app/Contracts/Repositories/Sales/QuotationRepositoryInterface.php
app/Infrastructure/Repositories/Sales/EloquentQuotationRepository.php
```

**Interface:**

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
    public function findByNumber(string $number): ?Quotation;

    public function getExpiringSoon(int $days = 7): Collection;

    public function getByContact(int $contactId): Collection;

    public function getPendingApproval(): Collection;

    public function getNeedingFollowUp(): Collection;
}
```

**Implementation:**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\AbstractEloquentRepository;
use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;

/**
 * @extends AbstractEloquentRepository<Quotation>
 */
class EloquentQuotationRepository extends AbstractEloquentRepository implements QuotationRepositoryInterface
{
    protected function getModelClass(): string
    {
        return Quotation::class;
    }

    public function findByNumber(string $number): ?Quotation
    {
        return Quotation::where('quotation_number', $number)->first();
    }

    public function getExpiringSoon(int $days = 7): Collection
    {
        return Quotation::query()
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Submitted, DocumentStatus::Approved])
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>', now())
            ->orderBy('valid_until')
            ->get();
    }

    public function getByContact(int $contactId): Collection
    {
        return Quotation::where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getPendingApproval(): Collection
    {
        return Quotation::where('status', DocumentStatus::Submitted)
            ->orderBy('submitted_at')
            ->get();
    }

    public function getNeedingFollowUp(): Collection
    {
        return Quotation::needsFollowUp()->get();
    }
}
```

**Register:**

```php
// app/Providers/RepositoryServiceProvider.php
public array $bindings = [
    // ... existing
    QuotationRepositoryInterface::class => EloquentQuotationRepository::class,
];
```

**Acceptance Criteria:**
- [ ] Interface created with domain-specific methods
- [ ] Eloquent implementation created
- [ ] Binding registered
- [ ] Tests created for repository

---

#### 3.2 Migrate QuotationService to AbstractDocumentService

**Goal:** Consistent service structure across all document services.

**This is a larger refactor. Consider creating a new service and migrating gradually.**

**Option A: Full Migration (Higher Risk)**

Rewrite `QuotationService` to extend `AbstractDocumentService`.

**Option B: Adapter Pattern (Lower Risk)**

Keep `QuotationService` as-is but wrap with consistent interface.

**Recommended: Option A with Feature Flag**

```php
// config/features.php
return [
    'use_new_quotation_service' => env('FEATURE_NEW_QUOTATION_SERVICE', false),
];

// AppServiceProvider
if (config('features.use_new_quotation_service')) {
    $this->app->bind(QuotationServiceInterface::class, NewQuotationService::class);
} else {
    $this->app->bind(QuotationServiceInterface::class, QuotationService::class);
}
```

**Acceptance Criteria:**
- [ ] New service extends `AbstractDocumentService`
- [ ] Feature flag controls which implementation is used
- [ ] All existing tests pass with both implementations
- [ ] Gradual rollout to production

---

### Phase 4: Testing Infrastructure

**Timeline:** 2-3 days
**Risk Level:** Low
**Dependencies:** Phase 1

#### 4.1 Create Test Helpers

**Files to Create:**

```
tests/Support/TestsWithOperationContext.php
tests/Support/InMemoryQuotationRepository.php
```

**OperationContext Trait:**

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\OperationContext;

trait TestsWithOperationContext
{
    protected function withUserContext(object $service, int $userId): object
    {
        if (method_exists($service, 'withContext')) {
            return $service->withContext(OperationContext::forUser($userId));
        }

        return $service;
    }

    protected function withSystemContext(object $service): object
    {
        if (method_exists($service, 'withContext')) {
            return $service->withContext(OperationContext::system());
        }

        return $service;
    }

    protected function createUserContext(int $userId): OperationContext
    {
        return OperationContext::forUser($userId);
    }
}
```

**In-Memory Repository:**

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;

class InMemoryQuotationRepository implements QuotationRepositoryInterface
{
    private Collection $quotations;
    private int $nextId = 1;

    public function __construct()
    {
        $this->quotations = collect();
    }

    public function find(int $id): ?Quotation
    {
        return $this->quotations->firstWhere('id', $id);
    }

    public function create(array $data): Quotation
    {
        $quotation = new Quotation($data);
        $quotation->id = $this->nextId++;
        $this->quotations->push($quotation);
        return $quotation;
    }

    public function update(object $model, array $data): bool
    {
        $model->fill($data);
        return true;
    }

    public function delete(object $model): bool
    {
        $this->quotations = $this->quotations->reject(fn ($q) => $q->id === $model->id);
        return true;
    }

    public function findByNumber(string $number): ?Quotation
    {
        return $this->quotations->firstWhere('quotation_number', $number);
    }

    // ... implement other methods
}
```

**Acceptance Criteria:**
- [x] Test trait created and working ✅ (tests/Traits/TestsWithOperationContext.php)
- [x] In-memory repository created ✅ (tests/Support/InMemoryQuotationRepository.php)
- [x] Unit tests for in-memory repository ✅ (30 tests, all passing)

---

## Migration Checklist

### Pre-Migration
- [x] Backup database (N/A - no DB changes)
- [x] Create feature branch
- [x] Run full test suite (baseline)
- [ ] Document current behavior with characterization tests

### Phase 1 Execution ✅ COMPLETED
- [x] Create `OperationContext` class ✅
- [x] Update `AbstractApplicationService` ✅
- [x] Run tests ✅
- [x] Complete interface contracts ✅
- [x] Register new bindings ✅
- [x] Run tests (58+ tests passing) ✅
- [x] Create TestsWithOperationContext trait ✅
- [x] Update skills documentation ✅
- [ ] Deploy to staging

### Phase 2 Execution
- [ ] Add deprecation warnings to model methods
- [ ] Create `QuotationDomainFactory`
- [ ] Update services to use factory
- [ ] Run tests
- [ ] Deploy to staging
- [ ] Monitor deprecation logs

### Phase 3 Execution ✅ COMPLETED
- [x] Create `QuotationRepositoryInterface` ✅
- [x] Implement `EloquentQuotationRepository` ✅
- [x] Create new `DocumentBasedQuotationService` (feature flagged) ✅
- [x] Run tests with both implementations ✅ (291 tests passing)
- [ ] Deploy with flag disabled
- [ ] Enable flag for subset of users
- [ ] Monitor and fix issues
- [ ] Enable flag for all users
- [ ] Remove old implementation

### Post-Migration
- [ ] Remove deprecated methods (after grace period)
- [ ] Remove feature flags
- [ ] Update documentation
- [ ] Knowledge sharing session

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking existing functionality | Backward compatible changes, deprecation warnings |
| Test coverage gaps | Write characterization tests before refactoring |
| Production incidents | Feature flags, gradual rollout |
| Developer confusion | Documentation, team walkthrough sessions |
| Timeline overrun | Prioritize phases, deliver incrementally |

---

## Success Metrics

| Metric | Before | Current | Target |
|--------|--------|---------|--------|
| Quotation model LOC | 735 | **650** (-12%) | < 300 |
| Direct `auth()->id()` calls | 25+ | 25+ (infra ready) | 0 (via context) |
| Services with interfaces | ~70% | ~75% (+1) | 100% |
| OperationContext support | 0 | ✅ Ready | All services |
| AbstractDocumentService-based services | 6 | **7** (+1 via feature flag) | All document services |
| QuotationDomainFactory | N/A | ✅ Complete | Done |
| QuotationRepository | N/A | ✅ Complete (23 tests) | Done |
| Business logic in model | Yes | **Reduced** (mutations removed) | Minimal |
| Test helper infrastructure | None | ✅ **Complete** (trait + in-memory repo) | Full suite |
| InMemoryQuotationRepository | N/A | ✅ Complete (30 tests) | Done |
| Unit test coverage (services) | Unknown | Unknown | > 80% |
| Time to write new service test | High | **Low** (helpers ready) | Low |

---

## Notes

- Each phase is independently deployable
- Phases can be parallelized with multiple developers
- Phase 1 should be completed before Phase 2/3 for maximum benefit
- Consider creating tech debt tickets for tracking

---

## Appendix: Files Summary

### Phase 1 - Files Created ✅
```
app/Support/OperationContext.php                              ✅ DONE
app/Contracts/Sales/QuotationConversionServiceInterface.php   ✅ DONE
tests/Unit/Support/OperationContextTest.php                   ✅ DONE
tests/Traits/TestsWithOperationContext.php                    ✅ DONE
.claude/skills/enter365/ARCHITECTURE_PATTERNS.md              ✅ DONE
```

### Phase 1 - Files Modified ✅
```
app/Services/Base/AbstractApplicationService.php              ✅ DONE
app/Contracts/Sales/QuotationServiceInterface.php             ✅ DONE
app/Services/Sales/QuotationConversionService.php             ✅ DONE
app/Providers/AppServiceProvider.php                          ✅ DONE
.claude/skills/enter365/SERVICE_BINDINGS.md                   ✅ DONE
.claude/skills/enter365/TESTING_PATTERNS.md                   ✅ DONE
.claude/skills/enter365/SKILL.md                              ✅ DONE
```

### Phase 2 - Files Created ✅
```
app/Domain/Sales/Quotations/QuotationDomainFactory.php        ✅ DONE
```

### Phase 2 - Files Modified ✅
```
app/Models/Sales/Quotation.php                                ✅ DONE
app/Services/Sales/QuotationService.php                       ✅ DONE
app/Services/Sales/QuotationConversionService.php             ✅ DONE
app/Providers/AppServiceProvider.php                          ✅ DONE
```

### Phase 3.1 - Files Created ✅
```
app/Contracts/Repositories/Sales/QuotationRepositoryInterface.php  ✅ DONE
app/Infrastructure/Repositories/Sales/EloquentQuotationRepository.php  ✅ DONE
tests/Feature/Infrastructure/Repositories/EloquentQuotationRepositoryTest.php  ✅ DONE
.claude/skills/enter365/REPOSITORIES.md                       ✅ DONE
```

### Phase 3.1 - Files Modified ✅
```
app/Providers/RepositoryServiceProvider.php                   ✅ DONE
.claude/skills/enter365/SERVICE_BINDINGS.md                   ✅ DONE
.claude/skills/enter365/SKILL.md                              ✅ DONE
```

### Phase 3.2 - Files Created ✅
```
app/Services/Sales/DocumentBasedQuotationService.php          ✅ DONE
tests/Feature/Services/Sales/DocumentBasedQuotationServiceTest.php  ✅ DONE
```

### Phase 3.2 - Files Modified ✅
```
config/features.php                                           ✅ DONE (added services section)
app/Providers/AppServiceProvider.php                          ✅ DONE (conditional binding)
```

### Phase 4 - Files Created ✅
```
tests/Support/InMemoryQuotationRepository.php                 ✅ DONE
tests/Unit/Support/InMemoryQuotationRepositoryTest.php        ✅ DONE
```

### Phase 4 - Skills Updated ✅
```
.claude/skills/enter365/SKILL.md                              ✅ DONE (gotchas #20, #21)
.claude/skills/enter365/REPOSITORIES.md                       ✅ DONE (in-memory section)
.claude/skills/enter365/TESTING_PATTERNS.md                   ✅ DONE (unit testing section)
```
