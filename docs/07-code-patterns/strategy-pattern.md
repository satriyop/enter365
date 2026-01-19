---
pattern: strategy
title: "Strategy Pattern"
location: app/Contracts/*/Strategies/, app/Services/*/Strategies/
tags: [ddd, design-patterns, accounting]
updated: 2026-01-19
---

# Strategy Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Business logic has multiple valid implementations
- Behavior should be swappable at runtime
- Configuration determines which algorithm to use
- Testing requires isolated algorithm verification

**Key locations:**
- Interfaces: `/app/Contracts/Accounting/Strategies/`
- Implementations: `/app/Services/Accounting/Strategies/`

---

## Existing Strategies

| Strategy | Purpose | Implementations |
|----------|---------|-----------------|
| `COGSRecognitionStrategy` | When to recognize Cost of Goods Sold | OnInvoice, OnDelivery, Manual |
| `ClosingStrategy` | Period closing method | Standard, FastClose |
| `InventoryAccountingStrategy` | Inventory valuation method | FIFO, Average, Standard |
| `ManufacturingCostStrategy` | Production costing method | Standard, Actual |
| `ReturnAccountingStrategy` | Return credit handling | Credit, Refund |

---

## Pattern Structure

### 1. Strategy Interface

```php
<?php
// File: app/Contracts/Accounting/Strategies/COGSRecognitionStrategy.php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\JournalEntry;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

/**
 * Strategy for Cost of Goods Sold (COGS) recognition.
 *
 * Implementations determine when COGS journal entries are created:
 * - On invoice post (matches revenue recognition)
 * - On delivery (when goods leave warehouse)
 * - Manual (no automatic COGS)
 */
interface COGSRecognitionStrategy
{
    /**
     * Handle COGS when invoice is posted.
     *
     * OnInvoice: Dr COGS, Cr Inventory (for each inventoried item)
     * OnDelivery: No action
     * Manual: No action
     */
    public function onInvoicePost(Invoice $invoice): ?JournalEntry;

    /**
     * Handle COGS when goods are shipped/delivered.
     *
     * OnInvoice: No action
     * OnDelivery: Dr COGS, Cr Inventory (for each inventoried item)
     * Manual: No action
     */
    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry;

    /**
     * Calculate COGS amount for an invoice.
     *
     * Uses average cost from inventory movements.
     */
    public function calculateCOGS(Invoice $invoice): int;

    /**
     * Get the strategy identifier.
     */
    public function getIdentifier(): string;
}
```

### 2. Strategy Implementations

```php
<?php
// File: app/Services/Accounting/Strategies/OnInvoiceCOGSStrategy.php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Services\Domains\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

class OnInvoiceCOGSStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        $cogsAmount = $this->calculateCOGS($invoice);

        if ($cogsAmount <= 0) {
            return null;
        }

        return $this->journalService->create([
            'date' => $invoice->invoice_date,
            'description' => "COGS for Invoice {$invoice->invoice_number}",
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'lines' => [
                ['account_code' => config('accounting.accounts.cogs'), 'debit' => $cogsAmount],
                ['account_code' => config('accounting.accounts.inventory'), 'credit' => $cogsAmount],
            ],
        ]);
    }

    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        // On-Invoice strategy: Do nothing on delivery
        return null;
    }

    public function calculateCOGS(Invoice $invoice): int
    {
        return $invoice->items
            ->filter(fn ($item) => $item->product?->is_inventoried)
            ->sum(fn ($item) => $item->quantity * ($item->product->average_cost ?? 0));
    }

    public function getIdentifier(): string
    {
        return 'on_invoice';
    }
}
```

