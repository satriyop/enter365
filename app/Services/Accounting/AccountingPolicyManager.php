<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\Strategies\ClosingStrategy;
use App\Contracts\Accounting\Strategies\COGSRecognitionStrategy;
use App\Contracts\Accounting\Strategies\InventoryAccountingStrategy;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Contracts\Inventory\CostingStrategy;
use App\Models\Accounting\AccountingPolicy;
use App\Services\Accounting\Strategies\Closing\DirectClosingStrategy;
use App\Services\Accounting\Strategies\Closing\IncomeSummaryStrategy;
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
use App\Services\Inventory\Costing\FIFOCostingStrategy;
use App\Services\Inventory\Costing\WeightedAverageCostingStrategy;
use Illuminate\Contracts\Container\Container;

/**
 * Manages accounting policy strategies.
 *
 * Reads DB-persisted policies first, falls back to config.
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

    /**
     * Map of closing strategy names to strategy classes.
     *
     * @var array<string, class-string<ClosingStrategy>>
     */
    private array $closingStrategies = [
        'direct' => DirectClosingStrategy::class,
        'income_summary' => IncomeSummaryStrategy::class,
    ];

    /**
     * Map of inventory costing method names to strategy classes.
     *
     * @var array<string, class-string<CostingStrategy>>
     */
    private array $costingStrategies = [
        'weighted_average' => WeightedAverageCostingStrategy::class,
        'fifo' => FIFOCostingStrategy::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Read a policy value from DB first, fall back to config.
     *
     * Uses try/catch so the app still works during migration
     * (before the accounting_policies table exists).
     */
    private function getPolicyValue(string $key, string $default): string
    {
        try {
            $policy = AccountingPolicy::current();

            return $policy->{$key} ?? $default;
        } catch (\Throwable) {
            return config("accounting.policies.{$key}", $default);
        }
    }

    /**
     * Get the configured inventory accounting strategy.
     */
    public function inventory(): InventoryAccountingStrategy
    {
        $method = $this->getPolicyValue('inventory_method', 'hybrid');

        if (! isset($this->inventoryStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan inventory method',
                "Unknown inventory method: {$method}"
            );
        }

        return $this->container->make($this->inventoryStrategies[$method]);
    }

    /**
     * Get the configured COGS recognition strategy.
     */
    public function cogs(): COGSRecognitionStrategy
    {
        $method = $this->getPolicyValue('cogs_recognition', 'on_invoice');

        if (! isset($this->cogsStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan COGS recognition method',
                "Unknown COGS recognition method: {$method}"
            );
        }

        return $this->container->make($this->cogsStrategies[$method]);
    }

    /**
     * Get the configured return accounting strategy.
     */
    public function returns(): ReturnAccountingStrategy
    {
        $method = $this->getPolicyValue('return_accounting', 'full_journal');

        if (! isset($this->returnStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan return accounting method',
                "Unknown return accounting method: {$method}"
            );
        }

        return $this->container->make($this->returnStrategies[$method]);
    }

    /**
     * Get the configured manufacturing cost strategy.
     */
    public function manufacturing(): ManufacturingCostStrategy
    {
        $method = $this->getPolicyValue('manufacturing_costing', 'project_based');

        if (! isset($this->manufacturingStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan manufacturing costing method',
                "Unknown manufacturing costing method: {$method}"
            );
        }

        return $this->container->make($this->manufacturingStrategies[$method]);
    }

    /**
     * Get the configured closing strategy.
     */
    public function closing(): ClosingStrategy
    {
        $method = $this->getPolicyValue('closing_strategy', 'direct');

        if (! isset($this->closingStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan closing strategy',
                "Unknown closing strategy: {$method}"
            );
        }

        return $this->container->make($this->closingStrategies[$method]);
    }

    /**
     * Get the configured inventory costing strategy (Weighted Average, FIFO).
     */
    public function costing(): CostingStrategy
    {
        $method = $this->getPolicyValue('costing_method', 'weighted_average');

        if (! isset($this->costingStrategies[$method])) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan costing method',
                "Unknown costing method: {$method}"
            );
        }

        return $this->container->make($this->costingStrategies[$method]);
    }

    /**
     * Get current policy values (DB-first, config fallback).
     *
     * @return array<string, string>
     */
    public function getCurrentPolicies(): array
    {
        return [
            'inventory_method' => $this->getPolicyValue('inventory_method', 'hybrid'),
            'cogs_recognition' => $this->getPolicyValue('cogs_recognition', 'on_invoice'),
            'return_accounting' => $this->getPolicyValue('return_accounting', 'full_journal'),
            'manufacturing_costing' => $this->getPolicyValue('manufacturing_costing', 'project_based'),
            'closing_strategy' => $this->getPolicyValue('closing_strategy', 'direct'),
            'costing_method' => $this->getPolicyValue('costing_method', 'weighted_average'),
        ];
    }

    /**
     * Get available options for each policy (for API metadata).
     *
     * @return array<string, list<string>>
     */
    public function getAvailableOptions(): array
    {
        return [
            'inventory_method' => array_keys($this->inventoryStrategies),
            'cogs_recognition' => array_keys($this->cogsStrategies),
            'return_accounting' => array_keys($this->returnStrategies),
            'manufacturing_costing' => array_keys($this->manufacturingStrategies),
            'closing_strategy' => array_keys($this->closingStrategies),
            'costing_method' => array_keys($this->costingStrategies),
        ];
    }

    /**
     * Check if a specific policy is configured.
     */
    public function isPolicySet(string $policy, string $value): bool
    {
        return $this->getPolicyValue($policy, '') === $value;
    }
}
