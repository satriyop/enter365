# Phase 1: Domain Layer Consolidation

> **Goal**: Complete the domain layer structure across all modules. Currently Sales, Purchasing, and Accounting have mature domain layers. Manufacturing and Inventory need to catch up.

## Current State

| Domain | StateMachine | Events | Value Objects | Handlers | Complete |
|--------|--------------|--------|---------------|----------|----------|
| Sales | Yes | Yes | Partial | Yes | 85% |
| Purchasing | Yes | Yes | Partial | Yes | 85% |
| Accounting | Yes | Yes | Yes | - | 90% |
| Manufacturing | Partial | Partial | No | No | 40% |
| Inventory | No | No | No | No | 20% |
| Projects | Yes | Yes | No | No | 60% |

---

## Deliverables

- [ ] Complete Manufacturing domain layer
- [ ] Create Inventory domain layer
- [ ] Add Value Objects for Money, Quantity, DateRange
- [ ] Standardize all domain events
- [ ] Create approval pipelines where needed

---

## Part 1: Value Objects

Value Objects are immutable objects that describe characteristics. They have no identity and are compared by value.

### 1.1 Money Value Object

```php
<?php
// File: app/Domain/Shared/ValueObjects/Money.php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Money value object.
 *
 * Represents monetary values in minor units (cents/rupiah).
 * All arithmetic returns new instances.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public int $amount,
        public string $currency = 'IDR'
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    /**
     * Create from major units (e.g., 100.50 -> 10050 cents).
     */
    public static function fromMajor(float $amount, string $currency = 'IDR'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    /**
     * Create zero amount.
     */
    public static function zero(string $currency = 'IDR'): self
    {
        return new self(0, $currency);
    }

    /**
     * Add two money values.
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract money value.
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException('Cannot subtract: result would be negative.');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by a factor.
     */
    public function multiply(float $factor): self
    {
        return new self((int) round($this->amount * $factor), $this->currency);
    }

    /**
     * Divide by a factor.
     */
    public function divide(float $divisor): self
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero.');
        }

        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    /**
     * Calculate percentage of this amount.
     */
    public function percentage(float $percent): self
    {
        return $this->multiply($percent / 100);
    }

    /**
     * Check if this equals another money.
     */
    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    /**
     * Check if this is greater than another money.
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /**
     * Check if this is less than another money.
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    /**
     * Check if amount is zero.
     */
    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /**
     * Format for display.
     */
    public function format(): string
    {
        $major = $this->amount / 100;

        return match ($this->currency) {
            'IDR' => 'Rp ' . number_format($major, 0, ',', '.'),
            'USD' => '$' . number_format($major, 2, '.', ','),
            default => $this->currency . ' ' . number_format($major, 2, '.', ','),
        };
    }

    /**
     * Get amount in major units.
     */
    public function toMajor(): float
    {
        return $this->amount / 100;
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
```

### 1.2 Quantity Value Object

```php
<?php
// File: app/Domain/Shared/ValueObjects/Quantity.php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable Quantity value object.
 *
 * Represents quantities with unit of measure.
 */
final readonly class Quantity implements JsonSerializable, Stringable
{
    public function __construct(
        public float $value,
        public string $unit = 'unit'
    ) {
        if ($this->value < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }
    }

    public static function zero(string $unit = 'unit'): self
    {
        return new self(0, $unit);
    }

    public function add(Quantity $other): self
    {
        $this->assertSameUnit($other);

        return new self($this->value + $other->value, $this->unit);
    }

    public function subtract(Quantity $other): self
    {
        $this->assertSameUnit($other);

        if ($other->value > $this->value) {
            throw new InvalidArgumentException('Cannot subtract: result would be negative.');
        }

        return new self($this->value - $other->value, $this->unit);
    }

    public function multiply(float $factor): self
    {
        return new self($this->value * $factor, $this->unit);
    }

    public function equals(Quantity $other): bool
    {
        return abs($this->value - $other->value) < 0.0001 && $this->unit === $other->unit;
    }

    public function isZero(): bool
    {
        return $this->value == 0;
    }

    public function greaterThan(Quantity $other): bool
    {
        $this->assertSameUnit($other);

        return $this->value > $other->value;
    }

    public function lessThan(Quantity $other): bool
    {
        $this->assertSameUnit($other);

        return $this->value < $other->value;
    }

    public function format(): string
    {
        $formatted = number_format($this->value, 2);

        return "{$formatted} {$this->unit}";
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'unit' => $this->unit,
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameUnit(Quantity $other): void
    {
        if ($this->unit !== $other->unit) {
            throw new InvalidArgumentException(
                "Cannot operate on different units: {$this->unit} vs {$other->unit}"
            );
        }
    }
}
```

