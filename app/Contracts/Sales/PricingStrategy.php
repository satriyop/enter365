<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Domain\Shared\ValueObjects\Money;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;

/**
 * Strategy interface for product pricing.
 *
 * Allows different pricing models:
 * - Standard: Use product's selling_price
 * - Customer-specific: Check customer price lists
 * - Volume-based: Apply quantity discounts
 */
interface PricingStrategy
{
    /**
     * Get price for product and customer.
     *
     * @param  Product  $product  The product to price
     * @param  Contact  $contact  The customer buying
     * @param  float  $quantity  Quantity being purchased (for volume discounts)
     */
    public function getPrice(Product $product, Contact $contact, float $quantity = 1): Money;

    /**
     * Get strategy name for configuration.
     */
    public function getName(): string;
}
