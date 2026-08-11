<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Purchasing\GoodsReceiptNoteServiceInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Shared\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Pure trading demo for FEATURE_PRESET=general / demo=general.
 *
 * Creates a minimal end-to-end SME cycle without manufacturing or solar:
 * - Catalog products + customer/supplier
 * - Purchase: PO → GRN → Bill → Payment
 * - Sales: Quotation → Invoice → DO ship → Payment
 * - One partial invoice payment
 */
class GeneralTradingDemoSeeder extends Seeder
{
    private QuotationServiceInterface $quotationService;

    private InvoiceServiceInterface $invoiceService;

    private DeliveryOrderServiceInterface $deliveryOrderService;

    private PaymentServiceInterface $paymentService;

    private PurchaseOrderServiceInterface $purchaseOrderService;

    private GoodsReceiptNoteServiceInterface $grnService;

    private BillServiceInterface $billService;

    private ?User $adminUser = null;

    private ?Warehouse $warehouse = null;

    private ?Account $bankAccount = null;

    private Carbon $baseDate;

    public function run(): void
    {
        $this->quotationService = app(QuotationServiceInterface::class);
        $this->invoiceService = app(InvoiceServiceInterface::class);
        $this->deliveryOrderService = app(DeliveryOrderServiceInterface::class);
        $this->paymentService = app(PaymentServiceInterface::class);
        $this->purchaseOrderService = app(PurchaseOrderServiceInterface::class);
        $this->grnService = app(GoodsReceiptNoteServiceInterface::class);
        $this->billService = app(BillServiceInterface::class);

        $this->adminUser = User::where('email', 'admin@demo.com')->first()
            ?? User::query()->first();
        $this->warehouse = Warehouse::where('is_default', true)->first()
            ?? Warehouse::query()->first();
        $this->bankAccount = Account::where('code', '1-1010')->first()
            ?? Account::where('code', '1-1002')->first()
            ?? Account::where('code', '1-1001')->first();

        if (! $this->adminUser || ! $this->warehouse || ! $this->bankAccount) {
            throw new \RuntimeException(
                'General trading demo needs users, a warehouse, and a cash/bank account. Run foundation + MasterData seeders first.'
            );
        }

        $this->baseDate = now()->startOfMonth();

        $this->command?->info('🏢 Seeding general trading cycles (PO→Bill, Quotation→Invoice→DO→Pay)...');

        Auth::guard('web')->login($this->adminUser);

        try {
            [$customer, $vendor, $productA, $productB] = $this->seedCatalog();

            $this->seedPurchaseCycle($vendor, $productA, $productB);
            $this->seedSalesCycle($customer, $productA, $productB);
            $this->seedPartialInvoice($customer, $productB);
        } finally {
            Auth::guard('web')->logout();
            Carbon::setTestNow(null);
        }

        $this->command?->info('  ✓ General trading demo cycles ready');
    }

    /**
     * @return array{0: Contact, 1: Contact, 2: Product, 3: Product}
     */
    private function seedCatalog(): array
    {
        $customer = Contact::query()->updateOrCreate(
            ['code' => 'C-GEN-01'],
            [
                'name' => 'PT Mitra Dagang Umum',
                'type' => Contact::TYPE_CUSTOMER,
                'email' => 'purchasing@mitradagang.example',
                'phone' => '021-5550101',
                'is_active' => true,
                'payment_term_days' => 30,
            ]
        );

        $vendor = Contact::query()->updateOrCreate(
            ['code' => 'S-GEN-01'],
            [
                'name' => 'CV Sumber Suku Cadang',
                'type' => Contact::TYPE_SUPPLIER,
                'email' => 'sales@sumbersuku.example',
                'phone' => '021-5550202',
                'is_active' => true,
                'payment_term_days' => 14,
            ]
        );

        $productA = Product::query()->updateOrCreate(
            ['sku' => 'GEN-ITEM-A'],
            [
                'name' => 'Barang Dagangan A',
                'type' => Product::TYPE_PRODUCT,
                'unit' => 'pcs',
                'purchase_price' => 100_000,
                'selling_price' => 150_000,
                'track_inventory' => true,
                'is_active' => true,
            ]
        );

        $productB = Product::query()->updateOrCreate(
            ['sku' => 'GEN-ITEM-B'],
            [
                'name' => 'Barang Dagangan B',
                'type' => Product::TYPE_PRODUCT,
                'unit' => 'pcs',
                'purchase_price' => 250_000,
                'selling_price' => 375_000,
                'track_inventory' => true,
                'is_active' => true,
            ]
        );

        return [$customer, $vendor, $productA, $productB];
    }