### 1.3 DateRange Value Object

```php
<?php
// File: app/Domain/Shared/ValueObjects/DateRange.php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable DateRange value object.
 */
final readonly class DateRange implements JsonSerializable
{
    public CarbonImmutable $start;
    public CarbonImmutable $end;

    public function __construct(
        CarbonImmutable|string $start,
        CarbonImmutable|string $end
    ) {
        $this->start = $start instanceof CarbonImmutable ? $start : CarbonImmutable::parse($start);
        $this->end = $end instanceof CarbonImmutable ? $end : CarbonImmutable::parse($end);

        if ($this->end->isBefore($this->start)) {
            throw new InvalidArgumentException('End date cannot be before start date.');
        }
    }

    public static function thisMonth(): self
    {
        return new self(
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth()
        );
    }

    public static function thisYear(): self
    {
        return new self(
            CarbonImmutable::now()->startOfYear(),
            CarbonImmutable::now()->endOfYear()
        );
    }

    public static function lastDays(int $days): self
    {
        return new self(
            CarbonImmutable::now()->subDays($days)->startOfDay(),
            CarbonImmutable::now()->endOfDay()
        );
    }

    public function contains(CarbonImmutable|string $date): bool
    {
        $date = $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);

        return $date->between($this->start, $this->end);
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->start->lte($other->end) && $this->end->gte($other->start);
    }

    public function days(): int
    {
        return $this->start->diffInDays($this->end) + 1;
    }

    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'days' => $this->days(),
        ];
    }
}
```

---

## Part 2: Manufacturing Domain Layer

### 2.1 Manufacturing Directory Structure

```
app/Domain/Manufacturing/
├── WorkOrders/
│   ├── WorkOrderStateMachine.php (exists, needs enhancement)
│   ├── WorkOrderCalculator.php
│   ├── Events/
│   │   ├── WorkOrderStatusChanged.php (exists)
│   │   ├── WorkOrderConfirmed.php (exists)
│   │   ├── WorkOrderStarted.php (exists)
│   │   ├── WorkOrderCompleted.php (exists)
│   │   ├── WorkOrderCancelled.php (exists)
│   │   └── MaterialConsumed.php (new)
│   └── Handlers/
│       ├── WorkOrderCompletionPipeline.php (new)
│       ├── MaterialConsumptionHandler.php (new)
│       ├── InventoryUpdateHandler.php (new)
│       └── CostCalculationHandler.php (new)
├── MaterialRequisitions/
│   ├── MaterialRequisitionStateMachine.php (needs creation)
│   └── Events/ (exists)
├── Boms/
│   ├── BomCalculator.php
│   ├── BomExploder.php (new - explodes multi-level BOMs)
│   └── Events/
│       ├── BomActivated.php
│       └── BomDeactivated.php
└── Subcontractors/
    ├── SubcontractorWorkOrderStateMachine.php
    └── Events/ (exists)
```

### 2.2 Work Order Completion Pipeline

```php
<?php
// File: app/Domain/Manufacturing/WorkOrders/Handlers/WorkOrderCompletionPipeline.php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted;
use App\Models\Manufacturing\WorkOrder;

/**
 * Pipeline that executes handlers when a work order is completed.
 */
class WorkOrderCompletionPipeline
{
    /** @var array<CompletionHandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function addHandler(CompletionHandlerInterface $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    /**
     * Process work order completion through all handlers.
     */
    public function process(WorkOrder $workOrder, ?int $userId = null): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($workOrder, $userId);
        }

        $this->eventDispatcher->dispatch(
            WorkOrderCompleted::fromWorkOrder($workOrder, $userId)
        );
    }
}
```

```php
<?php
// File: app/Domain/Manufacturing/WorkOrders/Handlers/CompletionHandlerInterface.php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Models\Manufacturing\WorkOrder;

interface CompletionHandlerInterface
{
    /**
     * Handle work order completion.
     */
    public function handle(WorkOrder $workOrder, ?int $userId = null): void;
}
```

