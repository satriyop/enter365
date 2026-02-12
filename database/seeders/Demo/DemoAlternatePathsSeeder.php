<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Purchasing\PurchaseOrderServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\QuotationServiceInterface;
use App\Contracts\Solar\SolarProposalServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds alternate document paths: rejections, cancellations, voids, and solar proposals.
 *
 * Demonstrates non-happy-path flows that users encounter in real operations:
 * - Quotations rejected by management or cancelled after approval
 * - Purchase orders rejected or cancelled
 * - Invoices voided after posting
 * - Solar proposals going through the full proposal→quotation lifecycle
 */
class DemoAlternatePathsSeeder extends Seeder
{
    private QuotationServiceInterface $quotationService;

    private PurchaseOrderServiceInterface $purchaseOrderService;

    private InvoiceServiceInterface $invoiceService;

    private BillServiceInterface $billService;

    private ?SolarProposalServiceInterface $solarProposalService = null;

    private ?User $adminUser = null;

    private ?User $salesUser = null;

    private ?User $purchasingUser = null;

    private ?Warehouse $warehouse = null;

    private ?Account $bankAccount = null;

    private Carbon $baseDate;

    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        $this->baseDate = now()->startOfMonth();

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           ALTERNATE PATHS DEMO DATA                                 ║');
        $this->command->info('║     Rejections • Cancellations • Voids • Solar Proposals             ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        Auth::login($this->adminUser);

        try {
            $this->seedQuotationReject();
            $this->seedQuotationCancel();
            $this->seedPurchaseOrderReject();
            $this->seedPurchaseOrderCancel();
            $this->seedInvoiceVoid();
            $this->seedSolarProposal();
        } finally {
            Auth::logout();
            Carbon::setTestNow(null);
        }

        $this->outputSummary();
    }

    private function initializeServices(): void
    {
        $this->quotationService = app(QuotationServiceInterface::class);
        $this->purchaseOrderService = app(PurchaseOrderServiceInterface::class);
        $this->invoiceService = app(InvoiceServiceInterface::class);
        $this->billService = app(BillServiceInterface::class);

        // Solar proposal service may not be bound if module is disabled
        try {
            $this->solarProposalService = app(SolarProposalServiceInterface::class);
        } catch (\Throwable) {
            // Solar module not available
        }
    }

    private function loadDependencies(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first();
        $this->salesUser = User::where('email', 'sales@demo.com')->first() ?? $this->adminUser;
        $this->purchasingUser = User::where('email', 'purchasing@demo.com')->first() ?? $this->adminUser;
        $this->warehouse = Warehouse::where('is_default', true)->first();
        $this->bankAccount = Account::where('code', '1-1010')->first();

        if (! $this->adminUser || ! $this->warehouse || ! $this->bankAccount) {
            throw new \RuntimeException(
                'Required dependencies not found. Ensure MasterDataSeeder has run.'
            );
        }
    }