```php
<?php
// File: app/Services/Accounting/Strategies/OnDeliveryCOGSStrategy.php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Services\Domains\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;

class OnDeliveryCOGSStrategy implements COGSRecognitionStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onInvoicePost(Invoice $invoice): ?JournalEntry
    {
        // On-Delivery strategy: Do nothing on invoice post
        return null;
    }

    public function onDeliveryShip(DeliveryOrder $deliveryOrder): ?JournalEntry
    {
        $cogsAmount = $this->calculateCOGSFromDelivery($deliveryOrder);

        if ($cogsAmount <= 0) {
            return null;
        }

        return $this->journalService->create([
            'date' => $deliveryOrder->delivery_date,
            'description' => "COGS for DO {$deliveryOrder->do_number}",
            'source_type' => DeliveryOrder::class,
            'source_id' => $deliveryOrder->id,
            'lines' => [
                ['account_code' => config('accounting.accounts.cogs'), 'debit' => $cogsAmount],
                ['account_code' => config('accounting.accounts.inventory'), 'credit' => $cogsAmount],
            ],
        ]);
    }

    public function calculateCOGS(Invoice $invoice): int
    {
        // For on-delivery, calculate from delivery orders
        return $invoice->deliveryOrders
            ->sum(fn ($do) => $this->calculateCOGSFromDelivery($do));
    }

    private function calculateCOGSFromDelivery(DeliveryOrder $deliveryOrder): int
    {
        return $deliveryOrder->items
            ->filter(fn ($item) => $item->product?->is_inventoried)
            ->sum(fn ($item) => $item->quantity_delivered * ($item->product->average_cost ?? 0));
    }

    public function getIdentifier(): string
    {
        return 'on_delivery';
    }
}
```

### 3. Strategy Factory

```php
<?php
// File: app/Services/Accounting/Strategies/COGSStrategyFactory.php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use InvalidArgumentException;

class COGSStrategyFactory
{
    public const ON_INVOICE = 'on_invoice';
    public const ON_DELIVERY = 'on_delivery';
    public const MANUAL = 'manual';

    public function make(?string $type = null): COGSRecognitionStrategy
    {
        $type = $type ?? config('accounting.cogs_recognition', self::ON_INVOICE);

        return match ($type) {
            self::ON_INVOICE => app(OnInvoiceCOGSStrategy::class),
            self::ON_DELIVERY => app(OnDeliveryCOGSStrategy::class),
            self::MANUAL => app(ManualCOGSStrategy::class),
            default => throw new InvalidArgumentException("Unknown COGS strategy: {$type}"),
        };
    }

    public function fromConfig(): COGSRecognitionStrategy
    {
        return $this->make(config('accounting.cogs_recognition'));
    }
}
```

### 4. Service Provider Binding

```php
<?php
// File: app/Providers/AppServiceProvider.php

public function register(): void
{
    // Bind strategy interface to factory-resolved implementation
    $this->app->bind(COGSRecognitionStrategy::class, function () {
        return app(COGSStrategyFactory::class)->fromConfig();
    });
}
```

### 5. Usage in Service

```php
<?php
// File: app/Services/Sales/InvoiceService.php

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private COGSRecognitionStrategy $cogsStrategy,
        private JournalServiceInterface $journalService
    ) {}

    public function post(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            // Create revenue journal entry
            $this->journalService->createFromInvoice($invoice);

            // Delegate COGS handling to strategy
            $this->cogsStrategy->onInvoicePost($invoice);

            // Update status
            $stateMachine = InvoiceStateMachine::fromInvoice($invoice);
            $stateMachine->transitionTo(DocumentStatus::Sent);

            return $invoice->fresh();
        });
    }
}
```

---

## Configuration-Driven Strategies

```php
// File: config/accounting.php

return [
    'cogs_recognition' => env('ACCOUNTING_COGS_RECOGNITION', 'on_invoice'),
    // Options: 'on_invoice', 'on_delivery', 'manual'

    'inventory_costing' => env('ACCOUNTING_INVENTORY_COSTING', 'average'),
    // Options: 'fifo', 'average', 'standard'

    'closing_method' => env('ACCOUNTING_CLOSING_METHOD', 'standard'),
    // Options: 'standard', 'fast_close'
];
```

---

## Creating a New Strategy

### Step 1: Define Interface

```php
<?php
// File: app/Contracts/{Domain}/Strategies/{Name}Strategy.php

declare(strict_types=1);

namespace App\Contracts\{Domain}\Strategies;

interface {Name}Strategy
{
    /**
     * Main operation description.
     */
    public function execute(/* params */): mixed;

    /**
     * Strategy identifier for config/factory.
     */
    public function getIdentifier(): string;
}
```

### Step 2: Create Implementations

