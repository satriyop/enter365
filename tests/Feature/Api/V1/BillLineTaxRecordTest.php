<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\TaxTag;
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

function billExpenseAccount(): Account
{
    return Account::query()->where('code', '5-1001')->first()
        ?? Account::query()->where('code', '6-1001')->firstOrFail();
}

describe('Bill line tax records', function () {
    it('inherits product purchase taxes when tax_rate is omitted', function () {
        $purchase = TaxRecord::factory()->create([
            'code' => 'PPN-BUY-12',
            'name' => 'PPN Masukan 12%',
            'rate' => 12,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->purchaseTaxes()->sync([$purchase->id => ['kind' => 'purchase']]);

        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                    'expense_account_id' => $expense->id,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 12)
            ->assertJsonPath('data.items.0.tax_record_ids.0', $purchase->id)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.tax_amount', 12_000)
            ->assertJsonPath('data.total_amount', 112_000);
    });

    it('accepts tax_record_ids and stacks rates', function () {
        $ppn = TaxRecord::factory()->create([
            'code' => 'PPN-BUY-11',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $luxury = TaxRecord::factory()->create([
            'code' => 'PPnBM-BUY-1',
            'rate' => 1,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Office supplies',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                    'expense_account_id' => $expense->id,
                    'tax_record_ids' => [$ppn->id, $luxury->id],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 12)
            ->assertJsonPath('data.items.0.tax_amount', 12_000)
            ->assertJsonPath('data.tax_amount', 12_000);

        expect($response->json('data.items.0.tax_record_ids'))->toEqual([$ppn->id, $luxury->id]);
    });

    it('keeps explicit tax_tag_ids for journal grids', function () {
        $purchase = TaxRecord::factory()->create([
            'code' => 'PPN-TAG',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $tag = TaxTag::factory()->tax()->create();
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Tagged line',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 50_000,
                    'expense_account_id' => $expense->id,
                    'tax_record_ids' => [$purchase->id],
                    'tax_tag_ids' => [$tag->id],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 11)
            ->assertJsonPath('data.items.0.tax_tag_ids.0', $tag->id)
            ->assertJsonPath('data.items.0.tax_record_ids.0', $purchase->id);
    });

    it('rejects unknown tax_record_ids', function () {
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Bad tax',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'expense_account_id' => $expense->id,
                    'tax_record_ids' => [999_999],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.tax_record_ids.0']);
    });

    it('inherits product purchase taxes when tax_rate is null', function () {
        $purchase = TaxRecord::factory()->create([
            'code' => 'PPN-NULL',
            'rate' => 12,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->purchaseTaxes()->sync([$purchase->id => ['kind' => 'purchase']]);
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                    'expense_account_id' => $expense->id,
                    'tax_rate' => null,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 12)
            ->assertJsonPath('data.tax_amount', 12_000);
    });

    it('keeps zero inherited purchase tax instead of the header default', function () {
        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                    'expense_account_id' => $expense->id,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.total_amount', 100_000);
    });

    it('posts bill input tax to the tax record refund account', function () {
        $ppnIn = Account::query()->where('code', '1-1300')->firstOrFail();
        $purchase = TaxRecord::factory()->create([
            'code' => 'PPN-REF',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
            'refund_account_id' => $ppnIn->id,
        ]);
        $supplier = Contact::factory()->supplier()->create();
        $expense = billExpenseAccount();

        $created = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Taxed',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100_000,
                    'expense_account_id' => $expense->id,
                    'tax_record_ids' => [$purchase->id],
                ],
            ],
        ]);
        $created->assertCreated();
        $id = (int) $created->json('data.id');

        $this->postJson("/api/v1/bills/{$id}/post")->assertOk();

        $posted = \App\Models\Purchasing\Bill::query()->with('journalEntry.lines')->findOrFail($id);
        expect($posted->journalEntry?->lines->pluck('account_id')->all())->toContain($ppnIn->id);
    });
});

it('stacks multiple product purchase tax rates', function () {
    $ppn = TaxRecord::factory()->create([
        'code' => 'PPN-P-11',
        'rate' => 11,
        'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
    ]);
    $luxury = TaxRecord::factory()->create([
        'code' => 'PPnBM-P-1',
        'rate' => 1,
        'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
    ]);
    $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
    $product->purchaseTaxes()->sync([
        $ppn->id => ['kind' => 'purchase'],
        $luxury->id => ['kind' => 'purchase'],
    ]);

    expect($product->fresh()->purchaseTaxRate())->toBe(12.0);
});
