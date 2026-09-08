<?php

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\TaxTag;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Shared\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate as admin (has all permissions)
    authenticatedAdmin();
});

describe('Bill API', function () {

    it('can list all bills', function () {
        \App\Models\Purchasing\Bill::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/bills');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/bills');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'status' => ['value', 'label', 'color', 'is_terminal', 'is_editable'],
                    ],
                ],
            ]);
    });

    it('can filter bills by status', function () {
        Bill::factory()->draft()->count(2)->create();
        Bill::factory()->received()->count(3)->create();

        $response = $this->getJson('/api/v1/bills?status=received');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter bills by supplier', function () {
        $supplier = Contact::factory()->supplier()->create();
        Bill::factory()->forContact($supplier)->count(2)->create();
        Bill::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/bills?contact_id={$supplier->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a bill with items', function () {
        $supplier = Contact::factory()->supplier()->create();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'vendor_invoice_number' => 'INV-SUP-001',
            'bill_date' => '2024-12-25',
            'due_date' => '2025-01-25',
            'description' => 'Purchase from supplier',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Barang A',
                    'quantity' => 100,
                    'unit' => 'pcs',
                    'unit_price' => 25000,
                    'expense_account_id' => $expenseAccount->id,
                ],
                [
                    'description' => 'Barang B',
                    'quantity' => 50,
                    'unit' => 'pcs',
                    'unit_price' => 15000,
                    'expense_account_id' => $expenseAccount->id,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.vendor_invoice_number', 'INV-SUP-001')
            ->assertJsonCount(2, 'data.items');

        // Verify calculations: 2,500,000 + 750,000 = 3,250,000 subtotal
        // Tax: 3,250,000 * 11% = 357,500
        // Total: 3,607,500
        $response->assertJsonPath('data.subtotal', 3250000)
            ->assertJsonPath('data.tax_amount', 357500)
            ->assertJsonPath('data.total_amount', 3607500);

        expect($response->json('data.items.0.account_id'))->toBe($expenseAccount->id);
        expect($response->json('data.items.0.expense_account_id'))->toBe($expenseAccount->id);
    });

    it('requires an expense account on bill lines', function () {
        $supplier = Contact::factory()->supplier()->create();

        $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => '2024-12-25',
            'due_date' => '2025-01-25',
            'items' => [
                [
                    'description' => 'No account',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.expense_account_id']);
    });

    it('accepts account_id alias, analytic distribution, and tax tags on bill lines', function () {
        $supplier = Contact::factory()->supplier()->create();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first();
        $analytic = AnalyticAccount::factory()->create();
        $tag = TaxTag::factory()->tax()->create();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Office supplies',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                    'account_id' => $expenseAccount->id,
                    'analytic_distribution' => [(string) $analytic->id => 100],
                    'tax_tag_ids' => [$tag->id],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.account_id', $expenseAccount->id)
            ->assertJsonPath('data.items.0.analytic_distribution.'.$analytic->id, 100)
            ->assertJsonPath('data.items.0.tax_tag_ids.0', $tag->id);

        $item = BillItem::query()->where('bill_id', $response->json('data.id'))->first();
        expect($item->expense_account_id)->toBe($expenseAccount->id);
        expect($item->analytic_distribution)->toMatchArray([(string) $analytic->id => 100]);
        expect($item->tax_tag_ids)->toEqual([$tag->id]);
    });

    it('rejects unknown analytic ids on bill lines', function () {
        $supplier = Contact::factory()->supplier()->create();
        $expenseAccount = Account::where('code', '5-1001')->first()
            ?? Account::where('code', '6-1001')->first();

        $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                [
                    'description' => 'Bad analytic',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'expense_account_id' => $expenseAccount->id,
                    'analytic_distribution' => ['99999' => 100],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.analytic_distribution']);
    });

    it('validates required fields when creating bill', function () {
        $response = $this->postJson('/api/v1/bills', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'bill_date', 'due_date', 'items']);
    });

    it('can show a single bill with items', function () {
        $bill = Bill::factory()->create();
        BillItem::factory()->forBill($bill)->count(2)->create();

        $response = $this->getJson("/api/v1/bills/{$bill->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft bill', function () {
        $bill = Bill::factory()->draft()->create();
        BillItem::factory()->forBill($bill)->create();

        $response = $this->putJson("/api/v1/bills/{$bill->id}", [
            'vendor_invoice_number' => 'UPDATED-INV',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vendor_invoice_number', 'UPDATED-INV')
            ->assertJsonPath('data.description', 'Updated description');
    });

    it('cannot update posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->putJson("/api/v1/bills/{$bill->id}", [
            'description' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft bill', function () {
        $bill = Bill::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/bills/{$bill->id}");

        $response->assertOk();
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    });

    it('cannot delete posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->deleteJson("/api/v1/bills/{$bill->id}");

        $response->assertUnprocessable();
    });

    it('can post a draft bill to journal', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'line_total' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'received')
            ->assertJsonStructure(['data' => ['journal_entry' => ['lines']]]);

        $this->assertNotNull($response->json('data.journal_entry_id'));
        expect($response->json('data.journal_entry.lines'))->toBeArray()->not->toBeEmpty();

        $show = $this->getJson("/api/v1/bills/{$bill->id}");
        $show->assertOk()
            ->assertJsonPath('data.journal_entry.id', $response->json('data.journal_entry_id'));
        expect($show->json('data.journal_entry.lines'))->toBeArray()->not->toBeEmpty();
    });

    it('posts bill with discount creating discount contra JE line', function () {
        $supplier = Contact::factory()->supplier()->create();
        // subtotal=1,000,000 discount=50,000 tax=110,000 total=1,060,000
        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'discount_amount' => 50000,
            'tax_amount' => 110000,
            'total_amount' => 1060000,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'line_total' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertOk();

        $jeId = $response->json('data.journal_entry_id');
        $this->assertNotNull($jeId);

        // Verify discount line exists: Cr Purchase Discount (5-1003) = 50,000
        $discountAccount = Account::where('code', '5-1003')->first();
        $discountLine = JournalEntryLine::where('journal_entry_id', $jeId)
            ->where('account_id', $discountAccount->id)
            ->first();

        expect($discountLine)->not->toBeNull()
            ->and($discountLine->credit)->toBe(50000)
            ->and($discountLine->debit)->toBe(0);

        // Verify AP credit = total_amount (net of discount)
        $apAccount = Account::where('code', '2-1100')->first();
        $apLine = JournalEntryLine::where('journal_entry_id', $jeId)
            ->where('account_id', $apAccount->id)
            ->first();

        expect($apLine->credit)->toBe(1060000);
    });

    it('copies per-line analytic distribution and tax tags onto the bill journal entry', function () {
        $supplier = Contact::factory()->supplier()->create();
        $expenseAccount = Account::where('code', '5-1002')->first()
            ?? Account::where('code', '5-1001')->first();
        $analyticA = AnalyticAccount::factory()->create();
        $analyticB = AnalyticAccount::factory()->create();
        $tagA = TaxTag::factory()->tax()->create();
        $tagB = TaxTag::factory()->tax()->create();

        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 300000,
            'tax_amount' => 0,
            'total_amount' => 300000,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'description' => 'Line A',
            'expense_account_id' => $expenseAccount->id,
            'line_total' => 100000,
            'analytic_distribution' => [(string) $analyticA->id => 100],
            'tax_tag_ids' => [$tagA->id],
        ]);
        BillItem::factory()->forBill($bill)->create([
            'description' => 'Line B',
            'expense_account_id' => $expenseAccount->id,
            'line_total' => 200000,
            'analytic_distribution' => [(string) $analyticB->id => 100],
            'tax_tag_ids' => [$tagB->id],
        ]);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertOk();

        $jeLines = collect($response->json('data.journal_entry.lines'))
            ->filter(fn (array $line) => (int) $line['debit'] > 0 && ($line['analytic_distribution'] ?? null))
            ->values();

        expect($jeLines)->toHaveCount(2)
            ->and($jeLines[0]['description'])->toBe('Line A')
            ->and($jeLines[0]['analytic_distribution'])->toMatchArray([(string) $analyticA->id => 100])
            ->and($jeLines[0]['tax_tag_ids'])->toEqual([$tagA->id])
            ->and($jeLines[1]['description'])->toBe('Line B')
            ->and($jeLines[1]['analytic_distribution'])->toMatchArray([(string) $analyticB->id => 100])
            ->and($jeLines[1]['tax_tag_ids'])->toEqual([$tagB->id]);
    });

    it('cannot post already posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertUnprocessable();
    });

    it('cannot post bill without items', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertUnprocessable();
    });

    it('transitions to partial status when partially paid', function () {
        Event::fake();

        $supplier = Contact::factory()->supplier()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        $bill = Bill::factory()->received()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 500000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
            'bill_id' => $bill->id,
        ]);

        $bill->refresh();
        expect($bill->status)->toBe(DocumentStatus::Partial);
    });

    it('transitions to paid status when fully paid', function () {
        Event::fake();

        $supplier = Contact::factory()->supplier()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        $bill = Bill::factory()->received()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
            'bill_id' => $bill->id,
        ]);

        $bill->refresh();
        expect($bill->status)->toBe(DocumentStatus::Paid);
    });

    it('creates a vendor credit note from a posted bill', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
        ]);
        BillItem::factory()->forBill($bill)->create(['line_total' => 1000000]);

        $this->postJson("/api/v1/bills/{$bill->id}/post")->assertOk();
        $bill->refresh();

        $response = $this->postJson("/api/v1/bills/{$bill->id}/credit-note", [
            'reason' => 'Kesalahan harga vendor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source_type', JournalEntry::SOURCE_REVERSAL)
            ->assertJsonPath('data.reversal_of.id', $bill->journal_entry_id)
            ->assertJsonPath('data.is_posted', true);

        $apAccount = Account::where('code', '2-1100')->first();
        $originalAp = JournalEntryLine::query()
            ->where('journal_entry_id', $bill->journal_entry_id)
            ->where('account_id', $apAccount->id)
            ->first();
        $reversalAp = JournalEntryLine::query()
            ->where('journal_entry_id', $response->json('data.id'))
            ->where('account_id', $apAccount->id)
            ->first();

        expect($originalAp)->not->toBeNull()
            ->and($reversalAp)->not->toBeNull()
            ->and($reversalAp->debit)->toBe($originalAp->credit)
            ->and($bill->fresh()->status)->not->toBe(DocumentStatus::Cancelled);

        $this->assertDatabaseMissing('purchase_returns', ['bill_id' => $bill->id]);
    });

    it('cannot create a credit note from a draft bill', function () {
        $bill = Bill::factory()->draft()->create();

        $this->postJson("/api/v1/bills/{$bill->id}/credit-note")
            ->assertUnprocessable();
    });

    it('matches a posted bill to a purchase order from the same vendor', function () {
        $supplier = Contact::factory()->supplier()->create();
        $product = Product::factory()->create();
        $bill = Bill::factory()->received()->forContact($supplier)->create();
        $billItem = BillItem::factory()->forBill($bill)->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100000,
            'line_total' => 200000,
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create(['contact_id' => $supplier->id]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'line_total' => 500000,
        ]);

        $worksheet = $this->getJson("/api/v1/bills/{$bill->id}/purchase-matching?purchase_order_id={$purchaseOrder->id}");
        $worksheet->assertOk()
            ->assertJsonPath('data.purchase_lines.0.quantity', 5)
            ->assertJsonPath('data.purchase_lines.0.billed_quantity', 0)
            ->assertJsonPath('data.purchase_lines.0.qty_to_invoice', 5);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/match-purchase-order", [
            'purchase_order_id' => $purchaseOrder->id,
            'lines' => [
                [
                    'bill_item_id' => $billItem->id,
                    'purchase_order_item_id' => $poItem->id,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.purchase_order_id', $purchaseOrder->id)
            ->assertJsonPath('data.items.0.purchase_order_item_id', $poItem->id);

        $matched = $this->getJson("/api/v1/bills/{$bill->id}/purchase-matching?purchase_order_id={$purchaseOrder->id}");
        $matched->assertOk()
            ->assertJsonPath('data.purchase_lines.0.billed_quantity', 2)
            ->assertJsonPath('data.purchase_lines.0.billed_amount', 200000)
            ->assertJsonPath('data.purchase_lines.0.qty_to_invoice', 3);
    });

    it('rejects purchase matching when the PO belongs to another vendor', function () {
        $supplier = Contact::factory()->supplier()->create();
        $other = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->received()->forContact($supplier)->create();
        $billItem = BillItem::factory()->forBill($bill)->create();
        $purchaseOrder = PurchaseOrder::factory()->create(['contact_id' => $other->id]);
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id]);

        $this->postJson("/api/v1/bills/{$bill->id}/match-purchase-order", [
            'purchase_order_id' => $purchaseOrder->id,
            'lines' => [
                [
                    'bill_item_id' => $billItem->id,
                    'purchase_order_item_id' => $poItem->id,
                ],
            ],
        ])->assertUnprocessable();
    });
});
