# Phase 5: Strategy Pattern Expansion

> **Goal**: Extend the strategy pattern to more areas for flexibility and configurability.

## Current State

Already implemented strategies:
- COGS Recognition (COGSOnInvoice, COGSOnDelivery, Manual)
- Inventory Accounting (Perpetual, Periodic, Hybrid)
- Year-End Closing (Direct, IncomeSummary)
- Return Accounting (FullJournal, InventoryOnly)
- Manufacturing Cost (WIP, JobCosting, ProjectBased)

---

## Deliverables

- [ ] Document number generation strategies
- [ ] Tax calculation strategies
- [ ] Pricing strategies
- [ ] Approval workflow strategies
- [ ] Notification strategies

---

## Part 1: Document Number Generation Strategies

### 1.1 Strategy Interface

```php
<?php
// File: app/Contracts/Shared/NumberGenerationStrategy.php

declare(strict_types=1);

namespace App\Contracts\Shared;

interface NumberGenerationStrategy
{
    /**
     * Generate document number.
     *
     * @param array<string, mixed> $context Context data (document type, date, etc.)
     */
    public function generate(array $context = []): string;

    /**
     * Get strategy name for configuration.
     */
    public function getName(): string;
}
```

### 1.2 Sequential Strategy

```php
<?php
// File: app/Domain/Shared/NumberGeneration/SequentialNumberStrategy.php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use Illuminate\Support\Facades\DB;

/**
 * Sequential number generation: PREFIX-YYYYMM-0001
 */
class SequentialNumberStrategy implements NumberGenerationStrategy
{
    public function generate(array $context = []): string
    {
        $prefix = $context['prefix'] ?? 'DOC';
        $table = $context['table'];
        $column = $context['column'];
        $dateFormat = $context['date_format'] ?? 'Ym';
        $padLength = $context['pad_length'] ?? 4;

        $datePart = now()->format($dateFormat);
        $fullPrefix = "{$prefix}-{$datePart}-";

        $lastNumber = DB::table($table)
            ->where($column, 'like', "{$fullPrefix}%")
            ->max($column);

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -$padLength);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $fullPrefix . str_pad((string) $nextSequence, $padLength, '0', STR_PAD_LEFT);
    }

    public function getName(): string
    {
        return 'sequential';
    }
}
```

### 1.3 Project-Based Strategy

```php
<?php
// File: app/Domain/Shared/NumberGeneration/ProjectBasedNumberStrategy.php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use App\Models\Projects\Project;
use Illuminate\Support\Facades\DB;

/**
 * Project-based numbering: PRJ-001/INV/001
 */
class ProjectBasedNumberStrategy implements NumberGenerationStrategy
{
    public function generate(array $context = []): string
    {
        $prefix = $context['prefix'] ?? 'DOC';
        $table = $context['table'];
        $column = $context['column'];
        $projectId = $context['project_id'] ?? null;

        if (! $projectId) {
            // Fall back to sequential if no project
            return (new SequentialNumberStrategy())->generate($context);
        }

        $project = Project::find($projectId);
        $projectCode = $project?->project_code ?? 'UNK';

        $pattern = "{$projectCode}/{$prefix}/%";

        $lastNumber = DB::table($table)
            ->where($column, 'like', $pattern)
            ->max($column);

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, strrpos($lastNumber, '/') + 1);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return "{$projectCode}/{$prefix}/" . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    public function getName(): string
    {
        return 'project_based';
    }
}
```

### 1.4 Number Generation Manager

```php
<?php
// File: app/Services/Shared/NumberGenerationManager.php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\NumberGenerationStrategy;
use InvalidArgumentException;

class NumberGenerationManager
{
    /** @var array<string, NumberGenerationStrategy> */
    private array $strategies = [];

    private string $defaultStrategy = 'sequential';

    public function register(NumberGenerationStrategy $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;
        return $this;
    }

    public function setDefault(string $strategyName): self
    {
        $this->defaultStrategy = $strategyName;
        return $this;
    }

    public function generate(string $documentType, array $context = []): string
    {
        $strategyName = config("documents.{$documentType}.numbering", $this->defaultStrategy);

        if (! isset($this->strategies[$strategyName])) {
            throw new InvalidArgumentException("Unknown numbering strategy: {$strategyName}");
        }

        return $this->strategies[$strategyName]->generate($context);
    }

    public function getStrategy(string $name): NumberGenerationStrategy
    {
        if (! isset($this->strategies[$name])) {
            throw new InvalidArgumentException("Unknown strategy: {$name}");
        }

        return $this->strategies[$name];
    }
}
```

---

## Part 2: Tax Calculation Strategies

### 2.1 Strategy Interface

