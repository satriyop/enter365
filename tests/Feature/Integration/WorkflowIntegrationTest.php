<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\Shared\Payment;
use App\Models\User;
use App\Services\Inventory\StockOpnameService;
use App\Services\Manufacturing\MaterialRequisitionService;
use App\Services\Manufacturing\WorkOrderService;
use App\Services\Purchasing\GoodsReceiptNoteService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Sales\DeliveryOrderService;
use App\Services\Sales\InvoiceService;
use App\Services\Sales\QuotationService;
use App\Services\Shared\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Create all chart-of-accounts entries needed by the journal service.
 *
 * @return array<string, Account> keyed by account code
 */
function createRequiredAccounts(array $codes = []): array
{
    $definitions = [
        '1-1001' => ['name' => 'Kas', 'type' => Account::TYPE_ASSET, 'factory' => 'cash'],
        '1-1002' => ['name' => 'Bank', 'type' => Account::TYPE_ASSET, 'factory' => 'bank'],
        '1-1100' => ['name' => 'Piutang Usaha', 'type' => Account::TYPE_ASSET, 'factory' => 'accountsReceivable'],
        '1-1300' => ['name' => 'PPN Masukan', 'type' => Account::TYPE_ASSET, 'factory' => 'taxReceivable'],
        '1-1400' => ['name' => 'Persediaan', 'type' => Account::TYPE_ASSET, 'subtype' => Account::SUBTYPE_CURRENT_ASSET],
        '2-1100' => ['name' => 'Utang Usaha', 'type' => Account::TYPE_LIABILITY, 'factory' => 'accountsPayable'],
        '2-1200' => ['name' => 'PPN Keluaran', 'type' => Account::TYPE_LIABILITY, 'factory' => 'taxPayable'],
        '4-1001' => ['name' => 'Pendapatan Penjualan', 'type' => Account::TYPE_REVENUE, 'factory' => 'salesRevenue'],
        '5-1001' => ['name' => 'Harga Pokok Penjualan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE],
        '5-1002' => ['name' => 'Pembelian', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE],
        '5-2900' => ['name' => 'Penyesuaian Persediaan', 'type' => Account::TYPE_EXPENSE, 'subtype' => Account::SUBTYPE_OPERATING_EXPENSE],
    ];

    if (empty($codes)) {
        $codes = array_keys($definitions);
    }

    $accounts = [];
    foreach ($codes as $code) {
        if (! isset($definitions[$code])) {
            continue;
        }

        $def = $definitions[$code];

        // Use named factory states where available for system accounts
        if (isset($def['factory'])) {
            $accounts[$code] = Account::factory()->{$def['factory']}()->create();
        } else {
            $accounts[$code] = Account::factory()->create([
                'code' => $code,
                'name' => $def['name'],
                'type' => $def['type'],
                'subtype' => $def['subtype'] ?? null,
                'is_active' => true,
            ]);
        }
    }

    return $accounts;
}

// ---------------------------------------------------------------------------
// Existing Tests (preserved)
// ---------------------------------------------------------------------------

describe('Sales Workflow: Quotation → Invoice', function () {
    test('complete quotation lifecycle from draft to invoice', function () {
        $service = app(QuotationService::class);

        $quotation = Quotation::factory()->draft()->create([
            'valid_until' => now()->addDays(30),
        ]);

        QuotationItem::factory()->count(2)->create([
            'quotation_id' => $quotation->id,
        ]);

        // Submit
        $quotation = $service->submit($quotation, $this->user->id);
        expect($quotation->status)->toBe(DocumentStatus::Submitted)
            ->and($quotation->submitted_at)->not->toBeNull();

        // Approve
        $quotation = $service->approve($quotation, $this->user->id);
        expect($quotation->status)->toBe(DocumentStatus::Approved)
            ->and($quotation->approved_at)->not->toBeNull();

        // Convert to Invoice
        $invoice = $service->convertToInvoice($quotation);
        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->contact_id)->toBe($quotation->contact_id)
            ->and($invoice->items)->toHaveCount(2);

        // Verify quotation marked as converted
        $quotation->refresh();
        expect($quotation->status)->toBe(DocumentStatus::Converted)
            ->and($quotation->converted_to_invoice_id)->toBe($invoice->id);
    });
});

