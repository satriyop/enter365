<?php

declare(strict_types=1);

namespace App\Domain\Sales\Pricing;

use App\Contracts\Sales\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Standard pricing: Use product's selling price.
 *
 * This is the default pricing strategy that simply
 * returns the product's configured selling_price.
 */
class StandardPricingStrategy implements PricingStrategy
{
    /**
     * Get standard selling price for product.
     */
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money
    {
        return Money::of($product->selling_price, 'IDR');
    }

    public function getName(): string
    {
        return 'standard';
    }
}
