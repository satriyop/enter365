# Scaffold Service Skill

Generate a domain service with interface following enter365 architecture patterns.

## Trigger

Use when user says:
- `/scaffold-service`
- "create a new service for X"
- "scaffold service for Y domain"

## Required Information

Prompt the user for:
1. **Domain** - Which domain folder? (Sales, Purchasing, Manufacturing, Inventory, Projects, Solar, Accounting)
2. **Model name** - The primary model this service handles (e.g., Invoice, WorkOrder, Bom)
3. **Actions needed** - CRUD only, or workflow actions? (approve, submit, complete, etc.)

## Files to Generate

```
app/
├── Contracts/Services/Domains/
│   └── {Model}ServiceInterface.php
├── Services/{Domain}/
│   └── {Model}Service.php
tests/
└── Unit/Services/{Model}ServiceTest.php
```

Plus: Add binding to `app/Providers/AppServiceProvider.php`

---

## Template: Interface

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\{Domain}\{Model};

/**
 * Interface for {Model} service operations.
 */
interface {Model}ServiceInterface
{
    /**
     * Create a new {model}.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): {Model};

    /**
     * Update a {model}.
     *
     * @param array<string, mixed> $data
     */
    public function update({Model} ${model}, array $data): {Model};

    /**
     * Delete a {model}.
     */
    public function delete({Model} ${model}): bool;

    // Add workflow methods based on user requirements:
    // public function approve({Model} ${model}, ?int $userId = null): {Model};
    // public function submit({Model} ${model}, ?int $userId = null): {Model};
    // public function complete({Model} ${model}, ?int $userId = null): {Model};
    // public function cancel({Model} ${model}, ?string $reason = null, ?int $userId = null): {Model};
}
```

---

## Template: Service Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services\{Domain};

use App\Contracts\Services\Domains\{Model}ServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Exceptions\Domain\ValidationException;
use App\Models\{Domain}\{Model};
use Illuminate\Support\Facades\DB;

class {Model}Service implements {Model}ServiceInterface
{
    // Inject other service interfaces as needed
    // public function __construct(
    //     private InventoryServiceInterface $inventoryService
    // ) {}

    /**
     * Create a new {model}.
     */
    public function create(array $data): {Model}
    {
        return DB::transaction(function () use ($data) {
            // Generate document number if applicable
            // $data['{model}_number'] = $this->generateNumber();

            $data['status'] = DocumentStatus::Draft;
            $data['created_by'] = auth()->id();

            ${model} = {Model}::create($data);

            // Handle related items if applicable
            // if (isset($data['items'])) {
            //     $this->syncItems(${model}, $data['items']);
            // }

            return ${model}->fresh();
        });
    }

    /**
     * Update a {model}.
     */
    public function update({Model} ${model}, array $data): {Model}
    {
        $this->ensureEditable(${model});

        return DB::transaction(function () use (${model}, $data) {
            ${model}->update($data);

            // Handle related items if applicable
            // if (isset($data['items'])) {
            //     $this->syncItems(${model}, $data['items']);
            // }

            return ${model}->fresh();
        });
    }

    /**
     * Delete a {model}.
     */
    public function delete({Model} ${model}): bool
    {
        $this->ensureEditable(${model});

        return (bool) ${model}->delete();
    }

    // ─────────────────────────────────────────────────────────────
    // Workflow Methods (uncomment/modify as needed)
    // ─────────────────────────────────────────────────────────────

    // public function submit({Model} ${model}, ?int $userId = null): {Model}
    // {
    //     if (${model}->status !== DocumentStatus::Draft) {
    //         throw new StateTransitionException(
    //             entity: '{Model}',
    //             currentState: ${model}->status->value,
    //             targetState: 'submitted',
    //             message: 'Only draft {models} can be submitted.'
    //         );
    //     }
    //
    //     ${model}->update([
    //         'status' => DocumentStatus::Submitted,
    //         'submitted_at' => now(),
    //         'submitted_by' => $userId ?? auth()->id(),
    //     ]);
    //
    //     return ${model}->fresh();
    // }

    // public function approve({Model} ${model}, ?int $userId = null): {Model}
    // {
    //     if (${model}->status !== DocumentStatus::Submitted) {
    //         throw new StateTransitionException(
    //             entity: '{Model}',
    //             currentState: ${model}->status->value,
    //             targetState: 'approved',
    //             message: 'Only submitted {models} can be approved.'
    //         );
    //     }
    //
    //     ${model}->update([
    //         'status' => DocumentStatus::Approved,
    //         'approved_at' => now(),
    //         'approved_by' => $userId ?? auth()->id(),
    //     ]);
    //
    //     return ${model}->fresh();
    // }

    // ─────────────────────────────────────────────────────────────
    // Private Methods
    // ─────────────────────────────────────────────────────────────

    private function ensureEditable({Model} ${model}): void
    {
        if (${model}->status !== DocumentStatus::Draft) {
            throw new StateTransitionException(
                entity: '{Model}',
                currentState: ${model}->status->value,
                targetState: 'edited',
                message: 'Only draft {models} can be edited.'
            );
        }
    }

    // private function syncItems({Model} ${model}, array $items): void
    // {
    //     ${model}->items()->delete();
    //
    //     foreach ($items as $index => $item) {
    //         ${model}->items()->create([
    //             'product_id' => $item['product_id'],
    //             'quantity' => $item['quantity'],
    //             'unit_price' => $item['unit_price'],
    //             'sort_order' => $index + 1,
    //         ]);
    //     }
    // }

    // private function generateNumber(): string
    // {
    //     $prefix = '{MODEL}';
    //     $year = now()->format('Y');
    //     $month = now()->format('m');
    //     $sequence = {Model}::whereYear('created_at', $year)
    //         ->whereMonth('created_at', $month)
    //         ->count() + 1;
    //
    //     return sprintf('%s/%s%s/%04d', $prefix, $year, $month, $sequence);
    // }
}
```

