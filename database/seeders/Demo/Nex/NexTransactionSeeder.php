<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Nex;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Purchasing\GoodsReceiptNoteServiceInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Purchasing\PurchaseReturnServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Domain\Sales\Quotations\Enums\QuotationType;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationVariantOption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds realistic business transactions for PT NEX (Solar EPC) demo.
 *
 * Uses the service layer to create complete business flows including:
 * - Journal entries for accounting reports
 * - Inventory movements for stock tracking
 * - Milestone payment patterns typical in solar industry
 * - O&M service billing
 */
class NexTransactionSeeder extends Seeder
{
    private static int $quotationSeq = 100;

    private QuotationServiceInterface $quotationService;

    private InvoiceServiceInterface $invoiceService;

    private PaymentServiceInterface $paymentService;

    private PurchaseOrderServiceInterface $purchaseOrderService;

    private GoodsReceiptNoteServiceInterface $grnService;

    private BillServiceInterface $billService;

    private PurchaseReturnServiceInterface $purchaseReturnService;

    private ?User $adminUser = null;

    private ?User $salesUser = null;

    private ?User $purchasingUser = null;

    private ?Warehouse $warehouse = null;

    private ?Account $bankAccount = null;

    private ?Account $cashAccount = null;

    /**
     * Base date for seeding (start of month simulation).
     */
    private Carbon $baseDate;

    /**
     * Seed transactions for PT NEX - Solar EPC business cycle demo.
     */
    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        // Set base date to start of current month for realistic data
        $this->baseDate = now()->startOfMonth();

        $this->command->info('Seeding NEX solar business transactions with journal entries...');

        // Authenticate as admin for seeding
        Auth::login($this->adminUser);

        try {
            // Multi-option quotation (demo showcase)
            $this->createMultiOptionQuotation();

            // Service-based transactions with journal entries
            $this->seedCompleteSolarSalesCycles();
            $this->seedServiceInvoices();
            $this->seedEquipmentPurchaseCycles();
            $this->seedPurchaseReturns();
        } finally {
            Auth::logout();
        }