```php
<?php
// File: app/Domain/Manufacturing/WorkOrders/Handlers/MaterialConsumptionHandler.php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Domain\Manufacturing\WorkOrders\Events\MaterialConsumed;
use App\Contracts\Events\EventDispatcherInterface;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\MaterialConsumption;

class MaterialConsumptionHandler implements CompletionHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        foreach ($workOrder->items as $item) {
            if ($item->product_id && $item->quantity_required > 0) {
                // Record consumption
                $consumption = MaterialConsumption::create([
                    'work_order_id' => $workOrder->id,
                    'work_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity_required,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->estimated_cost,
                    'consumed_at' => now(),
                    'consumed_by' => $userId,
                ]);

                // Deduct from inventory
                $this->inventoryService->deduct(
                    productId: $item->product_id,
                    quantity: $item->quantity_required,
                    warehouseId: $workOrder->warehouse_id,
                    reason: "Work Order {$workOrder->wo_number}",
                    referenceType: 'work_order',
                    referenceId: $workOrder->id
                );

                $this->eventDispatcher->dispatch(
                    new MaterialConsumed(
                        workOrderId: $workOrder->id,
                        productId: $item->product_id,
                        quantity: $item->quantity_required,
                        userId: $userId
                    )
                );
            }
        }
    }
}
```

```php
<?php
// File: app/Domain/Manufacturing/WorkOrders/Handlers/FinishedGoodsHandler.php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Manufacturing\WorkOrder;

/**
 * Adds finished goods to inventory when work order completes.
 */
class FinishedGoodsHandler implements CompletionHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        if (! $workOrder->product_id) {
            return;
        }

        $this->inventoryService->add(
            productId: $workOrder->product_id,
            quantity: $workOrder->quantity_completed,
            warehouseId: $workOrder->warehouse_id,
            reason: "Work Order {$workOrder->wo_number} completed",
            referenceType: 'work_order',
            referenceId: $workOrder->id,
            unitCost: $workOrder->unit_cost_actual
        );
    }
}
```

### 2.3 BOM Exploder

```php
<?php
// File: app/Domain/Manufacturing/Boms/BomExploder.php

declare(strict_types=1);

namespace App\Domain\Manufacturing\Boms;

use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;

/**
 * Explodes multi-level BOMs into flat or hierarchical structure.
 */
class BomExploder
{
    private int $maxDepth = 10;
    private bool $includeInactive = false;

    /**
     * Explode BOM into flat list of materials.
     *
     * @return array<int, array{
     *     product_id: int,
     *     description: string,
     *     quantity: float,
     *     unit: string,
     *     level: int,
     *     path: string
     * }>
     */
    public function explodeFlat(Bom $bom, float $quantity = 1): array
    {
        $result = [];
        $this->explodeRecursive($bom, $quantity, 0, '', $result);

        return $result;
    }

    /**
     * Explode BOM into hierarchical structure.
     *
     * @return array<int, array{
     *     product_id: int,
     *     description: string,
     *     quantity: float,
     *     unit: string,
     *     children: array
     * }>
     */
    public function explodeHierarchical(Bom $bom, float $quantity = 1): array
    {
        return $this->explodeRecursiveHierarchical($bom, $quantity, 0);
    }

    /**
     * Calculate total material requirements.
     *
     * @return array<int, array{
     *     product_id: int,
     *     total_quantity: float,
     *     unit: string
     * }>
     */
    public function calculateRequirements(Bom $bom, float $quantity = 1): array
    {
        $flat = $this->explodeFlat($bom, $quantity);

        $requirements = [];
        foreach ($flat as $item) {
            $productId = $item['product_id'];
            if (! isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'product_id' => $productId,
                    'total_quantity' => 0,
                    'unit' => $item['unit'],
                ];
            }
            $requirements[$productId]['total_quantity'] += $item['quantity'];
        }

        return array_values($requirements);
    }

    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = $depth;
        return $this;
    }

    public function includeInactive(bool $include = true): self
    {
        $this->includeInactive = $include;
        return $this;
    }

    private function explodeRecursive(
        Bom $bom,
        float $multiplier,
        int $level,
        string $path,
        array &$result
    ): void {
        if ($level > $this->maxDepth) {
            return;
        }

        $outputMultiplier = $multiplier / (float) $bom->output_quantity;

        foreach ($bom->items as $item) {
            if ($item->type !== BomItem::TYPE_MATERIAL) {
                continue;
            }

            $effectiveQty = $item->getEffectiveQuantity() * $outputMultiplier;
            $currentPath = $path ? "{$path} > {$item->description}" : $item->description;

            // Check if this product has its own BOM
            $childBom = $this->findChildBom($item->product_id);

            if ($childBom) {
                // Recurse into child BOM
                $this->explodeRecursive($childBom, $effectiveQty, $level + 1, $currentPath, $result);
            } else {
                // Leaf node - add to result
                $result[] = [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $effectiveQty,
                    'unit' => $item->unit,
                    'level' => $level,
                    'path' => $currentPath,
                ];
            }
        }
    }

    private function explodeRecursiveHierarchical(Bom $bom, float $multiplier, int $level): array
    {
        if ($level > $this->maxDepth) {
            return [];
        }

        $result = [];
        $outputMultiplier = $multiplier / (float) $bom->output_quantity;

        foreach ($bom->items as $item) {
            if ($item->type !== BomItem::TYPE_MATERIAL) {
                continue;
            }

            $effectiveQty = $item->getEffectiveQuantity() * $outputMultiplier;

            $entry = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $effectiveQty,
                'unit' => $item->unit,
                'children' => [],
            ];

            $childBom = $this->findChildBom($item->product_id);
            if ($childBom) {
                $entry['children'] = $this->explodeRecursiveHierarchical($childBom, $effectiveQty, $level + 1);
            }

            $result[] = $entry;
        }

        return $result;
    }

    private function findChildBom(?int $productId): ?Bom
    {
        if (! $productId) {
            return null;
        }

        $query = Bom::where('product_id', $productId);

        if (! $this->includeInactive) {
            $query->where('status', DocumentStatus::Active);
        }

        return $query->first();
    }
}
```

