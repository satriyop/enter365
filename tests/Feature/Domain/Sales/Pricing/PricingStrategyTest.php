<?php

declare(strict_types=1);

use App\Domain\Sales\Pricing\MarginBasedPricingStrategy;
use App\Domain\Sales\Pricing\StandardPricingStrategy;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('StandardPricingStrategy', function () {
    beforeEach(function () {
        $this->strategy = new StandardPricingStrategy;
    });

    it('returns product selling price', function () {
        $product = Product::factory()->create([
            'selling_price' => 150000,
        ]);
        $contact = Contact::factory()->create();

        $price = $this->strategy->getPrice($product, $contact);

        expect($price->amount)->toBe(150000)
            ->and($price->currency)->toBe('IDR');
    });

    it('ignores quantity', function () {
        $product = Product::factory()->create([
            'selling_price' => 100000,
        ]);
        $contact = Contact::factory()->create();

        $price1 = $this->strategy->getPrice($product, $contact, 1);
        $price10 = $this->strategy->getPrice($product, $contact, 10);

        expect($price1->amount)->toBe(100000)
            ->and($price10->amount)->toBe(100000);
    });

    it('has correct name', function () {
        expect($this->strategy->getName())->toBe('standard');
    });
});

describe('MarginBasedPricingStrategy', function () {
    it('applies default margin to purchase price', function () {
        $strategy = new MarginBasedPricingStrategy(20.0);

        $product = Product::factory()->create([
            'purchase_price' => 100000,
            'selling_price' => 150000, // Ignored
        ]);
        $contact = Contact::factory()->create();

        $price = $strategy->getPrice($product, $contact);

        // 100000 * 1.20 = 120000
        expect($price->amount)->toBe(120000)
            ->and($price->currency)->toBe('IDR');
    });

    it('rounds up fractional prices', function () {
        $strategy = new MarginBasedPricingStrategy(15.0);

        $product = Product::factory()->create([
            'purchase_price' => 12345, // 12345 * 1.15 = 14196.75 -> 14197
        ]);
        $contact = Contact::factory()->create();

        $price = $strategy->getPrice($product, $contact);

        expect($price->amount)->toBe(14197);
    });

    it('has correct name', function () {
        $strategy = new MarginBasedPricingStrategy;

        expect($strategy->getName())->toBe('margin_based');
    });
});
