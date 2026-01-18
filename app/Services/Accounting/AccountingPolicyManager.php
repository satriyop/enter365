<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Services\Accounting\Strategies\COGS\COGSOnDeliveryStrategy;
use App\Services\Accounting\Strategies\COGS\COGSOnInvoiceStrategy;
use App\Services\Accounting\Strategies\COGS\ManualCOGSStrategy;
use App\Services\Accounting\Strategies\Inventory\HybridInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PeriodicInventoryStrategy;
use App\Services\Accounting\Strategies\Inventory\PerpetualInventoryStrategy;
use App\Services\Accounting\Strategies\Manufacturing\JobCostingStrategy;
use App\Services\Accounting\Strategies\Manufacturing\ProjectBasedCostingStrategy;
use App\Services\Accounting\Strategies\Manufacturing\WIPAccountingStrategy;
use App\Services\Accounting\Strategies\Returns\FullReturnJournalStrategy;
use App\Services\Accounting\Strategies\Returns\InventoryOnlyReturnStrategy;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Manages accounting policy strategies.
 *
 * Reads configuration and returns appropriate strategy implementations.
 * Acts as a factory for accounting strategies.
 */
class AccountingPolicyManager
{
    /**
     * Map of inventory method names to strategy classes.
     *
     * @var array<string, class-string<InventoryAccountingStrategy>>
     */
    private array $inventoryStrategies = [
        'perpetual' => PerpetualInventoryStrategy::class,
        'periodic' => PeriodicInventoryStrategy::class,
        'hybrid' => HybridInventoryStrategy::class,
    ];

    /**
     * Map of COGS recognition names to strategy classes.
     *
     * @var array<string, class-string<COGSRecognitionStrategy>>
     */
    private array $cogsStrategies = [
        'on_invoice' => COGSOnInvoiceStrategy::class,
        'on_delivery' => COGSOnDeliveryStrategy::class,
        'manual' => ManualCOGSStrategy::class,
    ];

    /**
     * Map of return accounting names to strategy classes.
     *
     * @var array<string, class-string<ReturnAccountingStrategy>>
     */
    private array $returnStrategies = [
        'full_journal' => FullReturnJournalStrategy::class,
        'inventory_only' => InventoryOnlyReturnStrategy::class,
    ];

    /**
     * Map of manufacturing costing names to strategy classes.
     *
     * @var array<string, class-string<ManufacturingCostStrategy>>
     */
    private array $manufacturingStrategies = [
        'project_based' => ProjectBasedCostingStrategy::class,
        'job_costing' => JobCostingStrategy::class,
        'wip_accounting' => WIPAccountingStrategy::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Get the configured inventory accounting strategy.
     */
    public function inventory(): InventoryAccountingStrategy
    {
        $method = config('accounting.policies.inventory_method', 'hybrid');

        if (! isset($this->inventoryStrategies[$method])) {
            throw new InvalidArgumentException("Unknown inventory method: {$method}");
        }

        return $this->container->make($this->inventoryStrategies[$method]);
    }

    /**
     * Get the configured COGS recognition strategy.
     */
    public function cogs(): COGSRecognitionStrategy
    {
        $method = config('accounting.policies.cogs_recognition', 'on_invoice');

        if (! isset($this->cogsStrategies[$method])) {
            throw new InvalidArgumentException("Unknown COGS recognition method: {$method}");
        }

        return $this->container->make($this->cogsStrategies[$method]);
    }

    /**
     * Get the configured return accounting strategy.
     */
    public function returns(): ReturnAccountingStrategy
    {
        $method = config('accounting.policies.return_accounting', 'full_journal');

        if (! isset($this->returnStrategies[$method])) {
            throw new InvalidArgumentException("Unknown return accounting method: {$method}");
        }

        return $this->container->make($this->returnStrategies[$method]);
    }

    /**
     * Get the configured manufacturing cost strategy.
     */
    public function manufacturing(): ManufacturingCostStrategy
    {
        $method = config('accounting.policies.manufacturing_costing', 'project_based');

        if (! isset($this->manufacturingStrategies[$method])) {
            throw new InvalidArgumentException("Unknown manufacturing costing method: {$method}");
        }

        return $this->container->make($this->manufacturingStrategies[$method]);
    }

    /**
     * Get current policy configuration summary.
     *
     * @return array<string, string>
     */
    public function getCurrentPolicies(): array
    {
        return [
            'inventory_method' => config('accounting.policies.inventory_method', 'hybrid'),
            'cogs_recognition' => config('accounting.policies.cogs_recognition', 'on_invoice'),
            'return_accounting' => config('accounting.policies.return_accounting', 'full_journal'),
            'manufacturing_costing' => config('accounting.policies.manufacturing_costing', 'project_based'),
        ];
    }

    /**
     * Check if a specific policy is configured.
     */
    public function isPolicySet(string $policy, string $value): bool
    {
        return config("accounting.policies.{$policy}") === $value;
    }
}