```php
<?php
// File: app/Services/{Domain}/Strategies/{Variant}{Name}Strategy.php

declare(strict_types=1);

namespace App\Services\{Domain}\Strategies;

use App\Contracts\{Domain}\Strategies\{Name}Strategy;

class {Variant}{Name}Strategy implements {Name}Strategy
{
    public function execute(/* params */): mixed
    {
        // Implementation
    }

    public function getIdentifier(): string
    {
        return '{variant}';
    }
}
```

### Step 3: Create Factory

```php
<?php
// File: app/Services/{Domain}/Strategies/{Name}StrategyFactory.php

declare(strict_types=1);

namespace App\Services\{Domain}\Strategies;

use App\Contracts\{Domain}\Strategies\{Name}Strategy;

class {Name}StrategyFactory
{
    public const VARIANT_A = 'variant_a';
    public const VARIANT_B = 'variant_b';

    public function make(?string $type = null): {Name}Strategy
    {
        $type = $type ?? config('{domain}.{name}_strategy', self::VARIANT_A);

        return match ($type) {
            self::VARIANT_A => app(VariantA{Name}Strategy::class),
            self::VARIANT_B => app(VariantB{Name}Strategy::class),
            default => throw new InvalidArgumentException("Unknown strategy: {$type}"),
        };
    }
}
```

### Step 4: Register in Service Provider

```php
// File: app/Providers/AppServiceProvider.php

$this->app->bind({Name}Strategy::class, function () {
    return app({Name}StrategyFactory::class)->make();
});
```

---

## Testing Strategies

```php
<?php

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Services\Accounting\Strategies\OnInvoiceCOGSStrategy;
use App\Services\Accounting\Strategies\OnDeliveryCOGSStrategy;
use App\Models\Sales\Invoice;
use App\Models\Accounting\JournalEntry;

describe('COGSRecognitionStrategy', function () {

    describe('OnInvoiceCOGSStrategy', function () {

        it('creates journal entry on invoice post', function () {
            $invoice = Invoice::factory()
                ->has(InvoiceItem::factory()->inventoried())
                ->create();

            $strategy = app(OnInvoiceCOGSStrategy::class);
            $journal = $strategy->onInvoicePost($invoice);

            expect($journal)->toBeInstanceOf(JournalEntry::class);
            expect($journal->lines)->toHaveCount(2);
        });

        it('does nothing on delivery', function () {
            $deliveryOrder = DeliveryOrder::factory()->create();

            $strategy = app(OnInvoiceCOGSStrategy::class);
            $result = $strategy->onDeliveryShip($deliveryOrder);

            expect($result)->toBeNull();
        });
    });

    describe('OnDeliveryCOGSStrategy', function () {

        it('does nothing on invoice post', function () {
            $invoice = Invoice::factory()->create();

            $strategy = app(OnDeliveryCOGSStrategy::class);
            $result = $strategy->onInvoicePost($invoice);

            expect($result)->toBeNull();
        });

        it('creates journal entry on delivery', function () {
            $deliveryOrder = DeliveryOrder::factory()
                ->has(DeliveryOrderItem::factory()->inventoried())
                ->create();

            $strategy = app(OnDeliveryCOGSStrategy::class);
            $journal = $strategy->onDeliveryShip($deliveryOrder);

            expect($journal)->toBeInstanceOf(JournalEntry::class);
        });
    });
});

describe('Strategy selection via config', function () {

    it('resolves on_invoice strategy from config', function () {
        config(['accounting.cogs_recognition' => 'on_invoice']);

        $strategy = app(COGSRecognitionStrategy::class);

        expect($strategy)->toBeInstanceOf(OnInvoiceCOGSStrategy::class);
    });

    it('resolves on_delivery strategy from config', function () {
        config(['accounting.cogs_recognition' => 'on_delivery']);

        $strategy = app(COGSRecognitionStrategy::class);

        expect($strategy)->toBeInstanceOf(OnDeliveryCOGSStrategy::class);
    });
});
```

---

## Related Documents

- [Domain Layer Architecture](../01-architecture/domain-layer.md)
- [Service Pattern](./service-pattern.md)
- [ADR-0010: Configuration-Driven Rules](../08-adr/0010-configuration-driven-rules.md)