describe('Purchasing Workflow: PO → Bill', function () {
    test('purchase order state transitions from draft to approved', function () {
        $service = app(PurchaseOrderService::class);

        $po = PurchaseOrder::factory()->draft()->create();
        PurchaseOrderItem::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
        ]);

        // Submit
        $po = $service->submit($po, $this->user->id);
        expect($po->status)->toBe(DocumentStatus::Submitted)
            ->and($po->submitted_at)->not->toBeNull();

        // Approve
        $po = $service->approve($po, $this->user->id);
        expect($po->status)->toBe(DocumentStatus::Approved)
            ->and($po->approved_at)->not->toBeNull();
    });

    test('received PO converts to bill', function () {
        $service = app(PurchaseOrderService::class);

        $po = PurchaseOrder::factory()->received()->create();
        PurchaseOrderItem::factory()->fullyReceived()->count(2)->create([
            'purchase_order_id' => $po->id,
        ]);

        $bill = $service->convertToBill($po);
        expect($bill)->toBeInstanceOf(Bill::class)
            ->and($bill->contact_id)->toBe($po->contact_id)
            ->and($bill->items)->toHaveCount(2);

        $po->refresh();
        expect($po->converted_to_bill_id)->toBe($bill->id)
            ->and($po->converted_at)->not->toBeNull();
    });
});

describe('Manufacturing Workflow: Work Order lifecycle', function () {
    test('complete work order with material reservation and consumption', function () {
        $service = app(WorkOrderService::class);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'purchase_price' => 150000,
        ]);

        $wo = WorkOrder::factory()->draft()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        WorkOrderItem::factory()->material()->create([
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'unit_cost' => 150000,
            'total_estimated_cost' => 1500000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 150000,
        ]);

        // Confirm (reserves materials)
        $wo = $service->confirm($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Confirmed);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->reserved_quantity)->toBe(10);

        // Start
        $wo = $service->start($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::InProgress);

        // Complete (consumes materials)
        $wo = $service->complete($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Completed);

        // Verify inventory impact
        $stock->refresh();
        expect($stock->quantity)->toBe(90)
            ->and($stock->reserved_quantity)->toBe(0);

        // Verify inventory movement created
        $movement = InventoryMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $wo->id)
            ->first();
        expect($movement)->not->toBeNull()
            ->and($movement->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movement->quantity)->toBe(10);
    });
});

describe('Inventory Workflow: Stock Opname lifecycle', function () {
    test('complete stock opname from draft to approved with inventory adjustments', function () {
        $service = app(StockOpnameService::class);

        // Create required accounts for journal entry
        $accounts = createRequiredAccounts(['1-1400', '5-2900']);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'average_cost' => 100000,
            'total_value' => 5000000,
        ]);

        // Create
        $opname = $service->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->user->id,
        ]);
        expect($opname->status)->toBe(DocumentStatus::Draft);

        // Generate items (pulls from warehouse stock)
        $opname = $service->generateItems($opname);
        expect($opname->items)->toHaveCount(1);

        $opnameItem = $opname->items->first();
        expect($opnameItem->product_id)->toBe($product->id)
            ->and($opnameItem->system_quantity)->toEqual(50);

        // Start counting
        $opname = $service->startCounting($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Counting);

        // Simulate counting (physical count = 48, shortage of 2)
        $opnameItem->refresh();
        $opnameItem->update([
            'counted_quantity' => 48,
            'variance_quantity' => -2,
            'variance_value' => -200000,
            'counted_at' => now(),
        ]);

        // Submit for review
        $opname = $service->submitForReview($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Reviewed);

        // Approve (adjusts inventory)
        $opname = $service->approve($opname, $this->user->id);
        expect($opname->status)->toBe(DocumentStatus::Completed);

        // Verify stock adjusted
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(48);

        // Verify journal entry created via polymorphic source
        $journalEntry = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->first();
        expect($journalEntry)->not->toBeNull('Stock opname should create a journal entry');

        // Verify journal is balanced (total debit == total credit)
        $totalDebit = $journalEntry->lines->sum('debit');
        $totalCredit = $journalEntry->lines->sum('credit');
        expect($totalDebit)->toBe($totalCredit)
            ->and($totalDebit)->toBeGreaterThan(0);

        // Verify correct accounts used: Inventory (1-1400) and Adjustment (5-2900)
        $accountCodes = $journalEntry->lines->map(
            fn ($line) => $line->account->code
        )->sort()->values()->all();
        expect($accountCodes)->toBe(['1-1400', '5-2900']);

        // For a shortage: DR Adjustment (5-2900), CR Inventory (1-1400)
        $adjustmentLine = $journalEntry->lines->first(
            fn ($line) => $line->account->code === '5-2900'
        );
        $inventoryLine = $journalEntry->lines->first(
            fn ($line) => $line->account->code === '1-1400'
        );
        expect($adjustmentLine->debit)->toBeGreaterThan(0)
            ->and($adjustmentLine->credit)->toBe(0)
            ->and($inventoryLine->credit)->toBeGreaterThan(0)
            ->and($inventoryLine->debit)->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// New E2E Cycle Tests