---

## Part 3: Inventory Domain Layer

### 3.1 Inventory Directory Structure

```
app/Domain/Inventory/
├── Products/
│   ├── ProductCostCalculator.php
│   └── Events/
│       ├── ProductCreated.php
│       ├── ProductUpdated.php
│       └── StockLevelChanged.php
├── Movements/
│   ├── MovementRecorder.php
│   ├── MovementValidator.php
│   └── Events/
│       ├── InventoryReceived.php
│       ├── InventoryIssued.php
│       ├── InventoryAdjusted.php
│       └── InventoryTransferred.php
├── StockOpname/
│   ├── StockOpnameStateMachine.php
│   ├── StockOpnameCalculator.php
│   └── Events/
│       ├── StockOpnameStatusChanged.php
│       ├── StockOpnameCompleted.php
│       └── VarianceDetected.php
└── Costing/
    ├── CostingStrategy.php (interface)
    ├── Strategies/
    │   ├── FIFOCostingStrategy.php
    │   ├── WeightedAverageCostingStrategy.php
    │   └── StandardCostingStrategy.php
    └── Events/
        └── CostRecalculated.php
```

### 3.2 Stock Opname State Machine

```php
<?php
// File: app/Domain/Inventory/StockOpname/StockOpnameStateMachine.php

declare(strict_types=1);

namespace App\Domain\Inventory\StockOpname;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\Inventory\StockOpname\Events\StockOpnameStatusChanged;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\StockOpname;

class StockOpnameStateMachine extends AbstractStateMachine
{
    private StockOpname $stockOpname;

    public function __construct(StockOpname $stockOpname, ?EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct($stockOpname->status, $eventDispatcher);
        $this->stockOpname = $stockOpname;
    }

    public static function fromStockOpname(
        StockOpname $stockOpname,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($stockOpname, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::InProgress->value => [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Completed->value => [], // Terminal
            DocumentStatus::Cancelled->value => [], // Terminal
        ];
    }

    protected function getContextData(): array
    {
        return [
            'stock_opname_id' => $this->stockOpname->id,
            'opname_number' => $this->stockOpname->opname_number,
            'warehouse_id' => $this->stockOpname->warehouse_id,
            'item_count' => $this->stockOpname->items()->count(),
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->stockOpname->status = $status;
        $this->stockOpname->save();
    }

    protected function getDocumentType(): string
    {
        return 'Stock Opname';
    }

    protected function getDocumentId(): int
    {
        return $this->stockOpname->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return StockOpnameStatusChanged::class;
    }

    // Business rule methods
    public function canStart(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->stockOpname->items()->exists();
    }

    public function canComplete(): bool
    {
        if ($this->currentStatus !== DocumentStatus::InProgress) {
            return false;
        }

        // All items must have counted quantity
        return $this->stockOpname->items()
            ->whereNull('counted_quantity')
            ->doesntExist();
    }

    public function canEdit(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::InProgress,
        ]);
    }

    // Lifecycle hooks
    protected function beforeInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->stockOpname->items()->exists()) {
            throw StateTransitionException::actionNotAvailable(
                'start',
                $from->value,
                'Stock opname tidak memiliki item.'
            );
        }

        // Lock affected products
        $this->stockOpname->items->each(function ($item) {
            $item->product?->update(['is_locked' => true]);
        });
    }

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $uncountedItems = $this->stockOpname->items()
            ->whereNull('counted_quantity')
            ->count();

        if ($uncountedItems > 0) {
            throw StateTransitionException::actionNotAvailable(
                'complete',
                $from->value,
                "Masih ada {$uncountedItems} item yang belum dihitung."
            );
        }
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        // Unlock products
        $this->stockOpname->items->each(function ($item) {
            $item->product?->update(['is_locked' => false]);
        });

        // Calculate and record variances
        $calculator = app(StockOpnameCalculator::class);
        $calculator->calculateVariances($this->stockOpname);
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        // Unlock products
        $this->stockOpname->items->each(function ($item) {
            $item->product?->update(['is_locked' => false]);
        });
    }
}
```