```php
<?php
// File: app/Contracts/Accounting/TaxCalculationStrategy.php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Domain\Shared\ValueObjects\Money;

interface TaxCalculationStrategy
{
    /**
     * Calculate tax on amount.
     */
    public function calculate(Money $amount, float $rate): Money;

    /**
     * Check if prices are tax inclusive.
     */
    public function isTaxInclusive(): bool;

    /**
     * Get strategy name.
     */
    public function getName(): string;
}
```

### 2.2 Tax Exclusive Strategy (Indonesian Standard)

```php
<?php
// File: app/Domain/Accounting/Tax/TaxExclusiveStrategy.php

declare(strict_types=1);

namespace App\Domain\Accounting\Tax;

use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Tax exclusive: Tax is added on top of the price.
 * Price = 100, Tax = 11, Total = 111
 */
class TaxExclusiveStrategy implements TaxCalculationStrategy
{
    public function calculate(Money $amount, float $rate): Money
    {
        return $amount->percentage($rate);
    }

    public function isTaxInclusive(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return 'exclusive';
    }
}
```

### 2.3 Tax Inclusive Strategy

```php
<?php
// File: app/Domain/Accounting/Tax/TaxInclusiveStrategy.php

declare(strict_types=1);

namespace App\Domain\Accounting\Tax;

use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Tax inclusive: Price already includes tax.
 * Total = 111, Tax = 11, Price = 100
 */
class TaxInclusiveStrategy implements TaxCalculationStrategy
{
    public function calculate(Money $totalAmount, float $rate): Money
    {
        // Tax = Total - (Total / (1 + rate))
        $baseAmount = $totalAmount->divide(1 + ($rate / 100));
        return $totalAmount->subtract($baseAmount);
    }

    public function isTaxInclusive(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'inclusive';
    }
}
```

---

## Part 3: Pricing Strategies

### 3.1 Strategy Interface

```php
<?php
// File: app/Contracts/Sales/PricingStrategy.php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

interface PricingStrategy
{
    /**
     * Get price for product and customer.
     */
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money;

    /**
     * Get strategy name.
     */
    public function getName(): string;
}
```

### 3.2 Standard Pricing

```php
<?php
// File: app/Domain/Sales/Pricing/StandardPricingStrategy.php

declare(strict_types=1);

namespace App\Domain\Sales\Pricing;

use App\Contracts\Sales\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Standard pricing: Use product's selling price.
 */
class StandardPricingStrategy implements PricingStrategy
{
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money
    {
        return new Money($product->selling_price, $product->currency ?? 'IDR');
    }

    public function getName(): string
    {
        return 'standard';
    }
}
```

### 3.3 Customer-Specific Pricing

```php
<?php
// File: app/Domain/Sales/Pricing/CustomerPricingStrategy.php

declare(strict_types=1);

namespace App\Domain\Sales\Pricing;

use App\Contracts\Sales\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Customer-specific pricing: Check for customer price list first.
 */
class CustomerPricingStrategy implements PricingStrategy
{
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money
    {
        // Check customer-specific price
        $customerPrice = $contact->prices()
            ->where('product_id', $product->id)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($customerPrice) {
            return new Money($customerPrice->price, $product->currency ?? 'IDR');
        }

        // Fall back to standard price
        return new Money($product->selling_price, $product->currency ?? 'IDR');
    }

    public function getName(): string
    {
        return 'customer_specific';
    }
}
```

### 3.4 Volume Pricing

```php
<?php
// File: app/Domain/Sales/Pricing/VolumePricingStrategy.php

declare(strict_types=1);

namespace App\Domain\Sales\Pricing;

use App\Contracts\Sales\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Volume-based pricing: Discount based on quantity.
 */
class VolumePricingStrategy implements PricingStrategy
{
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money
    {
        $basePrice = $product->selling_price;

        // Get volume discount tier
        $tier = $product->volumeDiscounts()
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($tier) {
            $discountedPrice = (int) round($basePrice * (1 - $tier->discount_percent / 100));
            return new Money($discountedPrice, $product->currency ?? 'IDR');
        }

        return new Money($basePrice, $product->currency ?? 'IDR');
    }

    public function getName(): string
    {
        return 'volume';
    }
}
```

---

## Part 4: Approval Workflow Strategies

### 4.1 Strategy Interface

```php
<?php
// File: app/Contracts/Shared/ApprovalStrategy.php

declare(strict_types=1);

namespace App\Contracts\Shared;

use Illuminate\Database\Eloquent\Model;

interface ApprovalStrategy
{
    /**
     * Check if document requires approval.
     */
    public function requiresApproval(Model $document): bool;

    /**
     * Get required approvers.
     *
     * @return array<int> User IDs
     */
    public function getApprovers(Model $document): array;

    /**
     * Check if user can approve.
     */
    public function canApprove(Model $document, int $userId): bool;

    /**
     * Get strategy name.
     */
    public function getName(): string;
}
```