// ---------------------------------------------------------------------------

describe('Complete Purchasing Cycle: PO → GRN → Bill → Payment', function () {
    test('full purchasing workflow with inventory, journal entries, and payment', function () {
        $accounts = createRequiredAccounts(['1-1002', '2-1100', '1-1300', '5-1002', '1-1100']);

        $vendor = Contact::factory()->vendor()->create();
        $warehouse = Warehouse::factory()->create();

        $productA = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
        ]);
        $productB = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 250000,
        ]);

        // Ensure initial stock records exist (at zero)
        ProductStock::factory()->create([
            'product_id' => $productA->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
            'total_value' => 0,
        ]);
        ProductStock::factory()->create([
            'product_id' => $productB->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'average_cost' => 0,
            'total_value' => 0,
        ]);

        // --- Phase 1: Create and approve PO ---
        $poService = app(PurchaseOrderService::class);

        $po = PurchaseOrder::factory()->draft()->forContact($vendor)->create([
            'subtotal' => 2250000,
            'tax_rate' => 11.00,
            'tax_amount' => 247500,
            'total_amount' => 2497500,
            'base_currency_total' => 2497500,
        ]);

        $poItemA = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $productA->id,
            'description' => $productA->name,
            'quantity' => 10,
            'unit_price' => 100000,
            'tax_rate' => 11.00,
            'tax_amount' => 110000,
            'line_total' => 1000000,
        ]);
        $poItemB = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $productB->id,
            'description' => $productB->name,
            'quantity' => 5,
            'unit_price' => 250000,
            'tax_rate' => 11.00,
            'tax_amount' => 137500,
            'line_total' => 1250000,
        ]);

        $po = $poService->submit($po, $this->user->id);
        $po = $poService->approve($po, $this->user->id);
        expect($po->status)->toBe(DocumentStatus::Approved);

        // --- Phase 2: GRN - Receive goods into warehouse ---
        $grnService = app(GoodsReceiptNoteService::class);

        $grn = $grnService->createFromPurchaseOrder($po, [
            'warehouse_id' => $warehouse->id,
        ]);
        expect($grn->items)->toHaveCount(2);

        $grn = $grnService->startReceiving($grn, $this->user->id);
        expect($grn->status)->toBe(DocumentStatus::Receiving);

        // Receive full quantities
        foreach ($grn->items as $grnItem) {
            $grnService->updateItem($grnItem, [
                'quantity_received' => $grnItem->quantity_ordered,
            ]);
        }

        $grn = $grnService->complete($grn, $this->user->id);
        expect($grn->status)->toBe(DocumentStatus::Completed);

        // Assert: PO items have updated received quantities
        $poItemA->refresh();
        $poItemB->refresh();
        expect((float) $poItemA->quantity_received)->toBe(10.0)
            ->and((float) $poItemB->quantity_received)->toBe(5.0);

        // Assert: ProductStock increased
        $stockA = ProductStock::where('product_id', $productA->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $stockB = ProductStock::where('product_id', $productB->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stockA->quantity)->toBe(10)
            ->and($stockB->quantity)->toBe(5);

        // Assert: InventoryMovements created (TYPE_IN)
        $movementsIn = InventoryMovement::where('reference_type', $grn::class)
            ->where('reference_id', $grn->id)
            ->where('type', InventoryMovement::TYPE_IN)
            ->get();
        expect($movementsIn)->toHaveCount(2);

        // --- Phase 3: Convert to Bill and post ---
        $po->refresh();
        $bill = $poService->convertToBill($po);
        expect($bill)->toBeInstanceOf(Bill::class)
            ->and($bill->items)->toHaveCount(2);

        $billService = app(\App\Services\Purchasing\BillService::class);
        $bill = $billService->post($bill);
        expect($bill->status)->toBe(DocumentStatus::Received)
            ->and($bill->journal_entry_id)->not->toBeNull();

        // Assert: Bill journal entry is balanced
        $billJournal = $bill->journalEntry->load('lines.account');
        $billDebit = $billJournal->lines->sum('debit');
        $billCredit = $billJournal->lines->sum('credit');
        expect($billDebit)->toBe($billCredit)
            ->and($billDebit)->toBeGreaterThan(0);

        // Assert: Journal has DR Expense (5-1002) and CR AP (2-1100)
        $billAccountCodes = $billJournal->lines->pluck('account.code')->sort()->values()->all();
        expect($billAccountCodes)->toContain('5-1002')
            ->and($billAccountCodes)->toContain('2-1100');

        // --- Phase 4: Pay the bill ---
        $paymentService = app(PaymentService::class);

        $payment = $paymentService->createForBill($bill->fresh(), [
            'amount' => $bill->total_amount,
            'payment_date' => now()->toDateString(),
            'cash_account_id' => $accounts['1-1002']->id,
            'payment_method' => Payment::METHOD_TRANSFER,
        ]);

        $bill->refresh();
        expect($bill->paid_amount)->toBe($bill->total_amount)
            ->and($bill->status)->toBe(DocumentStatus::Paid);

        // Assert: Payment journal entry (DR AP / CR Bank)
        expect($payment->journal_entry_id)->not->toBeNull();
        $paymentJournal = $payment->journalEntry->load('lines.account');
        $payDebit = $paymentJournal->lines->sum('debit');
        $payCredit = $paymentJournal->lines->sum('credit');
        expect($payDebit)->toBe($payCredit)
            ->and($payDebit)->toBeGreaterThan(0);

        $payAccountCodes = $paymentJournal->lines->pluck('account.code')->sort()->values()->all();
        expect($payAccountCodes)->toContain('2-1100')  // AP debited
            ->and($payAccountCodes)->toContain('1-1002'); // Bank credited

        // --- Phase 5: Final accounting state assertions ---
        // After full purchase-to-payment cycle, verify net balances across ALL journals.

        // AP (2-1100, liability/credit-normal): CR on bill post, DR on payment → net 0
        $accounts['2-1100']->refresh();
        expect($accounts['2-1100']->getBalance())->toBe(0, 'AP must zero out after full payment');

        // Bank (1-1002, asset/debit-normal): only CR on payment → negative balance (cash outflow)
        $accounts['1-1002']->refresh();
        expect($accounts['1-1002']->getBalance())->toBe(-$bill->total_amount, 'Bank must reflect cash outflow');

        // Expense (5-1002, expense/debit-normal): only DR on bill post → equals subtotal
        $accounts['5-1002']->refresh();
        expect($accounts['5-1002']->getBalance())->toBe($bill->subtotal, 'Expense must equal bill subtotal');

        // PPN Masukan (1-1300, asset/debit-normal): only DR on bill post → equals tax amount
        $accounts['1-1300']->refresh();
        expect($accounts['1-1300']->getBalance())->toBe($bill->tax_amount, 'PPN Masukan must equal tax amount');

        // Global trial balance: sum of all debits must equal sum of all credits
        $totalDebits = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->sum('journal_entry_lines.debit');
        $totalCredits = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->sum('journal_entry_lines.credit');
        expect($totalDebits)->toBe($totalCredits, 'Trial balance must hold: total debits = total credits')
            ->and($totalDebits)->toBeGreaterThan(0);
    });
});

