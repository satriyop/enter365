<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPosition;
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

function fiscalPositionExpenseAccount(): Account
{
    return Account::query()->where('code', '5-1001')->first()
        ?? Account::query()->where('code', '6-1001')->firstOrFail();
}

describe('Fiscal positions master (#145)', function () {
    it('creates lists and shows a fiscal position with tax and account maps', function () {
        $sourceTax = TaxRecord::factory()->create([
            'code' => 'PPN-11',
            'name' => 'PPN 11%',
            'rate' => 11,
        ]);
        $destTax = TaxRecord::factory()->create([
            'code' => 'PPN-0',
            'name' => 'PPN 0%',
            'rate' => 0,
        ]);
        $sourceAccount = Account::factory()->revenue()->create();
        $destAccount = Account::factory()->revenue()->create();

        $create = $this->postJson('/api/v1/fiscal-positions', [
            'code' => 'EXPORT',
            'name' => 'Export 0%',
            'notes' => 'Map domestic VAT to export 0%.',
            'tax_maps' => [
                [
                    'source_tax_record_id' => $sourceTax->id,
                    'dest_tax_record_id' => $destTax->id,
                ],
            ],
            'account_maps' => [
                [
                    'source_account_id' => $sourceAccount->id,
                    'dest_account_id' => $destAccount->id,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.code', 'EXPORT')
            ->assertJsonPath('data.name', 'Export 0%')
            ->assertJsonPath('data.tax_maps.0.source_tax_record_id', $sourceTax->id)
            ->assertJsonPath('data.tax_maps.0.dest_tax_record_id', $destTax->id)
            ->assertJsonPath('data.account_maps.0.source_account_id', $sourceAccount->id)
            ->assertJsonPath('data.account_maps.0.dest_account_id', $destAccount->id);

        $id = (int) $create->json('data.id');

        $this->getJson('/api/v1/fiscal-positions')->assertOk()
            ->assertJsonPath('data.0.code', 'EXPORT');

        $this->getJson("/api/v1/fiscal-positions/{$id}")->assertOk()
            ->assertJsonPath('data.tax_maps.0.source_tax.code', 'PPN-11')
            ->assertJsonPath('data.tax_maps.0.dest_tax.code', 'PPN-0');
    });

    it('maps a source tax to exemption when dest is null', function () {
        $sourceTax = TaxRecord::factory()->create(['code' => 'PPN-EX', 'rate' => 11]);

        $create = $this->postJson('/api/v1/fiscal-positions', [
            'code' => 'NONPKP',
            'name' => 'Non PKP',
            'tax_maps' => [
                [
                    'source_tax_record_id' => $sourceTax->id,
                    'dest_tax_record_id' => null,
                ],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.tax_maps.0.dest_tax_record_id', null);
    });

    it('updates maps by replacing the full set', function () {
        $position = FiscalPosition::factory()->create(['code' => 'UPD']);
        $first = TaxRecord::factory()->create(['code' => 'T-A', 'rate' => 11]);
        $second = TaxRecord::factory()->create(['code' => 'T-B', 'rate' => 0]);

        $this->putJson("/api/v1/fiscal-positions/{$position->id}", [
            'name' => 'Updated export',
            'tax_maps' => [
                [
                    'source_tax_record_id' => $first->id,
                    'dest_tax_record_id' => $second->id,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated export')
            ->assertJsonPath('data.tax_maps.0.source_tax_record_id', $first->id);

        $this->putJson("/api/v1/fiscal-positions/{$position->id}", [
            'tax_maps' => [],
        ])->assertOk()
            ->assertJsonPath('data.tax_maps', []);
    });

    it('assigns a fiscal position on a contact', function () {
        $position = FiscalPosition::factory()->create(['code' => 'EXPORT']);

        $create = $this->postJson('/api/v1/contacts', [
            'code' => 'C-FP-1',
            'name' => 'Export Customer',
            'type' => 'customer',
            'fiscal_position_id' => $position->id,
        ]);

        $create->assertSuccessful()
            ->assertJsonPath('data.fiscal_position_id', $position->id);
    });

    it('refuses to delete a fiscal position still assigned to a contact', function () {
        $position = FiscalPosition::factory()->create();
        Contact::factory()->create(['fiscal_position_id' => $position->id]);

        $this->deleteJson("/api/v1/fiscal-positions/{$position->id}")
            ->assertConflict();

        expect(FiscalPosition::query()->whereKey($position->id)->exists())->toBeTrue();
    });

    it('deletes an unused fiscal position', function () {
        $position = FiscalPosition::factory()->create();

        $this->deleteJson("/api/v1/fiscal-positions/{$position->id}")
            ->assertOk();

        expect(FiscalPosition::query()->whereKey($position->id)->exists())->toBeFalse();
    });

    it('is a different route from fiscal periods', function () {
        $this->getJson('/api/v1/fiscal-positions')->assertOk();
        $this->getJson('/api/v1/fiscal-periods')->assertOk();
    });
});

describe('Fiscal position mapping on documents (#145)', function () {
    it('maps inherited bill purchase taxes through the vendor fiscal position', function () {
        $domestic = TaxRecord::factory()->create([
            'code' => 'PPN-BUY-11',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $zero = TaxRecord::factory()->create([
            'code' => 'PPN-BUY-0',
            'rate' => 0,
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
        $position = FiscalPosition::factory()->create(['code' => 'IMPORT']);
        $position->taxMaps()->create([
            'source_tax_record_id' => $domestic->id,
            'dest_tax_record_id' => $zero->id,
        ]);

        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->purchaseTaxes()->sync([$domestic->id => ['kind' => 'purchase']]);

        $supplier = Contact::factory()->supplier()->create([
            'fiscal_position_id' => $position->id,
        ]);
        $expense = fiscalPositionExpenseAccount();

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
            ->assertJsonPath('data.items.0.tax_rate', 0)
            ->assertJsonPath('data.items.0.tax_record_ids.0', $zero->id)
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.total_amount', 100_000);
    });

    it('maps invoice product sales tax rate through the customer fiscal position', function () {
        $domestic = TaxRecord::factory()->create([
            'code' => 'PPN-SALE-11',
            'rate' => 11,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
        $zero = TaxRecord::factory()->create([
            'code' => 'PPN-SALE-0',
            'rate' => 0,
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
        $position = FiscalPosition::factory()->create(['code' => 'EXPORT-INV']);
        $position->taxMaps()->create([
            'source_tax_record_id' => $domestic->id,
            'dest_tax_record_id' => $zero->id,
        ]);

        $product = Product::factory()->create(['tax_rate' => 0, 'is_taxable' => false]);
        $product->salesTaxes()->sync([$domestic->id => ['kind' => 'sales']]);

        $customer = Contact::factory()->customer()->create([
            'fiscal_position_id' => $position->id,
        ]);

        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
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

        $response->assertCreated()
            ->assertJsonPath('data.items.0.tax_rate', 0);
    });

    it('maps bill expense accounts through the vendor fiscal position', function () {
        $source = Account::factory()->expense()->create();
        $dest = Account::factory()->expense()->create();
        $position = FiscalPosition::factory()->create(['code' => 'ACCT-MAP']);
        $position->accountMaps()->create([
            'source_account_id' => $source->id,
            'dest_account_id' => $dest->id,
        ]);
        $supplier = Contact::factory()->supplier()->create([
            'fiscal_position_id' => $position->id,
        ]);

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Mapped expense',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 50_000,
                    'expense_account_id' => $source->id,
                    'tax_rate' => 0,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.expense_account_id', $dest->id);
    });
});
