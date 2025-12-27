<?php

namespace Database\Seeders\Demo\Vahana;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bom;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Payment;
use App\Models\Accounting\Product;
use App\Models\Accounting\PurchaseOrder;
use App\Models\Accounting\PurchaseOrderItem;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationItem;
use App\Models\Accounting\Warehouse;
use App\Models\Accounting\WorkOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class VahanaTransactionSeeder extends Seeder
{
    private static int $quotationSeq = 0;

    private static int $invoiceSeq = 0;

    private static int $poSeq = 0;

    private static int $woSeq = 0;

    /**
     * Seed transactions for PT Vahana - full business cycle demo.
     */
    public function run(): void
    {
        $this->createQuotations();
        $this->createInvoicesWithPayments();
        $this->createPurchaseOrders();
        $this->createWorkOrders();
    }

    private function createQuotations(): void
    {
        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();
        $salesUser = User::where('email', 'sales@demo.com')->first();

        // Get finished goods
        $lvmdp100 = Product::where('sku', 'FG-LVMDP-100')->first();
        $lvmdp250 = Product::where('sku', 'FG-LVMDP-250')->first();
        $mccDol = Product::where('sku', 'FG-MCC-DOL-25')->first();
        $ats100 = Product::where('sku', 'FG-ATS-100')->first();
        $db8w = Product::where('sku', 'FG-DB-8W')->first();
        $svcInstall = Product::where('sku', 'SVC-INS-PANEL')->first();
        $svcTest = Product::where('sku', 'SVC-COM-TEST')->first();

        // Quotation 1: PLN - Large order - Won
        $plnJkt = $customers->firstWhere('code', 'C-PLN-JKT');
        if ($plnJkt && $lvmdp250) {
            $this->createQuotation(
                $plnJkt,
                'Pengadaan Panel LVMDP untuk Gardu Induk Cawang',
                [
                    ['product' => $lvmdp250, 'qty' => 3, 'desc' => 'Panel LVMDP 250A untuk GI Cawang'],
                    ['product' => $svcInstall, 'qty' => 3, 'desc' => 'Jasa Instalasi'],
                    ['product' => $svcTest, 'qty' => 3, 'desc' => 'Jasa Commissioning'],
                ],
                'approved',
                $salesUser,
                ['priority' => 'high', 'outcome' => 'won', 'won_reason' => 'harga_kompetitif']
            );
        }

        // Quotation 2: Krakatau Steel - Medium order - Approved
        $krakatau = $customers->firstWhere('code', 'C-KRK');
        if ($krakatau && $mccDol) {
            $this->createQuotation(
                $krakatau,
                'Panel MCC untuk Plant Expansion',
                [
                    ['product' => $mccDol, 'qty' => 5, 'desc' => 'Panel MCC DOL 25A untuk conveyor'],
                    ['product' => $lvmdp100, 'qty' => 1, 'desc' => 'Panel LVMDP 100A main'],
                    ['product' => $svcInstall, 'qty' => 6, 'desc' => 'Jasa Instalasi'],
                ],
                'approved',
                $salesUser,
                ['priority' => 'normal']
            );
        }

        // Quotation 3: Wijaya Karya - Submitted, pending
        $wika = $customers->firstWhere('code', 'C-WKA');
        if ($wika && $ats100) {
            $this->createQuotation(
                $wika,
                'Panel ATS untuk Proyek Tol Jakarta-Cikampek',
                [
                    ['product' => $ats100, 'qty' => 10, 'desc' => 'Panel ATS 100A untuk rest area'],
                    ['product' => $db8w, 'qty' => 20, 'desc' => 'Distribution Board 8 Way'],
                    ['product' => $svcInstall, 'qty' => 30, 'desc' => 'Jasa Instalasi'],
                ],
                'submitted',
                $salesUser,
                ['priority' => 'urgent', 'next_follow_up_at' => now()->addDays(3)]
            );
        }

        // Quotation 4: Astra - Draft
        $astra = $customers->firstWhere('code', 'C-AST');
        if ($astra && $lvmdp100) {
            $this->createQuotation(
                $astra,
                'Panel LVMDP untuk Workshop Sunter',
                [
                    ['product' => $lvmdp100, 'qty' => 2, 'desc' => 'Panel LVMDP 100A'],
                    ['product' => $mccDol, 'qty' => 8, 'desc' => 'Panel MCC DOL untuk mesin produksi'],
                ],
                'draft',
                $salesUser
            );
        }

        // Quotation 5: Summarecon - Lost
        $summarecon = $customers->firstWhere('code', 'C-SML');
        if ($summarecon && $db8w) {
            $this->createQuotation(
                $summarecon,
                'DB Panel untuk Apartment Tower B',
                [
                    ['product' => $db8w, 'qty' => 100, 'desc' => 'Distribution Board 8 Way per unit'],
                    ['product' => $svcInstall, 'qty' => 100, 'desc' => 'Jasa Instalasi'],
                ],
                'approved',
                $salesUser,
                ['outcome' => 'lost', 'lost_reason' => 'harga_tinggi', 'lost_to_competitor' => 'PT Panel Elektrik Indonesia']
            );
        }

        // Quotation 6: CV Elektrik Mandiri - Small, submitted
        $elmandiri = $customers->firstWhere('code', 'C-ELM');
        if ($elmandiri && $db8w) {
            $this->createQuotation(
                $elmandiri,
                'DB Panel untuk Ruko Bekasi',
                [
                    ['product' => $db8w, 'qty' => 5, 'desc' => 'Distribution Board 8 Way'],
                ],
                'submitted',
                $salesUser,
                ['priority' => 'low']
            );
        }

        $this->command->info('Created 6 quotations');
    }

    private function createQuotation(
        Contact $contact,
        string $subject,
        array $items,
        string $status,
        ?User $salesUser = null,
        array $options = []
    ): Quotation {
        self::$quotationSeq++;
        $quotationNumber = 'QUO-'.now()->format('Ym').'-'.str_pad(self::$quotationSeq, 4, '0', STR_PAD_LEFT);

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['product']->selling_price * $item['qty'];
        }

        $taxRate = 11.0;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        $quotationData = [
            'quotation_number' => $quotationNumber,
            'revision' => 0,
            'contact_id' => $contact->id,
            'quotation_date' => now()->subDays(rand(1, 30)),
            'valid_until' => now()->addDays(30),
            'subject' => $subject,
            'status' => Quotation::STATUS_DRAFT,
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'base_currency_total' => $total,
            'terms_conditions' => Quotation::getDefaultTermsConditions(),
            'created_by' => $salesUser?->id,
            'assigned_to' => $salesUser?->id,
            'priority' => $options['priority'] ?? 'normal',
        ];

        // Handle next follow up
        if (isset($options['next_follow_up_at'])) {
            $quotationData['next_follow_up_at'] = $options['next_follow_up_at'];
        }

        // Handle status transitions
        if ($status === 'submitted' || $status === 'approved') {
            $quotationData['status'] = Quotation::STATUS_SUBMITTED;
            $quotationData['submitted_at'] = now()->subDays(rand(1, 5));
            $quotationData['submitted_by'] = $salesUser?->id;
        }

        if ($status === 'approved') {
            $quotationData['status'] = Quotation::STATUS_APPROVED;
            $quotationData['approved_at'] = now()->subDays(rand(0, 3));
            $quotationData['approved_by'] = User::where('email', 'admin@demo.com')->first()?->id;
        }

        // Handle outcomes
        if (isset($options['outcome'])) {
            $quotationData['outcome'] = $options['outcome'];
            $quotationData['outcome_at'] = now();
            if ($options['outcome'] === 'won') {
                $quotationData['won_reason'] = $options['won_reason'] ?? 'lainnya';
            } elseif ($options['outcome'] === 'lost') {
                $quotationData['lost_reason'] = $options['lost_reason'] ?? 'lainnya';
                $quotationData['lost_to_competitor'] = $options['lost_to_competitor'] ?? null;
            }
        }

        $quotation = Quotation::updateOrCreate(
            ['quotation_number' => $quotationNumber, 'revision' => 0],
            $quotationData
        );

        // Delete existing items and recreate
        QuotationItem::where('quotation_id', $quotation->id)->delete();

        // Create items
        $sortOrder = 1;
        foreach ($items as $item) {
            $lineTotal = $item['product']->selling_price * $item['qty'];
            $itemTaxAmount = (int) round($lineTotal * ($taxRate / 100));

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $item['product']->id,
                'description' => $item['desc'],
                'quantity' => $item['qty'],
                'unit' => $item['product']->unit,
                'unit_price' => $item['product']->selling_price,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTaxAmount,
                'line_total' => $lineTotal,
                'sort_order' => $sortOrder++,
            ]);
        }

        return $quotation;
    }

    private function createInvoicesWithPayments(): void
    {
        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->get();
        $cashAccount = Account::where('code', '1-1001')->first(); // Kas
        $bankAccount = Account::where('code', '1-1010')->first(); // Bank BCA

        // Invoice 1: Paid - from PLN
        $plnJbr = $customers->firstWhere('code', 'C-PLN-JBR');
        $lvmdp100 = Product::where('sku', 'FG-LVMDP-100')->first();
        if ($plnJbr && $lvmdp100) {
            $invoice = $this->createInvoice(
                $plnJbr,
                [
                    ['product' => $lvmdp100, 'qty' => 2],
                ],
                'paid',
                now()->subDays(45)
            );

            // Create payment
            if ($invoice && $bankAccount) {
                Payment::updateOrCreate(
                    ['payment_number' => 'PAY-'.now()->format('Ym').'-0001'],
                    [
                        'payment_date' => now()->subDays(15),
                        'type' => Payment::TYPE_RECEIVE,
                        'contact_id' => $plnJbr->id,
                        'cash_account_id' => $bankAccount->id,
                        'amount' => $invoice->total_amount,
                        'reference' => 'TRF-PLN-001',
                        'notes' => 'Pembayaran Invoice '.$invoice->invoice_number,
                        'payable_type' => Invoice::class,
                        'payable_id' => $invoice->id,
                    ]
                );
            }
        }

        // Invoice 2: Partial payment - from Trias
        $trias = $customers->firstWhere('code', 'C-TRIA');
        $mccDol = Product::where('sku', 'FG-MCC-DOL-25')->first();
        if ($trias && $mccDol) {
            $invoice = $this->createInvoice(
                $trias,
                [
                    ['product' => $mccDol, 'qty' => 3],
                ],
                'partial',
                now()->subDays(30)
            );

            // 50% payment
            if ($invoice && $bankAccount) {
                $partialAmount = (int) ($invoice->total_amount * 0.5);
                Payment::updateOrCreate(
                    ['payment_number' => 'PAY-'.now()->format('Ym').'-0002'],
                    [
                        'payment_date' => now()->subDays(10),
                        'type' => Payment::TYPE_RECEIVE,
                        'contact_id' => $trias->id,
                        'cash_account_id' => $bankAccount->id,
                        'amount' => $partialAmount,
                        'reference' => 'TRF-TRIAS-002',
                        'notes' => 'DP 50% Invoice '.$invoice->invoice_number,
                        'payable_type' => Invoice::class,
                        'payable_id' => $invoice->id,
                    ]
                );

                $invoice->update([
                    'paid_amount' => $partialAmount,
                    'status' => Invoice::STATUS_PARTIAL,
                ]);
            }
        }

        // Invoice 3: Sent, awaiting payment - from PP
        $pp = $customers->firstWhere('code', 'C-PP');
        $ats100 = Product::where('sku', 'FG-ATS-100')->first();
        if ($pp && $ats100) {
            $this->createInvoice(
                $pp,
                [
                    ['product' => $ats100, 'qty' => 2],
                ],
                'sent',
                now()->subDays(10)
            );
        }

        // Invoice 4: Overdue - from Mitra Kontraktor
        $mkt = $customers->firstWhere('code', 'C-MKT');
        $db8w = Product::where('sku', 'FG-DB-8W')->first();
        if ($mkt && $db8w) {
            $this->createInvoice(
                $mkt,
                [
                    ['product' => $db8w, 'qty' => 10],
                ],
                'overdue',
                now()->subDays(60)
            );
        }

        $this->command->info('Created 4 invoices with payments');
    }

    private function createInvoice(Contact $contact, array $items, string $status, $invoiceDate): ?Invoice
    {
        self::$invoiceSeq++;
        $invoiceNumber = 'INV-'.now()->format('Ym').'-'.str_pad(self::$invoiceSeq, 4, '0', STR_PAD_LEFT);

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['product']->selling_price * $item['qty'];
        }

        $taxRate = 11.0;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;
        $dueDate = (clone $invoiceDate)->addDays($contact->payment_term_days ?? 30);

        $invoiceStatus = match ($status) {
            'paid' => Invoice::STATUS_PAID,
            'partial' => Invoice::STATUS_PARTIAL,
            'overdue' => Invoice::STATUS_OVERDUE,
            default => Invoice::STATUS_SENT,
        };

        $invoice = Invoice::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'contact_id' => $contact->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => $invoiceStatus,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'paid_amount' => $status === 'paid' ? $total : 0,
            ]
        );

        // Delete existing items and recreate
        InvoiceItem::where('invoice_id', $invoice->id)->delete();

        foreach ($items as $index => $item) {
            $lineTotal = $item['product']->selling_price * $item['qty'];

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product']->id,
                'description' => $item['product']->name,
                'quantity' => $item['qty'],
                'unit' => $item['product']->unit,
                'unit_price' => $item['product']->selling_price,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ]);
        }

        return $invoice;
    }

    private function createPurchaseOrders(): void
    {
        $vendors = Contact::where('type', Contact::TYPE_SUPPLIER)
            ->where('is_subcontractor', false)
            ->get();

        $purchasingUser = User::where('email', 'purchasing@demo.com')->first();

        // PO 1: Schneider - Approved, partially received
        $schneider = $vendors->firstWhere('code', 'S-SCH');
        $mccb100 = Product::where('sku', 'EL-MCCB-100')->first();
        $mccb250 = Product::where('sku', 'EL-MCCB-250')->first();
        $ctr25 = Product::where('sku', 'EL-CTR-25')->first();

        if ($schneider && $mccb100) {
            $this->createPurchaseOrder(
                $schneider,
                [
                    ['product' => $mccb100, 'qty' => 10],
                    ['product' => $mccb250, 'qty' => 5],
                    ['product' => $ctr25, 'qty' => 20],
                ],
                'approved',
                $purchasingUser
            );
        }

        // PO 2: Supreme Cable - Approved
        $supreme = $vendors->firstWhere('code', 'S-SUM');
        $nyy4x35 = Product::where('sku', 'CB-NYY-4X35')->first();
        $nyy4x70 = Product::where('sku', 'CB-NYY-4X70')->first();

        if ($supreme && $nyy4x35) {
            $this->createPurchaseOrder(
                $supreme,
                [
                    ['product' => $nyy4x35, 'qty' => 500],
                    ['product' => $nyy4x70, 'qty' => 200],
                ],
                'approved',
                $purchasingUser
            );
        }

        // PO 3: Busbar Indonesia - Draft
        $busbar = $vendors->firstWhere('code', 'S-BBR');
        $bb30x5 = Product::where('sku', 'BB-CU-30X5')->first();
        $bb40x5 = Product::where('sku', 'BB-CU-40X5')->first();

        if ($busbar && $bb30x5) {
            $this->createPurchaseOrder(
                $busbar,
                [
                    ['product' => $bb30x5, 'qty' => 50],
                    ['product' => $bb40x5, 'qty' => 30],
                ],
                'draft',
                $purchasingUser
            );
        }

        // PO 4: Rittal - Submitted
        $rittal = $vendors->firstWhere('code', 'S-RTL');
        $en800 = Product::where('sku', 'EN-800X600')->first();
        $en1000 = Product::where('sku', 'EN-1000X800')->first();

        if ($rittal && $en800) {
            $this->createPurchaseOrder(
                $rittal,
                [
                    ['product' => $en800, 'qty' => 10],
                    ['product' => $en1000, 'qty' => 5],
                ],
                'submitted',
                $purchasingUser
            );
        }

        $this->command->info('Created 4 purchase orders');
    }

    private function createPurchaseOrder(
        Contact $vendor,
        array $items,
        string $status,
        ?User $user = null
    ): PurchaseOrder {
        self::$poSeq++;
        $poNumber = 'PO-'.now()->format('Ym').'-'.str_pad(self::$poSeq, 4, '0', STR_PAD_LEFT);

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['product']->purchase_price * $item['qty'];
        }

        $taxRate = 11.0;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        $poStatus = match ($status) {
            'approved' => PurchaseOrder::STATUS_APPROVED,
            'submitted' => PurchaseOrder::STATUS_SUBMITTED,
            default => PurchaseOrder::STATUS_DRAFT,
        };

        $poData = [
            'po_number' => $poNumber,
            'contact_id' => $vendor->id,
            'po_date' => now()->subDays(rand(1, 20)),
            'expected_date' => now()->addDays(rand(7, 30)),
            'status' => $poStatus,
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'base_currency_total' => $total,
            'notes' => 'Mohon dikirim sesuai jadwal',
            'created_by' => $user?->id,
        ];

        if ($status === 'submitted' || $status === 'approved') {
            $poData['submitted_at'] = now()->subDays(rand(1, 5));
            $poData['submitted_by'] = $user?->id;
        }

        if ($status === 'approved') {
            $poData['approved_at'] = now()->subDays(rand(0, 3));
            $poData['approved_by'] = User::where('email', 'admin@demo.com')->first()?->id;
        }

        $po = PurchaseOrder::updateOrCreate(
            ['po_number' => $poNumber],
            $poData
        );

        // Delete existing items and recreate
        PurchaseOrderItem::where('purchase_order_id', $po->id)->delete();

        $sortOrder = 1;
        foreach ($items as $item) {
            $lineTotal = $item['product']->purchase_price * $item['qty'];
            $itemTaxAmount = (int) round($lineTotal * ($taxRate / 100));

            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => $item['product']->id,
                'description' => $item['product']->name,
                'quantity' => $item['qty'],
                'quantity_received' => 0,
                'unit' => $item['product']->unit,
                'unit_price' => $item['product']->purchase_price,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTaxAmount,
                'line_total' => $lineTotal,
                'sort_order' => $sortOrder++,
            ]);
        }

        return $po;
    }

    private function createWorkOrders(): void
    {
        $productionUser = User::where('email', 'produksi@demo.com')->first();
        $warehouse = Warehouse::where('is_default', true)->first();

        // WO 1: LVMDP 100A - Completed
        $bomLvmdp = Bom::where('bom_number', 'BOM-LVMDP-100')->first();
        if ($bomLvmdp && $warehouse) {
            $this->createWorkOrder(
                $bomLvmdp,
                2,
                'completed',
                $productionUser,
                $warehouse,
                now()->subDays(20)
            );
        }

        // WO 2: MCC DOL 25A - In Progress
        $bomMcc = Bom::where('bom_number', 'BOM-MCC-DOL-25')->first();
        if ($bomMcc && $warehouse) {
            $this->createWorkOrder(
                $bomMcc,
                3,
                'in_progress',
                $productionUser,
                $warehouse,
                now()->subDays(5)
            );
        }

        // WO 3: DB 8 Way - Planned
        $bomDb = Bom::where('bom_number', 'BOM-DB-8W')->first();
        if ($bomDb && $warehouse) {
            $this->createWorkOrder(
                $bomDb,
                10,
                'confirmed',
                $productionUser,
                $warehouse,
                now()
            );
        }

        $this->command->info('Created 3 work orders');
    }

    private function createWorkOrder(
        Bom $bom,
        int $quantity,
        string $status,
        ?User $user,
        Warehouse $warehouse,
        $startDate
    ): WorkOrder {
        self::$woSeq++;
        $woNumber = 'WO-'.now()->format('Ym').'-'.str_pad(self::$woSeq, 4, '0', STR_PAD_LEFT);

        $woStatus = match ($status) {
            'completed' => WorkOrder::STATUS_COMPLETED,
            'in_progress' => WorkOrder::STATUS_IN_PROGRESS,
            'confirmed' => WorkOrder::STATUS_CONFIRMED,
            default => WorkOrder::STATUS_DRAFT,
        };

        $completedQty = $status === 'completed' ? $quantity : ($status === 'in_progress' ? (int) ($quantity * 0.3) : 0);
        $progressPercentage = $quantity > 0 ? (int) min(100, round(($completedQty / $quantity) * 100)) : 0;

        $wo = WorkOrder::updateOrCreate(
            ['wo_number' => $woNumber],
            [
                'name' => 'Produksi '.$bom->name,
                'bom_id' => $bom->id,
                'product_id' => $bom->product_id,
                'warehouse_id' => $warehouse->id,
                'quantity_ordered' => $quantity,
                'quantity_completed' => $completedQty,
                'quantity_scrapped' => 0,
                'progress_percentage' => $progressPercentage,
                'planned_start_date' => $startDate,
                'planned_end_date' => (clone $startDate)->addDays(7),
                'actual_start_date' => in_array($status, ['in_progress', 'completed']) ? $startDate : null,
                'actual_end_date' => $status === 'completed' ? (clone $startDate)->addDays(5) : null,
                'status' => $woStatus,
                'priority' => 'normal',
                'notes' => 'Work Order untuk '.$bom->name,
                'created_by' => $user?->id,
            ]
        );

        return $wo;
    }
}
