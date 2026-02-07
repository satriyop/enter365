<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Vahana;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Purchasing\GoodsReceiptNoteServiceInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Purchasing\PurchaseReturnServiceInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds realistic business transactions for PT Vahana demo.
 *
 * Uses the service layer to create complete business flows including:
 * - Journal entries for accounting reports
 * - Inventory movements for stock tracking
 * - Proper document state transitions
 */
class VahanaTransactionSeeder extends Seeder
{
    private QuotationServiceInterface $quotationService;

    private InvoiceServiceInterface $invoiceService;

    private DeliveryOrderServiceInterface $deliveryOrderService;

    private PaymentServiceInterface $paymentService;

    private PurchaseOrderServiceInterface $purchaseOrderService;

    private GoodsReceiptNoteServiceInterface $grnService;

    private BillServiceInterface $billService;

    private SalesReturnServiceInterface $salesReturnService;

    private PurchaseReturnServiceInterface $purchaseReturnService;

    private ?User $adminUser = null;

    private ?User $salesUser = null;

    private ?User $purchasingUser = null;

    private ?Warehouse $warehouse = null;

    private ?Account $bankAccount = null;

    /**
     * Base date for seeding (start of month simulation).
     */
    private Carbon $baseDate;

    /**
     * Seed transactions for PT Vahana - full business cycle demo.
     */
    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        // Set base date to start of current month for realistic data
        $this->baseDate = now()->startOfMonth();

        $this->command->info('Seeding complete business transactions with journal entries...');

        // Authenticate as admin for seeding
        Auth::login($this->adminUser);

        try {
            $this->seedCompleteSalesCycles();
            $this->seedDirectInvoicesWithPartialPayments();
            $this->seedCompletePurchaseCycles();
            $this->seedSalesReturns();
            $this->seedPurchaseReturns();
        } finally {
            Auth::logout();
        }