describe('Complete Sales Cycle: Quotation → Invoice → DO → Payment', function () {
    test('full sales workflow with journal entries, inventory deduction, and payment', function () {
        $accounts = createRequiredAccounts(['1-1002', '1-1100', '1-1400', '2-1100', '2-1200', '4-1001', '5-1001']);

        $customer = Contact::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();

        $productA = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
            'selling_price' => 200000,
        ]);
        $productB = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 50000,
            'selling_price' => 120000,
        ]);

        // Seed warehouse with stock
        ProductStock::factory()->create([
            'product_id' => $productA->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'average_cost' => 100000,
            'total_value' => 10000000,
        ]);
        ProductStock::factory()->create([
            'product_id' => $productB->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'average_cost' => 50000,
            'total_value' => 2500000,
        ]);

        // --- Phase 1: Quotation → Invoice ---
        $quotationService = app(QuotationService::class);

        $quotation = Quotation::factory()->draft()->forContact($customer)->create([
            'valid_until' => now()->addDays(30),
            'subtotal' => 3400000,       // 5*200000 + 20*120000
            'tax_rate' => 11.00,
            'tax_amount' => 374000,
            'total_amount' => 3774000,
            'base_currency_total' => 3774000,
        ]);

        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $productA->id,
            'description' => $productA->name,
            'quantity' => 5,
            'unit_price' => 200000,
            'tax_rate' => 11.00,
            'tax_amount' => 110000,
            'line_total' => 1000000,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $productB->id,
            'description' => $productB->name,
            'quantity' => 20,
            'unit_price' => 120000,
            'tax_rate' => 11.00,
            'tax_amount' => 264000,
            'line_total' => 2400000,
        ]);

        $quotation = $quotationService->submit($quotation, $this->user->id);
        $quotation = $quotationService->approve($quotation, $this->user->id);
        $invoice = $quotationService->convertToInvoice($quotation);

        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->items)->toHaveCount(2);

        // --- Phase 2: Post invoice (creates AR/Revenue journal) ---
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->post($invoice);

        expect($invoice->status)->toBe(DocumentStatus::Sent)
            ->and($invoice->journal_entry_id)->not->toBeNull();

        // Assert: Invoice journal is balanced
        $invoiceJournal = $invoice->journalEntry->load('lines.account');
        $invDebit = $invoiceJournal->lines->sum('debit');
        $invCredit = $invoiceJournal->lines->sum('credit');
        expect($invDebit)->toBe($invCredit)
            ->and($invDebit)->toBeGreaterThan(0);

        // Assert: Journal has DR AR (1-1100) and CR Revenue (4-1001) + Tax (2-1200)
        $invAccountCodes = $invoiceJournal->lines->pluck('account.code')->sort()->values()->all();
        expect($invAccountCodes)->toContain('1-1100')
            ->and($invAccountCodes)->toContain('4-1001');

        // --- Phase 3: Delivery Order (ship goods, deduct inventory) ---
        $doService = app(DeliveryOrderService::class);

        $deliveryOrder = $doService->createFromInvoice($invoice, [
            'warehouse_id' => $warehouse->id,
        ]);
        expect($deliveryOrder->items)->toHaveCount(2);

        $deliveryOrder = $doService->confirm($deliveryOrder, $this->user->id);
        expect($deliveryOrder->status)->toBe(DocumentStatus::Confirmed);

        $deliveryOrder = $doService->ship($deliveryOrder, [], $this->user->id);
        expect($deliveryOrder->status)->toBe(DocumentStatus::Shipped);

        // Assert: ProductStock decreased
        $stockA = ProductStock::where('product_id', $productA->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $stockB = ProductStock::where('product_id', $productB->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stockA->quantity)->toBe(95)   // 100 - 5
            ->and($stockB->quantity)->toBe(30); // 50 - 20

        // Assert: InventoryMovements created (TYPE_OUT)
        $movementsOut = InventoryMovement::where('reference_type', $deliveryOrder::class)
            ->where('reference_id', $deliveryOrder->id)
            ->where('type', InventoryMovement::TYPE_OUT)
            ->get();
        expect($movementsOut)->toHaveCount(2);

        // --- Phase 4: Pay the invoice ---
        $paymentService = app(PaymentService::class);

        $payment = $paymentService->createForInvoice($invoice->fresh(), [
            'amount' => $invoice->total_amount,
            'payment_date' => now()->toDateString(),
            'cash_account_id' => $accounts['1-1002']->id,
            'payment_method' => Payment::METHOD_TRANSFER,
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe($invoice->total_amount)
            ->and($invoice->status)->toBe(DocumentStatus::Paid);

        // Assert: Payment journal entry (DR Bank / CR AR)
        expect($payment->journal_entry_id)->not->toBeNull();
        $paymentJournal = $payment->journalEntry->load('lines.account');
        $payDebit = $paymentJournal->lines->sum('debit');
        $payCredit = $paymentJournal->lines->sum('credit');
        expect($payDebit)->toBe($payCredit)
            ->and($payDebit)->toBeGreaterThan(0);

        $payAccountCodes = $paymentJournal->lines->pluck('account.code')->sort()->values()->all();
        expect($payAccountCodes)->toContain('1-1002')  // Bank debited
            ->and($payAccountCodes)->toContain('1-1100'); // AR credited

        // --- Phase 5: Final accounting state assertions ---
        // After full sale-to-collection cycle, verify net balances across ALL journals.

        // AR (1-1100, asset/debit-normal): DR on invoice post, CR on payment → net 0
        $accounts['1-1100']->refresh();
        expect($accounts['1-1100']->getBalance())->toBe(0, 'AR must zero out after full payment');

        // Bank (1-1002, asset/debit-normal): only DR on payment → positive balance (cash inflow)
        $accounts['1-1002']->refresh();
        expect($accounts['1-1002']->getBalance())->toBe($invoice->total_amount, 'Bank must reflect cash inflow');

        // Revenue (4-1001, revenue/credit-normal): credited on invoice post → positive balance
        $accounts['4-1001']->refresh();
        expect($accounts['4-1001']->getBalance())->toBeGreaterThan(0, 'Revenue must be positive after sale');

        // Global trial balance: sum of all debits must equal sum of all credits
        $totalDebits = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->sum('journal_entry_lines.debit');
        $totalCredits = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.is_posted', true)
            ->sum('journal_entry_lines.credit');
        expect($totalDebits)->toBe($totalCredits, 'Trial balance must hold: total debits = total credits')
            ->and($totalDebits)->toBeGreaterThan(0);
    });
});

