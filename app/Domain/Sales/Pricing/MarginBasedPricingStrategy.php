<?php

declare(strict_types=1);

namespace App\Domain\Sales\Pricing;

use App\Contracts\Sales\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Margin-based pricing: Apply markup percentage to cost.
 *
 * Price = purchase_price × (1 + marginPercent/100)
 *
 * Useful for cost-plus pricing scenarios.
 */
class MarginBasedPricingStrategy implements PricingStrategy
{
    public function __construct(
        private readonly float $defaultMarginPercent = 20.0
    ) {}

    /**
     * Calculate price based on purchase cost plus margin.
     */
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money
    {
        $baseCost = $product->purchase_price;
        $marginPercent = $this->defaultMarginPercent;

        // Round up to nearest whole number
        $price = (int) ceil($baseCost * (1 + $marginPercent / 100));

        return Money::of($price, 'IDR');
    }

    public function getName(): string
    {
        return 'margin_based';
    }
}
