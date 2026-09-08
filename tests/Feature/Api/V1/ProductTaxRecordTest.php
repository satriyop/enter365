<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Tax\TaxRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    authenticatedAdmin();
});

describe('Tax records master', function () {
    it('can create and list tax records', function () {
        $create = $this->postJson('/api/v1/tax-records', [
            'code' => 'PPN-SALES',
            'name' => 'PPN Keluaran 11%',
            'rate' => 11,
            'applicability' => 'sales',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'PPN-SALES')
            ->assertJsonPath('data.applicability', 'sales');

        $this->getJson('/api/v1/tax-records')->assertOk()
            ->assertJsonPath('data.0.code', 'PPN-SALES');
    });
});

describe('Product sales and purchase taxes', function () {
    it('attaches separate sales and purchase tax records on a product', function () {
        $sales = TaxRecord::factory()->sales()->create();
        $purchase = TaxRecord::factory()->purchase()->create();

        $create = $this->postJson('/api/v1/products', [
            'name' => 'Kopi Gayo',
            'type' => 'product',
            'unit' => 'kg',
            'purchase_price' => 80_000,
            'selling_price' => 120_000,
            'sales_tax_ids' => [$sales->id],
            'purchase_tax_ids' => [$purchase->id],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.sales_taxes.0.id', $sales->id)
            ->assertJsonPath('data.purchase_taxes.0.id', $purchase->id)
            ->assertJsonPath('data.tax_rate', 11);

        $productId = (int) $create->json('data.id');

        $this->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.sales_taxes.0.code', 'PPN-SALES')
            ->assertJsonPath('data.purchase_taxes.0.code', 'PPN-PURCHASE');
    });

    it('invoice lines inherit the product sales tax rate when tax_rate is omitted', function () {
        $sales = TaxRecord::factory()->create([
            'code' => 'PPN-12',
            'name' => 'PPN 12%',
            'rate' => 12,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->salesTaxes()->sync([$sales->id => ['kind' => 'sales']]);

        $customer = Contact::factory()->customer()->create();

        $invoice = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                ],
            ],
        ]);

        $invoice->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 12);
    });
});