describe('Complete Manufacturing Cycle: BOM → WO → MR → Completion', function () {
    test('full manufacturing workflow with material requisition and inventory consumption', function () {
        $warehouse = Warehouse::factory()->create();

        // Raw materials
        $rawA = Product::factory()->create([
            'name' => 'Raw Material A',
            'track_inventory' => true,
            'purchase_price' => 10000,
        ]);
        $rawB = Product::factory()->create([
            'name' => 'Raw Material B',
            'track_inventory' => true,
            'purchase_price' => 2000,
        ]);

        // Finished product
        $finished = Product::factory()->create([
            'name' => 'Finished Product',
            'track_inventory' => true,
            'purchase_price' => 50000,
        ]);

        // Seed stock for raw materials
        ProductStock::factory()->create([
            'product_id' => $rawA->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 10000,
            'total_value' => 1000000,
        ]);
        ProductStock::factory()->create([
            'product_id' => $rawB->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 500,
            'reserved_quantity' => 0,
            'average_cost' => 2000,
            'total_value' => 1000000,
        ]);

        // --- Phase 1: Create BOM ---
        $bom = Bom::factory()->active()->forProduct($finished)->create([
            'output_quantity' => 1,
        ]);

        BomItem::factory()->material()->create([
            'bom_id' => $bom->id,
            'product_id' => $rawA->id,
            'description' => $rawA->name,
            'quantity' => 2,
            'unit_cost' => 10000,
            'total_cost' => 20000,
            'waste_percentage' => 0,
        ]);
        BomItem::factory()->material()->create([
            'bom_id' => $bom->id,
            'product_id' => $rawB->id,
            'description' => $rawB->name,
            'quantity' => 5,
            'unit_cost' => 2000,
            'total_cost' => 10000,
            'waste_percentage' => 0,
        ]);

        // --- Phase 2: Create Work Order from BOM (produce 3 units) ---
        $woService = app(WorkOrderService::class);

        $wo = $woService->createFromBom($bom, [
            'quantity' => 3,
            'warehouse_id' => $warehouse->id,
        ]);

        expect($wo)->toBeInstanceOf(WorkOrder::class)
            ->and($wo->status)->toBe(DocumentStatus::Draft)
            ->and($wo->product_id)->toBe($finished->id)
            ->and($wo->warehouse_id)->toBe($warehouse->id);

        // Assert: WO items populated from BOM (quantities scaled by WO quantity)
        $woMaterials = $wo->materialItems;
        expect($woMaterials)->toHaveCount(2);

        $woItemA = $woMaterials->firstWhere('product_id', $rawA->id);
        $woItemB = $woMaterials->firstWhere('product_id', $rawB->id);
        expect((float) $woItemA->quantity_required)->toBe(6.0)  // 2 per unit * 3 units
            ->and((float) $woItemB->quantity_required)->toBe(15.0); // 5 per unit * 3 units

        // --- Phase 3: Material Requisition ---
        $mrService = app(MaterialRequisitionService::class);

        $mr = $mrService->create($wo);
        expect($mr->items)->toHaveCount(2);

        // Assert: MR items match WO material items
        $mrItemA = $mr->items->firstWhere('product_id', $rawA->id);
        $mrItemB = $mr->items->firstWhere('product_id', $rawB->id);
        expect((float) $mrItemA->quantity_requested)->toBe(6.0)
            ->and((float) $mrItemB->quantity_requested)->toBe(15.0);

        $mr = $mrService->approve($mr, $this->user->id);
        expect($mr->status)->toBe(DocumentStatus::Approved);

        // Issue all materials
        $mr->refresh();
        $mrItemA = $mr->items->firstWhere('product_id', $rawA->id);
        $mrItemB = $mr->items->firstWhere('product_id', $rawB->id);

        $mr = $mrService->issue($mr, [
            ['item_id' => $mrItemA->id, 'quantity' => 6],
            ['item_id' => $mrItemB->id, 'quantity' => 15],
        ], $this->user->id);

        expect($mr->status)->toBe(DocumentStatus::Issued);

        // --- Phase 4: Work Order lifecycle (confirm → start → complete) ---
        $wo = $woService->confirm($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Confirmed);

        // Assert: reserved_quantity increased
        $stockA = ProductStock::where('product_id', $rawA->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $stockB = ProductStock::where('product_id', $rawB->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stockA->reserved_quantity)->toBe(6)
            ->and($stockB->reserved_quantity)->toBe(15);

        $wo = $woService->start($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::InProgress);

        $wo = $woService->complete($wo, $this->user->id);
        expect($wo->status)->toBe(DocumentStatus::Completed);

        // Assert: Stock consumed (quantity decreased, reserved back to 0)
        $stockA->refresh();
        $stockB->refresh();
        expect($stockA->quantity)->toBe(94)       // 100 - 6
            ->and($stockA->reserved_quantity)->toBe(0)
            ->and($stockB->quantity)->toBe(485)    // 500 - 15
            ->and($stockB->reserved_quantity)->toBe(0);

        // Assert: InventoryMovement records created
        $movements = InventoryMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $wo->id)
            ->get();
        expect($movements)->toHaveCount(2);

        $movementA = $movements->firstWhere('product_id', $rawA->id);
        $movementB = $movements->firstWhere('product_id', $rawB->id);
        expect($movementA->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movementA->quantity)->toBe(6)
            ->and($movementB->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movementB->quantity)->toBe(15);
    });
});