### 3.3 Movement Recorder

```php
<?php
// File: app/Domain/Inventory/Movements/MovementRecorder.php

declare(strict_types=1);

namespace App\Domain\Inventory\Movements;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\ProductStock;

/**
 * Records all inventory movements with proper event dispatching.
 */
class MovementRecorder
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MovementValidator $validator
    ) {}

    /**
     * Record incoming inventory (purchase, production, return).
     */
    public function recordReceipt(
        int $productId,
        int $warehouseId,
        float $quantity,
        int $unitCost,
        string $type,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateReceipt($productId, $warehouseId, $quantity);

        $stock = $this->getOrCreateStock($productId, $warehouseId);
        $previousQty = $stock->quantity;

        // Update stock
        $stock->quantity += $quantity;
        $stock->recalculateAverageCost($quantity, $unitCost);
        $stock->save();

        // Record movement
        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryReceived(
                productId: $productId,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record outgoing inventory (sale, consumption, return to supplier).
     */
    public function recordIssue(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $type,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateIssue($productId, $warehouseId, $quantity);

        $stock = $this->getStock($productId, $warehouseId);
        $previousQty = $stock->quantity;

        // Update stock
        $stock->quantity -= $quantity;
        $stock->save();

        // Record movement
        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'direction' => 'out',
            'quantity' => $quantity,
            'unit_cost' => $stock->average_cost,
            'total_cost' => $quantity * $stock->average_cost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryIssued(
                productId: $productId,
                warehouseId: $warehouseId,
                quantity: $quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record inventory adjustment (positive or negative).
     */
    public function recordAdjustment(
        int $productId,
        int $warehouseId,
        float $adjustmentQuantity,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): InventoryMovement {
        $this->validator->validateAdjustment($productId, $warehouseId, $adjustmentQuantity);

        $stock = $this->getOrCreateStock($productId, $warehouseId);
        $previousQty = $stock->quantity;

        // Update stock
        $stock->quantity += $adjustmentQuantity;
        $stock->save();

        // Record movement
        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => 'adjustment',
            'direction' => $adjustmentQuantity >= 0 ? 'in' : 'out',
            'quantity' => abs($adjustmentQuantity),
            'unit_cost' => $stock->average_cost,
            'total_cost' => abs($adjustmentQuantity) * $stock->average_cost,
            'quantity_before' => $previousQty,
            'quantity_after' => $stock->quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $userId ?? auth()->id(),
        ]);

        $this->eventDispatcher->dispatch(
            new InventoryAdjusted(
                productId: $productId,
                warehouseId: $warehouseId,
                adjustmentQuantity: $adjustmentQuantity,
                previousQuantity: $previousQty,
                newQuantity: $stock->quantity,
                movementId: $movement->id,
                userId: $userId
            )
        );

        return $movement;
    }

    /**
     * Record transfer between warehouses.
     */
    public function recordTransfer(
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        string $reason,
        ?int $userId = null
    ): array {
        $this->validator->validateTransfer($productId, $fromWarehouseId, $toWarehouseId, $quantity);

        // Issue from source
        $outMovement = $this->recordIssue(
            $productId,
            $fromWarehouseId,
            $quantity,
            'transfer_out',
            "Transfer to warehouse {$toWarehouseId}: {$reason}",
            'warehouse',
            $toWarehouseId,
            $userId
        );

        // Receive at destination
        $inMovement = $this->recordReceipt(
            $productId,
            $toWarehouseId,
            $quantity,
            $outMovement->unit_cost,
            'transfer_in',
            "Transfer from warehouse {$fromWarehouseId}: {$reason}",
            'warehouse',
            $fromWarehouseId,
            $userId
        );

        $this->eventDispatcher->dispatch(
            new InventoryTransferred(
                productId: $productId,
                fromWarehouseId: $fromWarehouseId,
                toWarehouseId: $toWarehouseId,
                quantity: $quantity,
                outMovementId: $outMovement->id,
                inMovementId: $inMovement->id,
                userId: $userId
            )
        );

        return ['out' => $outMovement, 'in' => $inMovement];
    }

    private function getStock(int $productId, int $warehouseId): ProductStock
    {
        return ProductStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->firstOrFail();
    }

    private function getOrCreateStock(int $productId, int $warehouseId): ProductStock
    {
        return ProductStock::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0]
        );
    }
}
```