    /**
     * Quotation rejected by management - price too high.
     */
    private function seedQuotationReject(): void
    {
        $this->command->info('  → Seeding Quotation Rejection...');

        $customer = Contact::where('code', 'C-MKT')->first();
        $items = $this->buildSalesItems([
            ['sku' => 'FG-DB-8W', 'qty' => 20, 'desc' => 'Distribution Board 8 Way - project kantor'],
        ]);

        if (! $customer || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping quotation reject');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(2));

        $quotation = $this->quotationService->create([
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subject' => 'Panel DB untuk Renovasi Kantor',
            'items' => $items,
        ], $this->salesUser);

        Carbon::setTestNow($this->baseDate->copy()->addDays(3));
        $quotation = $this->quotationService->submit($quotation, $this->salesUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(4));
        $this->quotationService->reject($quotation, 'Harga terlalu mahal, minta revisi dari sales', $this->adminUser->id);

        $this->command->info('    Created 1 rejected quotation');
    }

    /**
     * Quotation approved then cancelled - customer changed plans.
     */
    private function seedQuotationCancel(): void
    {
        $this->command->info('  → Seeding Quotation Cancellation...');

        $customer = Contact::where('code', 'C-ELM')->first();
        $items = $this->buildSalesItems([
            ['sku' => 'FG-ATS-100', 'qty' => 2, 'desc' => 'Panel ATS 100A untuk showroom'],
        ]);

        if (! $customer || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping quotation cancel');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(3));

        $quotation = $this->quotationService->create([
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subject' => 'Panel ATS untuk Showroom Baru',
            'items' => $items,
        ], $this->salesUser);

        Carbon::setTestNow($this->baseDate->copy()->addDays(4));
        $quotation = $this->quotationService->submit($quotation, $this->salesUser->id);
        $quotation = $this->quotationService->approve($quotation, $this->adminUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(7));
        $this->quotationService->cancel($quotation, 'Customer batal, proyek showroom ditunda', $this->adminUser->id);

        $this->command->info('    Created 1 cancelled quotation');
    }

    /**
     * PO rejected by management - supplier price too high.
     */
    private function seedPurchaseOrderReject(): void
    {
        $this->command->info('  → Seeding PO Rejection...');

        $supplier = Contact::where('code', 'S-CHNT')->first();
        $items = $this->buildPurchaseItems([
            ['sku' => 'EL-MCB-1P16', 'qty' => 100],
            ['sku' => 'EL-MCB-3P32', 'qty' => 50],
        ]);

        if (! $supplier || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping PO reject');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(4));

        $po = $this->purchaseOrderService->create([
            'contact_id' => $supplier->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(14)->toDateString(),
            'items' => $items,
            'notes' => 'Restock MCB untuk stok gudang',
        ]);

        Carbon::setTestNow($this->baseDate->copy()->addDays(5));
        $po = $this->purchaseOrderService->submit($po, $this->purchasingUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(6));
        $this->purchaseOrderService->reject($po, 'Harga supplier terlalu tinggi, cari alternatif', $this->adminUser->id);

        $this->command->info('    Created 1 rejected PO');
    }

    /**
     * PO approved then cancelled - supplier can't deliver.
     */
    private function seedPurchaseOrderCancel(): void
    {
        $this->command->info('  → Seeding PO Cancellation...');

        $supplier = Contact::where('code', 'S-ENB')->first();
        $items = $this->buildPurchaseItems([
            ['sku' => 'EN-800X600', 'qty' => 3],
        ]);

        if (! $supplier || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping PO cancel');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(5));

        $po = $this->purchaseOrderService->create([
            'contact_id' => $supplier->id,
            'po_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'items' => $items,
            'notes' => 'Enclosure untuk project urgent',
        ]);

        Carbon::setTestNow($this->baseDate->copy()->addDays(6));
        $po = $this->purchaseOrderService->submit($po, $this->purchasingUser->id);
        $po = $this->purchaseOrderService->approve($po, $this->adminUser->id);

        Carbon::setTestNow($this->baseDate->copy()->addDays(9));
        $this->purchaseOrderService->cancel($po, 'Supplier tidak bisa kirim tepat waktu, pindah ke Rittal', $this->adminUser->id);

        $this->command->info('    Created 1 cancelled PO');
    }

    /**
     * Invoice posted then voided - wrong data entry.
     */
    private function seedInvoiceVoid(): void
    {
        $this->command->info('  → Seeding Invoice Void...');

        $customer = Contact::where('code', 'C-TRIA')->first();
        $items = $this->buildSalesItems([
            ['sku' => 'FG-DB-8W', 'qty' => 3, 'desc' => 'Distribution Board 8 Way'],
        ]);

        if (! $customer || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping invoice void');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(8));

        $invoice = $this->invoiceService->create([
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => $items,
        ]);
        $invoice = $this->invoiceService->post($invoice);

        Carbon::setTestNow($this->baseDate->copy()->addDays(9));
        $this->invoiceService->void($invoice, 'Salah input, perlu dibuat ulang dengan data yang benar');

        $this->command->info('    Created 1 voided invoice');
    }

    /**
     * Solar proposal: create → calculate → select BOM → send → accept → convert to quotation.
     */
    private function seedSolarProposal(): void
    {
        $this->command->info('  → Seeding Solar Proposal...');

        if (! $this->solarProposalService) {
            $this->command->warn('    Solar module not available, skipping');

            return;
        }

        // Find a NEX customer for the solar proposal
        $customer = Contact::where('code', 'C-IND-CRG')->first();
        if (! $customer) {
            $this->command->warn('    NEX customer not found, skipping solar proposal');

            return;
        }

        // Find a variant group with BOMs for the proposal
        $variantGroup = BomVariantGroup::whereHas('boms')->first();
        if (! $variantGroup) {
            $this->command->warn('    No BOM variant group found, skipping solar proposal');

            return;
        }

        $bom = Bom::where('variant_group_id', $variantGroup->id)
            ->whereIn('status', ['approved', 'active'])
            ->first();

        if (! $bom) {
            $this->command->warn('    No approved BOM in variant group, skipping solar proposal');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(5));

        try {
            $proposal = $this->solarProposalService->create([
                'contact_id' => $customer->id,
                'site_name' => 'Pabrik Pakan Ternak - CP Cikarang',
                'site_address' => 'Kawasan Industri Jababeka, Cikarang',
                'province' => 'Jawa Barat',
                'city' => 'Bekasi',
                'latitude' => -6.3200,
                'longitude' => 107.1700,
                'roof_area_m2' => 5000,
                'roof_type' => 'flat',
                'roof_orientation' => 'north',
                'roof_tilt_degrees' => 10,
                'shading_percentage' => 5,
                'monthly_consumption_kwh' => 85000,
                'pln_tariff_category' => 'I-3/TM',
                'electricity_rate' => 1115,
                'tariff_escalation_percent' => 5.0,
                'notes' => 'Survey sudah dilakukan, atap dalam kondisi baik',
                'created_by' => $this->salesUser->id,
            ]);

            // Calculate financial analysis
            Carbon::setTestNow($this->baseDate->copy()->addDays(6));
            $proposal = $this->solarProposalService->calculateProposal($proposal);

            // Attach variant group and select BOM
            $proposal = $this->solarProposalService->attachVariantGroup($proposal, $variantGroup->id);
            $proposal = $this->solarProposalService->selectBom($proposal, $bom->id);

            // Send to customer
            Carbon::setTestNow($this->baseDate->copy()->addDays(8));
            $proposal = $this->solarProposalService->send($proposal);

            // Customer accepts
            Carbon::setTestNow($this->baseDate->copy()->addDays(12));
            $proposal = $this->solarProposalService->accept($proposal, $bom->id);

            // Convert to quotation
            Carbon::setTestNow($this->baseDate->copy()->addDays(13));
            $this->solarProposalService->convertToQuotation($proposal);

            $this->command->info('    Created 1 solar proposal (accepted → quotation)');
        } catch (\Throwable $e) {
            $this->command->warn('    Solar proposal skipped: '.$e->getMessage());
        }
    }

    /**
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

    private function outputSummary(): void
    {
        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║              Alternate Paths Demo Summary                           ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║    Quotation Rejected            1                                  ║');
        $this->command->info('║    Quotation Cancelled           1                                  ║');
        $this->command->info('║    PO Rejected                   1                                  ║');
        $this->command->info('║    PO Cancelled                  1                                  ║');
        $this->command->info('║    Invoice Voided                1                                  ║');
        $this->command->info('║    Solar Proposal                1 (if solar module active)          ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
