<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Accounting\TaxCalculationStrategy;
use App\Contracts\Sales\PricingStrategy;
use App\Contracts\Shared\ApprovalStrategy;
use App\Domain\Accounting\Tax\TaxExclusiveStrategy;
use App\Domain\Accounting\Tax\TaxInclusiveStrategy;
use App\Domain\Sales\Pricing\MarginBasedPricingStrategy;
use App\Domain\Sales\Pricing\StandardPricingStrategy;
use App\Domain\Shared\Approval\AmountBasedApprovalStrategy;
use App\Domain\Shared\Approval\AutoApproveStrategy;
use App\Domain\Shared\NumberGeneration\ProjectBasedNumberStrategy;
use App\Domain\Shared\NumberGeneration\SequentialNumberStrategy;
use App\Services\Shared\NumberGenerationManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers strategy patterns for configurable behaviors.
 *
 * Strategies can be switched via configuration to change
 * application behavior without code changes.
 */
class StrategyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerNumberGenerationStrategies();
        $this->registerTaxStrategies();
        $this->registerPricingStrategies();
        $this->registerApprovalStrategies();
    }

    /**
     * Register document number generation strategies.
     */
    private function registerNumberGenerationStrategies(): void
    {
        $this->app->singleton(NumberGenerationManager::class, function ($app) {
            $manager = new NumberGenerationManager;

            // Register available strategies
            $manager->register(new SequentialNumberStrategy);
            $manager->register(new ProjectBasedNumberStrategy);

            // Set default from config
            $default = config('documents.default_numbering', 'sequential');
            $manager->setDefault($default);

            return $manager;
        });
    }

    /**
     * Register tax calculation strategies.
     */
    private function registerTaxStrategies(): void
    {
        $this->app->bind(TaxCalculationStrategy::class, function ($app) {
            $strategy = config('accounting.tax.strategy', 'exclusive');

            return match ($strategy) {
                'inclusive' => new TaxInclusiveStrategy,
                default => new TaxExclusiveStrategy,
            };
        });
    }

    /**
     * Register pricing strategies.
     */
    private function registerPricingStrategies(): void
    {
        $this->app->bind(PricingStrategy::class, function ($app) {
            $strategy = config('sales.pricing.strategy', 'standard');

            return match ($strategy) {
                'margin_based' => new MarginBasedPricingStrategy(
                    config('sales.pricing.default_margin', 20.0)
                ),
                default => new StandardPricingStrategy,
            };
        });
    }

    /**
     * Register approval workflow strategies.
     */
    private function registerApprovalStrategies(): void
    {
        $this->app->bind(ApprovalStrategy::class, function ($app) {
            $strategy = config('approval.strategy', 'auto_approve');

            return match ($strategy) {
                'amount_based' => new AmountBasedApprovalStrategy,
                default => new AutoApproveStrategy,
            };
        });
    }
}
