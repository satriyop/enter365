<?php

use App\Models\Accounting\Account;
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

    it('can update and delete a tax record', function () {
        $vat = Account::query()->where('code', '2-1200')->firstOrFail();

        $create = $this->postJson('/api/v1/tax-records', [
            'code' => 'PPN-UPD',
            'name' => 'PPN 11%',
            'rate' => 11,
            'applicability' => 'sales',
            'computation' => 'percentage',
            'invoice_account_id' => $vat->id,
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.computation', 'percentage')
            ->assertJsonPath('data.invoice_account_id', $vat->id);

        $id = (int) $create->json('data.id');

        $this->putJson("/api/v1/tax-records/{$id}", [
            'name' => 'PPN Keluaran 12%',
            'rate' => 12,
        ])->assertOk()
            ->assertJsonPath('data.name', 'PPN Keluaran 12%')
            ->assertJsonPath('data.rate', 12);

        $this->deleteJson("/api/v1/tax-records/{$id}")->assertOk();
        $this->getJson('/api/v1/tax-records')->assertOk()
            ->assertJsonMissing(['code' => 'PPN-UPD']);
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
            ->assertJsonPath('data.items.0.tax_rate', 12)
            ->assertJsonPath('data.tax_amount', 12_000)
            ->assertJsonPath('data.total_amount', 112_000);
    });

    it('stacks multiple product sales tax rates', function () {
        $ppn = TaxRecord::factory()->create([
            'code' => 'PPN-11',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
        $luxury = TaxRecord::factory()->create([
            'code' => 'PPnBM-1',
            'rate' => 1,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->salesTaxes()->sync([
            $ppn->id => ['kind' => 'sales'],
            $luxury->id => ['kind' => 'sales'],
        ]);

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

        expect($product->fresh()->salesTaxRate())->toBe(12.0);
        $invoice->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 12)
            ->assertJsonPath('data.tax_amount', 12_000)
            ->assertJsonPath('data.total_amount', 112_000);
    });

    it('posts invoice tax to the tax record invoice account', function () {
        $vat = Account::query()->where('code', '2-1200')->firstOrFail();
        $sales = TaxRecord::factory()->create([
            'code' => 'PPN-ACC',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
            'invoice_account_id' => $vat->id,
        ]);
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->salesTaxes()->sync([$sales->id => ['kind' => 'sales']]);
        $customer = Contact::factory()->customer()->create();

        $created = $this->postJson('/api/v1/invoices', [
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
        $created->assertCreated();
        $id = (int) $created->json('data.id');

        $this->postJson("/api/v1/invoices/{$id}/post")->assertOk();

        $posted = \App\Models\Sales\Invoice::query()->with('journalEntry.lines')->findOrFail($id);
        expect($posted->journalEntry?->lines->pluck('account_id')->all())->toContain($vat->id);
    });
});