---

## Template: Unit Test

```php
<?php

declare(strict_types=1);

use App\Contracts\Services\Domains\{Model}ServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\{Domain}\{Model};

beforeEach(function () {
    $this->service = app({Model}ServiceInterface::class);
});

describe('{Model}Service', function () {
    describe('create', function () {
        it('creates a {model} with draft status', function () {
            // Arrange
            $data = [
                // Add required fields based on model
            ];

            // Act
            ${model} = $this->service->create($data);

            // Assert
            expect(${model})->toBeInstanceOf({Model}::class);
            expect(${model}->status)->toBe(DocumentStatus::Draft);
        });
    });

    describe('update', function () {
        it('updates a draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Draft]);

            $updated = $this->service->update(${model}, [
                // Add update fields
            ]);

            expect($updated->fresh())->not->toBeNull();
        });

        it('throws exception when updating non-draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Approved]);

            expect(fn() => $this->service->update(${model}, []))
                ->toThrow(StateTransitionException::class);
        });
    });

    describe('delete', function () {
        it('deletes a draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Draft]);

            $result = $this->service->delete(${model});

            expect($result)->toBeTrue();
            expect(${model}->fresh())->toBeNull();
        });

        it('throws exception when deleting non-draft {model}', function () {
            ${model} = {Model}::factory()->create(['status' => DocumentStatus::Approved]);

            expect(fn() => $this->service->delete(${model}))
                ->toThrow(StateTransitionException::class);
        });
    });

    // Add workflow tests as needed:
    // describe('submit', function () { ... });
    // describe('approve', function () { ... });
});
```

---

## AppServiceProvider Binding

Add to `app/Providers/AppServiceProvider.php` in the `register()` method:

```php
// {Domain} Domain
$this->app->bind(
    \App\Contracts\Services\Domains\{Model}ServiceInterface::class,
    \App\Services\{Domain}\{Model}Service::class
);
```

Group with other bindings from the same domain.

---

## Execution Checklist

When scaffolding a new service:

1. [ ] Gather requirements (domain, model, workflow actions)
2. [ ] Check if model exists in `app/Models/{Domain}/`
3. [ ] Check if factory exists in `database/factories/{Domain}/`
4. [ ] Create interface at `app/Contracts/Services/Domains/{Model}ServiceInterface.php`
5. [ ] Create service at `app/Services/{Domain}/{Model}Service.php`
6. [ ] Add binding to `AppServiceProvider.php`
7. [ ] Create test at `tests/Unit/Services/{Model}ServiceTest.php`
8. [ ] Run `php artisan test --filter={Model}Service` to verify
9. [ ] Run `vendor/bin/pint` to format

---

## Domain Reference

| Domain | Models | Services Location |
|--------|--------|-------------------|
| Sales | Invoice, Quotation, DeliveryOrder, DownPayment, SalesReturn | `app/Services/Sales/` |
| Purchasing | Bill, PurchaseOrder, GoodsReceiptNote, PurchaseReturn | `app/Services/Purchasing/` |
| Manufacturing | Bom, BomTemplate, BomVariantGroup, WorkOrder, MaterialRequisition | `app/Services/Manufacturing/` |
| Inventory | Product, ProductStock, StockOpname, InventoryMovement | `app/Services/Inventory/` |
| Projects | Project, ProjectCost, ProjectRevenue | `app/Services/Projects/` |
| Solar | SolarProposal, IndonesiaSolarData, PlnTariff | `app/Services/Solar/` |
| Accounting | Journal, Account, FiscalPeriod, BankTransaction | `app/Services/Accounting/` |

---

## Example Usage

**User:** `/scaffold-service`

**Claude:** I'll create a new service. Please provide:
1. Which domain? (Sales, Purchasing, Manufacturing, Inventory, Projects, Solar, Accounting)
2. Model name? (e.g., Invoice, WorkOrder)
3. What workflow actions? (CRUD only, or also: submit, approve, complete, cancel, etc.)

**User:** Manufacturing, SubcontractorWorkOrder, CRUD + assign, start, complete, cancel

**Claude:** Creating SubcontractorWorkOrderService with:
- Interface with CRUD + assign/start/complete/cancel methods
- Service implementation with state transitions
- Unit tests
- AppServiceProvider binding