        $this->command->info('NEX transaction seeding completed!');
        $this->outputSummary();
    }

    /**
     * Initialize services via service container.
     */
    private function initializeServices(): void
    {
        $this->quotationService = app(QuotationServiceInterface::class);
        $this->invoiceService = app(InvoiceServiceInterface::class);
        $this->paymentService = app(PaymentServiceInterface::class);
        $this->purchaseOrderService = app(PurchaseOrderServiceInterface::class);
        $this->grnService = app(GoodsReceiptNoteServiceInterface::class);
        $this->billService = app(BillServiceInterface::class);
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
        $this->cashAccount = Account::where('code', '1-1001')->first(); // Kas

        if (! $this->adminUser || ! $this->warehouse || ! $this->bankAccount) {
            throw new \RuntimeException('Required dependencies not found. Ensure UserSeeder, WarehouseSeeder, and AccountSeeder have run.');
        }
    }

    /**
     * Create a multi-option quotation showcasing Budget/Standard/Premium variants.
     */
    private function createMultiOptionQuotation(): void
    {
        $this->command->info('Creating multi-option quotation demo...');

        $customer = Contact::where('type', Contact::TYPE_CUSTOMER)
            ->where('code', 'C-RES-BSD')
            ->first();

        if (! $customer) {
            $customer = Contact::where('type', Contact::TYPE_CUSTOMER)->first();
        }

        // Find the PLTS 50 kWp variant group
        $variantGroup = BomVariantGroup::where('name', 'PLTS 50 kWp Material Options')->first();
        if (! $variantGroup) {
            $this->command->warn('  BomVariantGroup not found, skipping multi-option quotation');

            return;
        }

        // Get the BOMs in this variant group
        $boms = Bom::where('variant_group_id', $variantGroup->id)
            ->orderBy('variant_sort_order')
            ->get();

        if ($boms->count() < 2) {
            $this->command->warn('  Not enough BOMs in variant group, skipping multi-option quotation');

            return;
        }

        self::$quotationSeq++;
        $quotationNumber = 'QUO-'.now()->format('Ym').'-'.str_pad((string) self::$quotationSeq, 4, '0', STR_PAD_LEFT);

        // Create the multi-option quotation
        $quotation = Quotation::updateOrCreate(
            ['quotation_number' => $quotationNumber, 'revision' => 0],
            [
                'revision' => 0,
                'contact_id' => $customer->id,
                'quotation_date' => now()->subDays(5),
                'valid_until' => now()->addDays(30),
                'subject' => 'PLTS Rooftop 50 kWp - Pilihan Konfigurasi Material',
                'reference' => 'RFQ-BSD-2024-001',
                'quotation_type' => QuotationType::MultiOption->value,
                'variant_group_id' => $variantGroup->id,
                'status' => DocumentStatus::Submitted,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'subtotal' => 0,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax_rate' => 11.0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'base_currency_total' => 0,
                'notes' => 'Penawaran dengan 3 pilihan konfigurasi material. Silakan pilih sesuai dengan kebutuhan dan budget Anda.',
                'terms_conditions' => Quotation::getDefaultTermsConditions(),
                'created_by' => $this->salesUser?->id,
                'assigned_to' => $this->salesUser?->id,
                'submitted_at' => now()->subDays(3),
                'submitted_by' => $this->salesUser?->id,
                'priority' => 'high',
                'next_follow_up_at' => now()->addDays(2),
            ]
        );

        // Delete existing variant options and recreate
        QuotationVariantOption::where('quotation_id', $quotation->id)->delete();

        // Create variant options for each BOM
        $variantConfigs = $this->getVariantConfigs();

        $sortOrder = 0;
        foreach ($boms as $bom) {
            $variantName = $bom->variant_name;
            $config = $variantConfigs[$variantName] ?? null;

            if (! $config) {
                continue;
            }

            // Calculate selling price based on BOM cost + margin
            $bomCost = $bom->total_cost > 0 ? $bom->total_cost : ($sortOrder + 1) * 350000000;
            $margin = $config['margin_percent'] / 100;
            $sellingPrice = (int) round($bomCost * (1 + $margin));

            QuotationVariantOption::create([
                'quotation_id' => $quotation->id,
                'bom_id' => $bom->id,
                'display_name' => $config['display_name'],
                'tagline' => $config['tagline'],
                'is_recommended' => $config['is_recommended'],
                'selling_price' => $sellingPrice,
                'features' => $config['features'],
                'specifications' => $config['specifications'],
                'warranty_terms' => $config['warranty_terms'],
                'sort_order' => $sortOrder++,
            ]);
        }

        $this->command->info("  Created multi-option quotation: {$quotationNumber} with {$boms->count()} variants");
    }

    /**
     * Get variant configuration for multi-option quotations.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getVariantConfigs(): array
    {
        return [
            'Budget' => [
                'display_name' => 'Paket Hemat',
                'tagline' => 'Solusi ekonomis untuk memulai energi hijau',
                'is_recommended' => false,
                'margin_percent' => 15,
                'features' => [
                    'Garansi Inverter 5 Tahun',
                    'Garansi Panel 10 Tahun',
                    'Instalasi Standar',
                    'Support Teknis 1 Tahun',
                ],
                'specifications' => [
                    'inverter' => 'Growatt 10kW x 5 unit',
                    'panel' => 'NUSA Poly 550Wp x 91 unit',
                    'efficiency' => '~17%',
                    'warranty' => '5 Tahun Inverter, 10 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 10 tahun, inverter 5 tahun',
            ],
            'Standard' => [
                'display_name' => 'Paket Standar',
                'tagline' => 'Keseimbangan performa dan nilai investasi',
                'is_recommended' => true,
                'margin_percent' => 20,
                'features' => [
                    'Garansi Inverter 10 Tahun',
                    'Garansi Panel 12 Tahun',
                    'Instalasi Premium',
                    'Support Teknis 2 Tahun',
                    'Monitoring System',
                ],
                'specifications' => [
                    'inverter' => 'Huawei SUN2000 10kW x 5 unit',
                    'panel' => 'NUSA Mono 550Wp x 91 unit',
                    'efficiency' => '~19%',
                    'warranty' => '10 Tahun Inverter, 12 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 12 tahun, inverter 10 tahun, performance warranty 25 tahun',
            ],
            'Premium' => [
                'display_name' => 'Paket Premium',
                'tagline' => 'Performa maksimal untuk hasil optimal',
                'is_recommended' => false,
                'margin_percent' => 25,
                'features' => [
                    'Garansi Inverter 15 Tahun',
                    'Garansi Panel 15 Tahun',
                    'Instalasi Premium Plus',
                    'Support Teknis 3 Tahun',
                    'Monitoring System Advanced',
                    'Maintenance Gratis 1 Tahun',
                    'Performance Guarantee 90%',
                ],
                'specifications' => [
                    'inverter' => 'SMA Sunny Tripower 10kW x 5 unit',
                    'panel' => 'LONGi Bifacial 550Wp x 91 unit',
                    'efficiency' => '~21%',
                    'warranty' => '15 Tahun Inverter, 15 Tahun Panel',
                ],
                'warranty_terms' => 'Garansi panel 15 tahun, inverter 15 tahun, performance warranty 30 tahun (90%)',
            ],
        ];
    }

    /**
     * Scenario 1: Complete Solar Sales Cycles with Milestone Payments.
     *
     * Solar projects typically have milestone payments:
     * - 30% Down Payment on order confirmation
     * - 50% on material delivery
     * - 20% on commissioning completion
     */
    private function seedCompleteSolarSalesCycles(): void
    {
        $this->command->info('Creating complete solar sales cycles with milestone payments...');

        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();

        // Sale 1: Japfa (Agricultural) - 10 kWp system with 3 milestone payments
        $this->createCompleteSolarSaleCycle(
            customer: $customers->firstWhere('code', 'C-AGR-JFA'),
            products: [
                ['sku' => 'FG-PLTS-10KWP', 'qty' => 1, 'desc' => 'Sistem PLTS On-Grid 10 kWp untuk Cold Storage'],
                ['sku' => 'SVC-SURVEY', 'qty' => 1, 'desc' => 'Survey & Assessment Site'],
            ],
            subject: 'PLTS Rooftop 10 kWp - Cold Storage Japfa Bogor',
            milestonePayments: [30, 50, 20],
            dayOffsets: ['quotation' => 1, 'approve' => 3, 'invoice' => 5, 'payment1' => 8, 'payment2' => 18, 'payment3' => 28]
        );

        // Sale 2: Charoen Pokphand (Industrial) - 50 kWp system with 2 payments
        $this->createCompleteSolarSaleCycle(
            customer: $customers->firstWhere('code', 'C-IND-CRG'),
            products: [
                ['sku' => 'FG-PLTS-50KWP', 'qty' => 1, 'desc' => 'Sistem PLTS On-Grid 50 kWp Factory Rooftop'],
                ['sku' => 'SVC-DESIGN', 'qty' => 1, 'desc' => 'Desain Sistem PV & Engineering'],
            ],
            subject: 'PLTS Rooftop 50 kWp - Pabrik Charoen Pokphand',
            milestonePayments: [50, 50],
            dayOffsets: ['quotation' => 2, 'approve' => 5, 'invoice' => 7, 'payment1' => 10, 'payment2' => 25]
        );

        $this->command->info('  Created 2 complete solar sales cycles with milestone payments');
    }

    /**
     * Create a complete solar sales cycle with milestone payments.
     *
     * @param  array<int, array{sku: string, qty: int, desc: string}>  $products
     * @param  array<int, int>  $milestonePayments
     * @param  array<string, int>  $dayOffsets
     */
    private function createCompleteSolarSaleCycle(
        ?Contact $customer,
        array $products,
        string $subject,
        array $milestonePayments,
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

        // Step 4: Create Milestone Payments (solar projects don't ship, they install on-site)
        foreach ($milestonePayments as $index => $percent) {
            $paymentKey = 'payment'.($index + 1);
            if (! isset($dayOffsets[$paymentKey])) {
                continue;
            }

            Carbon::setTestNow($this->baseDate->copy()->addDays($dayOffsets[$paymentKey]));

            $amount = (int) round($invoice->total_amount * ($percent / 100));
            $milestoneNames = ['DP (Down Payment)', 'Delivery/Progress', 'Final/Commissioning'];
            $milestoneName = $milestoneNames[$index] ?? 'Payment '.($index + 1);

            $this->paymentService->createForInvoice($invoice->fresh(), [
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'cash_account_id' => $this->bankAccount->id,
                'reference' => 'TRF-'.strtoupper(substr($customer->code, 2)).'-'.now()->format('Ymd'),
                'notes' => "{$milestoneName} ({$percent}%) - Invoice {$invoice->invoice_number}",
            ]);
        }

        Carbon::setTestNow(null);
    }

    /**
     * Scenario 2: Service Invoices for O&M Contracts.
     *
     * Solar EPC companies often have recurring O&M (Operations & Maintenance) contracts.
     */
    private function seedServiceInvoices(): void
    {
        $this->command->info('Creating O&M service invoices...');

        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();

        // O&M Service 1: Indofood - Annual O&M Contract
        $this->createServiceInvoice(
            customer: $customers->firstWhere('code', 'C-IND-IDF'),
            products: [
                ['sku' => 'SVC-OM-YR', 'qty' => 1, 'desc' => 'Kontrak O&M Tahunan PLTS 100 kWp'],
                ['sku' => 'SVC-CLEAN-PKG', 'qty' => 100, 'desc' => 'Panel Cleaning per kWp (4x/tahun)'],
            ],
            dayOffsets: ['invoice' => 5, 'payment' => 15]
        );

        // O&M Service 2: Matahari - Quarterly Cleaning
        $this->createServiceInvoice(
            customer: $customers->firstWhere('code', 'C-COM-MKL'),
            products: [
                ['sku' => 'SVC-CLEAN-PKG', 'qty' => 30, 'desc' => 'Panel Cleaning PLTS 30 kWp - Q1 2025'],
                ['sku' => 'SVC-OM-MTH', 'qty' => 3, 'desc' => 'Monitoring & Support Bulanan (Jan-Mar)'],
            ],
            dayOffsets: ['invoice' => 8, 'payment' => 20]
        );

        $this->command->info('  Created 2 O&M service invoices');
    }

    /**
     * Create a service invoice with payment.
     *
     * @param  array<int, array{sku: string, qty: int, desc: string}>  $products
     * @param  array<string, int>  $dayOffsets
     */
    private function createServiceInvoice(
        ?Contact $customer,
        array $products,
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

        // Full payment
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
     * Scenario 3: Complete Equipment Purchase Cycles (PO → GRN → Bill → Payment).
     *
     * Solar EPC companies purchase PV modules, inverters, and other equipment from suppliers.
     */
    private function seedEquipmentPurchaseCycles(): void
    {
        $this->command->info('Creating equipment purchase cycles...');

        $suppliers = Contact::where('type', Contact::TYPE_SUPPLIER)
            ->where('is_subcontractor', false)
            ->get();

        // Purchase 1: JA Solar - PV Modules
        $this->createCompletePurchaseCycle(
            supplier: $suppliers->firstWhere('code', 'S-PV-JAS'),
            products: [
                ['sku' => 'PV-JA-545', 'qty' => 100, 'desc' => 'Panel Surya JA Solar 545Wp'],
            ],
            dayOffsets: ['po' => 1, 'approve' => 2, 'grn' => 8, 'bill' => 9, 'payment' => 25]
        );

        // Purchase 2: Huawei - Inverters
        $this->createCompletePurchaseCycle(
            supplier: $suppliers->firstWhere('code', 'S-INV-HUA'),
            products: [
                ['sku' => 'INV-HW-10K', 'qty' => 5, 'desc' => 'Inverter Huawei 10kW'],
                ['sku' => 'INV-HW-50K', 'qty' => 2, 'desc' => 'Inverter Huawei 50kW'],
                ['sku' => 'MON-DONGLE', 'qty' => 7, 'desc' => 'Smart Dongle WiFi/4G'],
            ],
            dayOffsets: ['po' => 2, 'approve' => 3, 'grn' => 10, 'bill' => 11, 'payment' => 28]
        );

        $this->command->info('  Created 2 equipment purchase cycles');
    }

    /**
     * Create a complete purchase cycle using services.
     *
     * @param  array<int, array{sku: string, qty: int, desc?: string}>  $products
     * @param  array<string, int>  $dayOffsets
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
            'notes' => 'Mohon konfirmasi lead time dan pengiriman',
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
     * Scenario 4: Purchase Returns for Defective Equipment.
     *
     * Solar equipment occasionally has defects discovered during QC or installation.
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
            'reason' => 'Panel dengan micro-crack terdeteksi pada QC/EL test',
            'created_by' => $this->purchasingUser->id,
        ]);

        // Return small quantity (defective units only)
        foreach ($purchaseReturn->items as $item) {
            $returnQty = max(1, (int) floor($item->quantity / 10)); // ~10% defect rate
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

        $this->command->info('  Created 1 purchase return for defective equipment');
    }

    /**
     * Build items array for quotations.
     *
     * @param  array<int, array{sku: string, qty: int, desc?: string}>  $products
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
     * @param  array<int, array{sku: string, qty: int, desc?: string}>  $products
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
     * @param  array<int, array{sku: string, qty: int, desc?: string}>  $products
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
                'description' => $productData['desc'] ?? $product->name,
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
        $this->command->info('=== NEX Transaction Summary ===');
        $this->command->info("Journal Entries: {$journalCount}");
        $this->command->info("Inventory Movements: {$inventoryCount}");
        $this->command->info("Posted Invoices: {$invoiceCount}");
        $this->command->info("Posted Bills: {$billCount}");
    }
}