---

## Part 4: Standardize Domain Events

### 4.1 Base Domain Event

```php
<?php
// File: app/Domain/Core/DomainEvent.php

declare(strict_types=1);

namespace App\Domain\Core;

use DateTimeImmutable;

/**
 * Base class for all domain events.
 */
abstract readonly class DomainEvent
{
    public DateTimeImmutable $occurredAt;

    public function __construct(?DateTimeImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    /**
     * Get event name for logging/tracking.
     */
    public function getEventName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return preg_replace('/(?<!^)[A-Z]/', '_$0', $className) ?? $className;
    }

    /**
     * Convert to array for serialization.
     */
    abstract public function toArray(): array;
}
```

### 4.2 Example Refactored Event

```php
<?php
// File: app/Domain/Sales/Invoices/Events/InvoicePaid.php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices\Events;

use App\Domain\Core\DomainEvent;
use App\Models\Sales\Invoice;
use DateTimeImmutable;

final readonly class InvoicePaid extends DomainEvent
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public int $totalAmount,
        public int $paidAmount,
        public int $paymentId,
        public ?int $userId = null,
        ?DateTimeImmutable $occurredAt = null
    ) {
        parent::__construct($occurredAt);
    }

    public static function fromInvoice(Invoice $invoice, int $paymentId, ?int $userId = null): self
    {
        return new self(
            invoiceId: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            totalAmount: $invoice->total_amount,
            paidAmount: $invoice->paid_amount,
            paymentId: $paymentId,
            userId: $userId
        );
    }

    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'total_amount' => $this->totalAmount,
            'paid_amount' => $this->paidAmount,
            'payment_id' => $this->paymentId,
            'user_id' => $this->userId,
            'occurred_at' => $this->occurredAt->format('c'),
        ];
    }
}
```

---

## Verification Checklist

After completing this phase, verify:

- [ ] Value Objects are immutable and properly tested
- [ ] Manufacturing domain has complete state machines
- [ ] Inventory domain has MovementRecorder with events
- [ ] StockOpname has state machine
- [ ] All domain events extend DomainEvent base class
- [ ] Approval pipelines are registered in container
- [ ] All existing tests still pass
- [ ] New domain tests are written

---

## Tests to Add

```php
<?php
// File: tests/Unit/Domain/Shared/ValueObjects/MoneyTest.php

use App\Domain\Shared\ValueObjects\Money;

describe('Money Value Object', function () {

    it('creates money from minor units', function () {
        $money = new Money(10000, 'IDR');

        expect($money->amount)->toBe(10000);
        expect($money->currency)->toBe('IDR');
    });

    it('creates money from major units', function () {
        $money = Money::fromMajor(100.50, 'USD');

        expect($money->amount)->toBe(10050);
    });

    it('adds two money values', function () {
        $a = new Money(1000, 'IDR');
        $b = new Money(500, 'IDR');

        $result = $a->add($b);

        expect($result->amount)->toBe(1500);
    });

    it('throws on different currencies', function () {
        $idr = new Money(1000, 'IDR');
        $usd = new Money(100, 'USD');

        expect(fn () => $idr->add($usd))->toThrow(InvalidArgumentException::class);
    });

    it('formats IDR correctly', function () {
        $money = new Money(1000000, 'IDR');

        expect($money->format())->toBe('Rp 10.000');
    });
});
```

---

## Next Phase

Once Phase 1 is complete and verified, proceed to [Phase 2: Repository Pattern](./03-phase-2-repository-pattern.md).