    private function seedPurchaseCycle(Contact $vendor, Product $productA, Product $productB): void
    {
        Carbon::setTestNow($this->baseDate->copy()->addDays(2));

        $po = $this->purchaseOrderService->create([
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Demo general: restock inventory',
            'created_by' => $this->adminUser->id,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'description' => $productA->name,
                    'quantity' => 50,
                    'unit_price' => (int) $productA->purchase_price,
                ],
                [
                    'product_id' => $productB->id,
                    'description' => $productB->name,
                    'quantity' => 20,
                    'unit_price' => (int) $productB->purchase_price,
                ],
            ],
        ]);

        $po = $this->purchaseOrderService->submit($po, $this->adminUser->id);
        $po = $this->purchaseOrderService->approve($po, $this->adminUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(5));

        $grn = $this->grnService->createFromPurchaseOrder($po, [
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'created_by' => $this->adminUser->id,
        ]);

        $grn = $this->grnService->startReceiving($grn, $this->adminUser->id);

        foreach ($grn->items as $item) {
            $this->grnService->updateItem($item, [
                'quantity_received' => $item->quantity_ordered,
                'quantity_rejected' => 0,
            ]);
        }

        $grn = $this->grnService->complete($grn->fresh(['items']), $this->adminUser->id);

        $po->refresh();
        $bill = $this->purchaseOrderService->convertToBill($po);
        $bill = $this->billService->post($bill);

        Carbon::setTestNow($this->baseDate->copy()->addDays(12));

        $this->paymentService->createForBill($bill->fresh(), [
            'payment_date' => now()->toDateString(),
            'amount' => $bill->total_amount,
            'cash_account_id' => $this->bankAccount->id,
            'payment_method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-GEN-PO-'.now()->format('Ymd'),
            'notes' => 'Pelunasan tagihan demo general',
        ]);

        $this->command?->info('  ✓ Purchase cycle: PO → GRN → Bill → Payment');
    }

    private function seedSalesCycle(Contact $customer, Product $productA, Product $productB): void
    {
        Carbon::setTestNow($this->baseDate->copy()->addDays(8));

        $quotation = $this->quotationService->create([
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subject' => 'Penawaran demo general — restock customer',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'description' => $productA->name,
                    'quantity' => 10,
                    'unit_price' => (int) $productA->selling_price,
                ],
                [
                    'product_id' => $productB->id,
                    'description' => $productB->name,
                    'quantity' => 4,
                    'unit_price' => (int) $productB->selling_price,
                ],
            ],
        ], $this->adminUser);

        Carbon::setTestNow($this->baseDate->copy()->addDays(9));
        $quotation = $this->quotationService->submit($quotation, $this->adminUser->id);
        $quotation = $this->quotationService->approve($quotation, $this->adminUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(10));
        $invoice = $this->quotationService->convertToInvoice($quotation);
        $invoice = $this->invoiceService->post($invoice);

        Carbon::setTestNow($this->baseDate->copy()->addDays(11));
        $deliveryOrder = $this->deliveryOrderService->createFromInvoice($invoice, [
            'warehouse_id' => $this->warehouse->id,
            'do_date' => now()->toDateString(),
            'created_by' => $this->adminUser->id,
        ]);
        $deliveryOrder = $this->deliveryOrderService->confirm($deliveryOrder, $this->adminUser->id);
        $this->deliveryOrderService->ship($deliveryOrder, [], $this->adminUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(15));
        $this->paymentService->createForInvoice($invoice->fresh(), [
            'payment_date' => now()->toDateString(),
            'amount' => $invoice->total_amount,
            'cash_account_id' => $this->bankAccount->id,
            'payment_method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-GEN-INV-'.now()->format('Ymd'),
            'notes' => 'Pelunasan faktur demo general',
        ]);

        $this->command?->info('  ✓ Sales cycle: Quotation → Invoice → DO → Payment');
    }

    private function seedPartialInvoice(Contact $customer, Product $productB): void
    {
        Carbon::setTestNow($this->baseDate->copy()->addDays(14));

        $invoice = $this->invoiceService->create([
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'description' => 'Invoice langsung demo (partial payment)',
            'created_by' => $this->adminUser->id,
            'items' => [
                [
                    'product_id' => $productB->id,
                    'description' => $productB->name,
                    'quantity' => 2,
                    'unit_price' => (int) $productB->selling_price,
                ],
            ],
        ]);

        $invoice = $this->invoiceService->post($invoice);

        Carbon::setTestNow($this->baseDate->copy()->addDays(18));
        $half = (int) round($invoice->total_amount * 0.5);
        $this->paymentService->createForInvoice($invoice->fresh(), [
            'payment_date' => now()->toDateString(),
            'amount' => $half,
            'cash_account_id' => $this->bankAccount->id,
            'payment_method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-GEN-PART-'.now()->format('Ymd'),
            'notes' => 'Pembayaran 50% demo general',
        ]);

        $this->command?->info('  ✓ Direct invoice with 50% partial payment');
    }
}