        $this->command->info('Transaction seeding completed!');
        $this->outputSummary();
    }

    /**
     * Initialize services via service container.
     */
    private function initializeServices(): void
    {
        $this->quotationService = app(QuotationServiceInterface::class);
        $this->invoiceService = app(InvoiceServiceInterface::class);
        $this->deliveryOrderService = app(DeliveryOrderServiceInterface::class);
        $this->paymentService = app(PaymentServiceInterface::class);
        $this->purchaseOrderService = app(PurchaseOrderServiceInterface::class);
        $this->grnService = app(GoodsReceiptNoteServiceInterface::class);
        $this->billService = app(BillServiceInterface::class);
        $this->salesReturnService = app(SalesReturnServiceInterface::class);
        $this->purchaseReturnService = app(PurchaseReturnServiceInterface::class);
    }

    /**
     * Load required dependencies from existing seeders.
     */
    private function loadDependencies(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first();
        $this->salesUser = User::where('email', 'sales@demo.com')->first();
        $this->purchasingUser = User::where('email', 'purchasing@demo.com')->first();
        $this->warehouse = Warehouse::where('is_default', true)->first();
        $this->bankAccount = Account::where('code', '1-1010')->first(); // Bank BCA

        if (! $this->adminUser || ! $this->warehouse || ! $this->bankAccount) {
            throw new \RuntimeException('Required dependencies not found. Ensure UserSeeder, WarehouseSeeder, and AccountSeeder have run.');
        }
    }

    /**
     * Scenario 1: Complete Sales Cycles (Quotation → Invoice → Delivery → Payment).
     *
     * Creates 3 complete sales transactions with full journal entries.
     */
    private function seedCompleteSalesCycles(): void
    {
        $this->command->info('Creating complete sales cycles...');

        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();

        // Sale 1: PLN JKT - Large order (LVMDP 250A)
        $this->createCompleteSaleCycle(
            customer: $customers->firstWhere('code', 'C-PLN-JKT'),
            products: [
                ['sku' => 'FG-LVMDP-250', 'qty' => 2, 'desc' => 'Panel LVMDP 250A untuk GI Cawang'],
                ['sku' => 'SVC-INS-PANEL', 'qty' => 2, 'desc' => 'Jasa Instalasi'],
            ],
            subject: 'Pengadaan Panel LVMDP untuk Gardu Induk Cawang',
            dayOffsets: ['quotation' => 1, 'approve' => 3, 'invoice' => 5, 'delivery' => 7, 'payment' => 10]
        );

        // Sale 2: Krakatau Steel - Medium order (MCC panels)
        $this->createCompleteSaleCycle(
            customer: $customers->firstWhere('code', 'C-KRK'),
            products: [
                ['sku' => 'FG-MCC-DOL-25', 'qty' => 3, 'desc' => 'Panel MCC DOL 25A untuk conveyor'],
                ['sku' => 'FG-LVMDP-100', 'qty' => 1, 'desc' => 'Panel LVMDP 100A main'],
            ],
            subject: 'Panel MCC untuk Plant Expansion',
            dayOffsets: ['quotation' => 2, 'approve' => 4, 'invoice' => 6, 'delivery' => 9, 'payment' => 14]
        );

        // Sale 3: Astra - DB panels
        $this->createCompleteSaleCycle(
            customer: $customers->firstWhere('code', 'C-AST'),
            products: [
                ['sku' => 'FG-DB-8W', 'qty' => 10, 'desc' => 'Distribution Board 8 Way untuk workshop'],
                ['sku' => 'SVC-INS-PANEL', 'qty' => 10, 'desc' => 'Jasa Instalasi'],
            ],
            subject: 'Panel DB untuk Workshop Sunter',
            dayOffsets: ['quotation' => 3, 'approve' => 5, 'invoice' => 7, 'delivery' => 10, 'payment' => 15]
        );

        $this->command->info('  Created 3 complete sales cycles');
    }

    /**
     * Create a complete sales cycle using services.
     */
    private function createCompleteSaleCycle(
        ?Contact $customer,
        array $products,
        string $subject,
        array $dayOffsets
    ): void {
        if (! $customer) {
            return;
        }

        // Build items array
        $items = $this->buildSalesItems($products);
        if (empty($items)) {
            return;
        }

        // Step 1: Create Quotation
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['quotation']));

        $quotation = $this->quotationService->create([
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subject' => $subject,
            'items' => $items,
        ], $this->salesUser);

        // Step 2: Submit and Approve Quotation
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['approve']));

        $quotation = $this->quotationService->submit($quotation, $this->salesUser->id);
        $quotation = $this->quotationService->approve($quotation, $this->adminUser->id);

        // Step 3: Convert to Invoice and Post
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['invoice']));

        $invoice = $this->quotationService->convertToInvoice($quotation);
        $invoice = $this->invoiceService->post($invoice);

        // Step 4: Create Delivery Order and Ship
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['delivery']));

        $deliveryOrder = $this->deliveryOrderService->createFromInvoice($invoice, [
            'warehouse_id' => $this->warehouse->id,
            'do_date' => now()->toDateString(),
            'created_by' => $this->salesUser->id,
        ]);
        $deliveryOrder = $this->deliveryOrderService->confirm($deliveryOrder, $this->salesUser->id);
        $this->deliveryOrderService->ship($deliveryOrder, [], $this->salesUser->id);

        // Step 5: Receive Full Payment
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['payment']));

        $this->paymentService->createForInvoice($invoice, [
            'payment_date' => now()->toDateString(),
            'amount' => $invoice->total_amount,
            'cash_account_id' => $this->bankAccount->id,
            'reference' => 'TRF-'.strtoupper(substr($customer->code, 2)).'-'.now()->format('Ymd'),
            'notes' => 'Pembayaran Invoice '.$invoice->invoice_number,
        ]);

        Carbon::setTestNow(null);
    }

    /**
     * Scenario 2: Direct Invoices with Partial Payments.
     *
     * Creates 2 invoices directly (without quotations) with multiple payments.
     */
    private function seedDirectInvoicesWithPartialPayments(): void
    {
        $this->command->info('Creating invoices with partial payments...');

        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();

        // Invoice 1: PP Construction - 50% payment received
        $this->createInvoiceWithPartialPayment(
            customer: $customers->firstWhere('code', 'C-PP'),
            products: [
                ['sku' => 'FG-ATS-100', 'qty' => 3, 'desc' => 'Panel ATS 100A untuk rest area'],
            ],
            paymentPercents: [50],
            dayOffsets: ['invoice' => 5, 'payment1' => 12]
        );

        // Invoice 2: Trias Sentosa - Two partial payments (30%, 40%)
        $this->createInvoiceWithPartialPayment(
            customer: $customers->firstWhere('code', 'C-TRIA'),
            products: [
                ['sku' => 'FG-MCC-DOL-25', 'qty' => 2, 'desc' => 'Panel MCC DOL 25A'],
                ['sku' => 'FG-DB-8W', 'qty' => 5, 'desc' => 'Distribution Board 8 Way'],
            ],
            paymentPercents: [30, 40],
            dayOffsets: ['invoice' => 6, 'payment1' => 15, 'payment2' => 22]
        );

        $this->command->info('  Created 2 invoices with partial payments');
    }

    /**
     * Create an invoice with partial payments.
     */
    private function createInvoiceWithPartialPayment(
        ?Contact $customer,
        array $products,
        array $paymentPercents,
        array $dayOffsets
    ): void {
        if (! $customer) {
            return;
        }

        $items = $this->buildInvoiceItems($products);
        if (empty($items)) {
            return;
        }

        // Create and post invoice
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['invoice']));

        $invoice = $this->invoiceService->create([
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays($customer->payment_term_days ?? 30)->toDateString(),
            'items' => $items,
        ]);
        $invoice = $this->invoiceService->post($invoice);

        // Create partial payments
        foreach ($paymentPercents as $index => $percent) {
            $paymentKey = 'payment'.($index + 1);
            if (! isset($dayOffsets[$paymentKey])) {
                continue;
            }

            Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets[$paymentKey]));

            $amount = (int) round($invoice->total_amount * ($percent / 100));

            $this->paymentService->createForInvoice($invoice->fresh(), [
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'cash_account_id' => $this->bankAccount->id,
                'reference' => 'TRF-'.strtoupper(substr($customer->code, 2)).'-'.now()->format('Ymd'),
                'notes' => "Pembayaran {$percent}% Invoice {$invoice->invoice_number}",
            ]);
        }

        Carbon::setTestNow(null);
    }

    /**
     * Scenario 3: Complete Purchase Cycles (PO → GRN → Bill → Payment).
     *
     * Creates 3 complete purchase transactions with inventory movements.
     */
    private function seedCompletePurchaseCycles(): void
    {
        $this->command->info('Creating complete purchase cycles...');

        $suppliers = Contact::where('type', Contact::TYPE_SUPPLIER)
            ->where('is_subcontractor', false)
            ->get();

        // Purchase 1: Schneider - Electrical components
        $this->createCompletePurchaseCycle(
            supplier: $suppliers->firstWhere('code', 'S-SCH'),
            products: [
                ['sku' => 'EL-MCCB-100', 'qty' => 5],
                ['sku' => 'EL-MCCB-250', 'qty' => 3],
                ['sku' => 'EL-CTR-25', 'qty' => 10],
            ],
            dayOffsets: ['po' => 1, 'approve' => 2, 'grn' => 6, 'bill' => 7, 'payment' => 20]
        );

        // Purchase 2: Supreme Cable - Cables
        $this->createCompletePurchaseCycle(
            supplier: $suppliers->firstWhere('code', 'S-SUM'),
            products: [
                ['sku' => 'CB-NYY-4X35', 'qty' => 200],
                ['sku' => 'CB-NYY-4X70', 'qty' => 100],
            ],
            dayOffsets: ['po' => 2, 'approve' => 3, 'grn' => 8, 'bill' => 9, 'payment' => 25]
        );

        // Purchase 3: Rittal - Enclosures
        $this->createCompletePurchaseCycle(
            supplier: $suppliers->firstWhere('code', 'S-RTL'),
            products: [
                ['sku' => 'EN-800X600', 'qty' => 5],
                ['sku' => 'EN-1000X800', 'qty' => 3],
            ],
            dayOffsets: ['po' => 3, 'approve' => 4, 'grn' => 10, 'bill' => 11, 'payment' => 28]
        );

        $this->command->info('  Created 3 complete purchase cycles');
    }

    /**
     * Create a complete purchase cycle using services.
     */
    private function createCompletePurchaseCycle(
        ?Contact $supplier,
        array $products,
        array $dayOffsets
    ): void {
        if (! $supplier) {
            return;
        }

        $items = $this->buildPurchaseItems($products);
        if (empty($items)) {
            return;
        }

        // Step 1: Create Purchase Order
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['po']));

        $po = $this->purchaseOrderService->create([
            'contact_id' => $supplier->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(14)->toDateString(),
            'items' => $items,
            'notes' => 'Mohon dikirim sesuai jadwal',
        ]);

        // Step 2: Submit and Approve PO
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['approve']));

        $po = $this->purchaseOrderService->submit($po, $this->purchasingUser->id);
        $po = $this->purchaseOrderService->approve($po, $this->adminUser->id);

        // Step 3: Create GRN and Complete (adds inventory)
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['grn']));

        $grn = $this->grnService->createFromPurchaseOrder($po, [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'supplier_do_number' => 'DO-'.$supplier->code.'-'.now()->format('Ymd'),
            'created_by' => $this->purchasingUser->id,
        ]);

        // Update received quantities on GRN items
        foreach ($grn->items as $item) {
            $this->grnService->updateItem($item, [
                'quantity_received' => $item->quantity_ordered,
            ]);
        }

        $grn = $this->grnService->complete($grn, $this->purchasingUser->id);

        // Step 4: Create Bill and Post
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['bill']));

        $bill = $this->billService->create([
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays($supplier->payment_term_days ?? 30)->toDateString(),
            'vendor_invoice_number' => 'INV-'.$supplier->code.'-'.now()->format('Ymd'),
            'items' => $this->buildBillItemsFromGrn($grn),
        ]);
        $bill = $this->billService->post($bill);

        // Step 5: Pay Bill
        Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets['payment']));

        $this->paymentService->createForBill($bill, [
            'payment_date' => now()->toDateString(),
            'amount' => $bill->total_amount,
            'cash_account_id' => $this->bankAccount->id,
            'reference' => 'PAY-'.strtoupper(substr($supplier->code, 2)).'-'.now()->format('Ymd'),
            'notes' => 'Pembayaran Bill '.$bill->bill_number,
        ]);

        Carbon::setTestNow(null);
    }

    /**
     * Scenario 5: Sales Returns.
     *
     * Creates 1 sales return against a posted invoice.
     */
    private function seedSalesReturns(): void
    {
        $this->command->info('Creating sales returns...');

        // Get a posted invoice to create return against
        $invoice = \App\Models\Sales\Invoice::where('status', \App\Enums\DocumentStatus::Partial)
            ->orWhere('status', \App\Enums\DocumentStatus::Paid)
            ->with('items')
            ->first();

        if (! $invoice) {
            $this->command->warn('  No posted invoice found for sales return');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(25));

        // Create sales return from invoice (copies all items)
        $salesReturn = $this->salesReturnService->createFromInvoice($invoice, [
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Barang tidak sesuai spesifikasi',
            'created_by' => $this->salesUser->id,
        ]);

        // Reduce quantities (return only partial)
        foreach ($salesReturn->items as $item) {
            $returnQty = max(1, (int) floor($item->quantity / 2));
            $item->quantity = $returnQty;
            $item->calculateLineTotal();
            $item->save();
        }
        $salesReturn->refresh();
        $salesReturn->calculateTotals();
        $salesReturn->save();

        // Submit and approve (triggers inventory and journal handlers)
        Carbon::setTestNow($this->baseDate->copy()->addDays(26));

        $salesReturn = $this->salesReturnService->submit($salesReturn, $this->salesUser->id);
        $this->salesReturnService->approve($salesReturn, $this->adminUser->id);

        Carbon::setTestNow(null);

        $this->command->info('  Created 1 sales return');
    }

    /**
     * Scenario 6: Purchase Returns.
     *
     * Creates 1 purchase return against a posted bill.
     */
    private function seedPurchaseReturns(): void
    {
        $this->command->info('Creating purchase returns...');

        // Get a posted bill to create return against
        $bill = \App\Models\Purchasing\Bill::where('status', \App\Enums\DocumentStatus::Partial)
            ->orWhere('status', \App\Enums\DocumentStatus::Paid)
            ->with('items')
            ->first();

        if (! $bill) {
            $this->command->warn('  No posted bill found for purchase return');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(28));

        // Create purchase return from bill
        $purchaseReturn = $this->purchaseReturnService->createFromBill($bill, [
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Barang rusak saat diterima',
            'created_by' => $this->purchasingUser->id,
        ]);

        // Reduce quantities (return only partial)
        foreach ($purchaseReturn->items as $item) {
            $returnQty = max(1, (int) floor($item->quantity / 3));
            $item->quantity = $returnQty;
            $item->calculateLineTotal();
            $item->save();
        }
        $purchaseReturn->refresh();
        $purchaseReturn->calculateTotals();
        $purchaseReturn->save();

        // Submit and approve (triggers inventory and journal handlers)
        Carbon::setTestNow($this->baseDate->copy()->addDays(29));

        $purchaseReturn = $this->purchaseReturnService->submit($purchaseReturn, $this->purchasingUser->id);
        $this->purchaseReturnService->approve($purchaseReturn, $this->adminUser->id);

        Carbon::setTestNow(null);

        $this->command->info('  Created 1 purchase return');
    }

    /**
     * Build items array for quotations/invoices.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSalesItems(array $products): array
    {
        $items = [];

        foreach ($products as $productData) {
            $product = Product::where('sku', $productData['sku'])->first();
            if (! $product) {
                continue;
            }

            $items[] = [
                'product_id' => $product->id,
                'description' => $productData['desc'] ?? $product->name,
                'quantity' => $productData['qty'],
                'unit' => $product->unit,
                'unit_price' => $product->selling_price,
                'tax_rate' => $product->tax_rate ?? 11,
            ];
        }

        return $items;
    }

    /**
     * Build items array for direct invoices.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildInvoiceItems(array $products): array
    {
        $items = [];

        foreach ($products as $productData) {
            $product = Product::where('sku', $productData['sku'])->first();
            if (! $product) {
                continue;
            }

            $items[] = [
                'product_id' => $product->id,
                'description' => $productData['desc'] ?? $product->name,
                'quantity' => $productData['qty'],
                'unit' => $product->unit,
                'unit_price' => $product->selling_price,
            ];
        }

        return $items;
    }

    /**
     * Build items array for purchase orders.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildPurchaseItems(array $products): array
    {
        $items = [];

        foreach ($products as $productData) {
            $product = Product::where('sku', $productData['sku'])->first();
            if (! $product) {
                continue;
            }

            $items[] = [
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $productData['qty'],
                'unit' => $product->unit,
                'unit_price' => $product->purchase_price,
                'tax_rate' => $product->tax_rate ?? 11,
            ];
        }

        return $items;
    }

    /**
     * Build bill items from completed GRN.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildBillItemsFromGrn(\App\Models\Purchasing\GoodsReceiptNote $grn): array
    {
        $items = [];

        foreach ($grn->items as $grnItem) {
            if ($grnItem->quantity_received <= 0) {
                continue;
            }

            $items[] = [
                'description' => $grnItem->product->name ?? 'Item',
                'quantity' => $grnItem->quantity_received,
                'unit' => $grnItem->product->unit ?? 'unit',
                'unit_price' => $grnItem->unit_price,
            ];
        }

        return $items;
    }

    /**
     * Output summary of seeded data.
     */
    private function outputSummary(): void
    {
        $journalCount = \App\Models\Accounting\JournalEntry::count();
        $inventoryCount = \App\Models\Inventory\InventoryMovement::count();
        $invoiceCount = \App\Models\Sales\Invoice::whereIn('status', [
            \App\Enums\DocumentStatus::Sent,
            \App\Enums\DocumentStatus::Partial,
            \App\Enums\DocumentStatus::Paid,
        ])->count();
        $billCount = \App\Models\Purchasing\Bill::whereIn('status', [
            \App\Enums\DocumentStatus::Received,
            \App\Enums\DocumentStatus::Partial,
            \App\Enums\DocumentStatus::Paid,
        ])->count();

        $this->command->newLine();
        $this->command->info('=== Transaction Summary ===');
        $this->command->info("Journal Entries: {$journalCount}");
        $this->command->info("Inventory Movements: {$inventoryCount}");
        $this->command->info("Posted Invoices: {$invoiceCount}");
        $this->command->info("Posted Bills: {$billCount}");
    }
}