### 4.2 Amount-Based Approval

```php
<?php
// File: app/Domain/Shared/Approval/AmountBasedApprovalStrategy.php

declare(strict_types=1);

namespace App\Domain\Shared\Approval;

use App\Contracts\Shared\ApprovalStrategy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Approval based on document amount thresholds.
 */
class AmountBasedApprovalStrategy implements ApprovalStrategy
{
    /** @var array<int, array{threshold: int, role: string}> */
    private array $thresholds;

    public function __construct()
    {
        $this->thresholds = config('approval.thresholds', [
            ['threshold' => 10000000, 'role' => 'manager'],      // > 10M needs manager
            ['threshold' => 50000000, 'role' => 'director'],     // > 50M needs director
            ['threshold' => 100000000, 'role' => 'ceo'],         // > 100M needs CEO
        ]);
    }

    public function requiresApproval(Model $document): bool
    {
        $amount = $document->total_amount ?? 0;

        foreach ($this->thresholds as $tier) {
            if ($amount > $tier['threshold']) {
                return true;
            }
        }

        return false;
    }

    public function getApprovers(Model $document): array
    {
        $amount = $document->total_amount ?? 0;
        $requiredRole = null;

        foreach ($this->thresholds as $tier) {
            if ($amount > $tier['threshold']) {
                $requiredRole = $tier['role'];
            }
        }

        if (! $requiredRole) {
            return [];
        }

        return User::role($requiredRole)->pluck('id')->toArray();
    }

    public function canApprove(Model $document, int $userId): bool
    {
        $approvers = $this->getApprovers($document);

        return in_array($userId, $approvers);
    }

    public function getName(): string
    {
        return 'amount_based';
    }
}
```

---

## Part 5: Register Strategies

### 5.1 Strategy Service Provider

```php
<?php
// File: app/Providers/StrategyServiceProvider.php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Number Generation
use App\Services\Shared\NumberGenerationManager;
use App\Domain\Shared\NumberGeneration\SequentialNumberStrategy;
use App\Domain\Shared\NumberGeneration\ProjectBasedNumberStrategy;

// Tax Calculation
use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Domain\Accounting\Tax\TaxExclusiveStrategy;
use App\Domain\Accounting\Tax\TaxInclusiveStrategy;

// Pricing
use App\Contracts\Sales\PricingStrategy;
use App\Domain\Sales\Pricing\StandardPricingStrategy;
use App\Domain\Sales\Pricing\CustomerPricingStrategy;
use App\Domain\Sales\Pricing\VolumePricingStrategy;

// Approval
use App\Contracts\Shared\ApprovalStrategy;
use App\Domain\Shared\Approval\AmountBasedApprovalStrategy;

class StrategyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerNumberGenerationStrategies();
        $this->registerTaxStrategies();
        $this->registerPricingStrategies();
        $this->registerApprovalStrategies();
    }

    private function registerNumberGenerationStrategies(): void
    {
        $this->app->singleton(NumberGenerationManager::class, function ($app) {
            $manager = new NumberGenerationManager();

            $manager->register(new SequentialNumberStrategy());
            $manager->register(new ProjectBasedNumberStrategy());

            $manager->setDefault(config('documents.default_numbering', 'sequential'));

            return $manager;
        });
    }

    private function registerTaxStrategies(): void
    {
        $this->app->bind(TaxCalculationStrategy::class, function ($app) {
            $strategy = config('accounting.tax.strategy', 'exclusive');

            return match ($strategy) {
                'inclusive' => new TaxInclusiveStrategy(),
                default => new TaxExclusiveStrategy(),
            };
        });
    }

    private function registerPricingStrategies(): void
    {
        $this->app->bind(PricingStrategy::class, function ($app) {
            $strategy = config('sales.pricing.strategy', 'standard');

            return match ($strategy) {
                'customer_specific' => $app->make(CustomerPricingStrategy::class),
                'volume' => $app->make(VolumePricingStrategy::class),
                default => new StandardPricingStrategy(),
            };
        });
    }

    private function registerApprovalStrategies(): void
    {
        $this->app->bind(ApprovalStrategy::class, function ($app) {
            $strategy = config('approval.strategy', 'amount_based');

            return match ($strategy) {
                default => new AmountBasedApprovalStrategy(),
            };
        });
    }
}
```

---

## Verification Checklist

- [ ] Number generation strategies implemented
- [ ] Tax calculation strategies implemented
- [ ] Pricing strategies implemented
- [ ] Approval strategies implemented
- [ ] StrategyServiceProvider registered
- [ ] Configuration files created
- [ ] All tests pass

---

## Next Phase

Proceed to [Phase 6: State Machine Enhancement](./07-phase-6-state-machine.md).
