<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Inventory\StockOpnameServiceInterface;
use App\Contracts\Manufacturing\MaterialRequisitionServiceInterface;
use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Contracts\Projects\ProjectServiceInterface;
use App\Contracts\Sales\DownPaymentServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds extended demo data for features not covered by base transaction seeders.
 *
 * This seeder adds demo data for:
 * - Projects (with costs and progress tracking)
 * - Down Payments (customer and vendor, with applications)
 * - Work Orders (from BOMs, with material consumption)
 * - Material Requisitions (from Work Orders)
 * - Stock Opnames (with variance adjustment)
 */
class DemoExtendedTransactionSeeder extends Seeder
{
    private ProjectServiceInterface $projectService;

    private DownPaymentServiceInterface $downPaymentService;

    private WorkOrderServiceInterface $workOrderService;

    private MaterialRequisitionServiceInterface $mrService;

    private StockOpnameServiceInterface $stockOpnameService;

    private ?User $adminUser = null;

    private ?User $productionUser = null;

    private ?User $warehouseUser = null;

    private ?Warehouse $warehouse = null;

    private ?Account $bankAccount = null;

    /**
     * Base date for seeding (start of month simulation).
     */
    private Carbon $baseDate;

    /**
     * Run the extended transaction seeder.
     */
    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        $this->baseDate = now()->startOfMonth();

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           EXTENDED FEATURE DEMO DATA                               ║');
        $this->command->info('║     Projects • Down Payments • Work Orders • Stock Opnames         ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        Auth::login($this->adminUser);

        try {
            $this->seedProjects();
            $this->seedDownPayments();
            $this->seedWorkOrders();
            $this->seedMaterialRequisitions();
            $this->seedStockOpnames();
        } finally {
            Auth::logout();
        }

        $this->outputSummary();
    }

    /**
     * Initialize services via service container.
     */
    private function initializeServices(): void
    {
        $this->projectService = app(ProjectServiceInterface::class);
        $this->downPaymentService = app(DownPaymentServiceInterface::class);
        $this->workOrderService = app(WorkOrderServiceInterface::class);
        $this->mrService = app(MaterialRequisitionServiceInterface::class);
        $this->stockOpnameService = app(StockOpnameServiceInterface::class);
    }

    /**
     * Load required dependencies from existing seeders.
     */
    private function loadDependencies(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first();
        $this->productionUser = User::where('email', 'produksi@demo.com')->first();
        $this->warehouseUser = User::where('email', 'gudang@demo.com')->first();
        $this->warehouse = Warehouse::where('is_default', true)->first();
        $this->bankAccount = Account::where('code', '1-1010')->first(); // Bank BCA

        if (! $this->adminUser || ! $this->warehouse || ! $this->bankAccount) {
            throw new \RuntimeException(
                'Required dependencies not found. Ensure UserSeeder, WarehouseSeeder, and AccountSeeder have run.'
            );
        }

        // Fallback users if specific roles don't exist
        $this->productionUser ??= $this->adminUser;
        $this->warehouseUser ??= $this->adminUser;
    }

    /**
     * Seed Projects with costs and progress.
     */
    private function seedProjects(): void
    {
        $this->command->info('  → Seeding Projects...');

        $customers = Contact::where('type', Contact::TYPE_CUSTOMER)->limit(2)->get();

        if ($customers->isEmpty()) {
            $this->command->warn('    No customers found, skipping projects');

            return;
        }

        // Project 1: Panel Installation - In Progress (50%)
        $customer1 = $customers->first();
        Carbon::setTestNow($this->baseDate->copy()->addDays(1));

        $project1 = $this->projectService->create([
            'name' => 'Panel LVMDP Instalasi - Pabrik A',
            'description' => 'Instalasi panel LVMDP 250A untuk pabrik baru',
            'contact_id' => $customer1->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'budget_amount' => 500_000_000,
            'contract_amount' => 600_000_000,
            'priority' => 'high',
            'location' => 'Kawasan Industri MM2100, Bekasi',
            'manager_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        // Start the project
        Carbon::setTestNow($this->baseDate->copy()->addDays(3));
        $this->projectService->start($project1);

        // Add material costs (quantity * unit_cost = total_cost)
        Carbon::setTestNow($this->baseDate->copy()->addDays(5));
        $this->projectService->addCost($project1, [
            'cost_type' => 'material',
            'quantity' => 1,
            'unit' => 'lot',
            'unit_cost' => 200_000_000,
            'description' => 'Pembelian komponen panel LVMDP',
            'cost_date' => now()->toDateString(),
        ]);

        // Add labor costs
        Carbon::setTestNow($this->baseDate->copy()->addDays(10));
        $this->projectService->addCost($project1, [
            'cost_type' => 'labor',
            'quantity' => 10,
            'unit' => 'orang-hari',
            'unit_cost' => 5_000_000,
            'description' => 'Biaya tenaga kerja instalasi',
            'cost_date' => now()->toDateString(),
        ]);

        // Update progress to 50%
        Carbon::setTestNow($this->baseDate->copy()->addDays(15));
        $this->projectService->updateProgress($project1, 50.0);

        // Add revenue (milestone billing)
        $this->projectService->addRevenue($project1, [
            'revenue_type' => 'milestone',
            'amount' => 300_000_000,
            'description' => 'Termin 1 - Progress 50%',
            'revenue_date' => now()->toDateString(),
            'milestone_name' => 'Progress 50%',
            'milestone_percentage' => 50.00,
        ]);

        // Project 2: Completed Project
        $customer2 = $customers->count() > 1 ? $customers->last() : $customer1;
        Carbon::setTestNow($this->baseDate->copy()->addDays(2));

        $project2 = $this->projectService->create([
            'name' => 'Panel MCC Produksi - Kontrak PLN',
            'description' => 'Pembuatan panel MCC untuk proyek PLN',
            'contact_id' => $customer2->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
            'budget_amount' => 300_000_000,
            'contract_amount' => 350_000_000,
            'priority' => 'medium',
            'location' => 'Gardu Induk Cawang',
            'manager_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
        ]);

        $this->projectService->start($project2);
        $this->projectService->updateProgress($project2, 100.0);
        $this->projectService->complete($project2);

        Carbon::setTestNow(null);

        $this->command->info('    Created 2 projects (1 in progress, 1 completed)');
    }

    /**
     * Seed Down Payments with applications.
     */
    private function seedDownPayments(): void
    {
        $this->command->info('  → Seeding Down Payments...');

        // Get a posted invoice to apply DP to
        $invoice = Invoice::whereIn('status', ['sent', 'partial'])
            ->with('contact')
            ->first();

        // Get a posted bill to apply DP to
        $bill = Bill::whereIn('status', ['received', 'partial'])
            ->with('contact')
            ->first();

        $dpCount = 0;

        // DP 1: Customer (Receivable) Down Payment
        if ($invoice) {
            Carbon::setTestNow($this->baseDate->copy()->addDays(3));

            $dp1 = $this->downPaymentService->create([
                'type' => 'receivable',
                'contact_id' => $invoice->contact_id,
                'dp_date' => now()->toDateString(),
                'amount' => min(50_000_000, (int) ($invoice->total_amount * 0.3)),
                'payment_method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
                'description' => 'DP 30% Project - '.$invoice->contact->name,
                'reference' => 'DP-CUST-'.now()->format('Ymd'),
            ]);

            // Apply partial amount to invoice
            Carbon::setTestNow($this->baseDate->copy()->addDays(7));
            $applyAmount = min($dp1->remaining_amount, $invoice->getOutstandingAmount());
            if ($applyAmount > 0) {
                $this->downPaymentService->applyToInvoice($dp1, $invoice, [
                    'amount' => (int) ($applyAmount * 0.6), // Apply 60% of DP
                    'applied_date' => now()->toDateString(),
                    'notes' => 'Aplikasi DP ke Invoice '.$invoice->invoice_number,
                ]);
            }

            $dpCount++;
        }

        // DP 2: Vendor (Payable) Down Payment
        if ($bill) {
            Carbon::setTestNow($this->baseDate->copy()->addDays(4));

            $dp2 = $this->downPaymentService->create([
                'type' => 'payable',
                'contact_id' => $bill->contact_id,
                'dp_date' => now()->toDateString(),
                'amount' => min(30_000_000, (int) ($bill->total_amount * 0.3)),
                'payment_method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
                'description' => 'DP Pembelian Material - '.$bill->contact->name,
                'reference' => 'DP-VEND-'.now()->format('Ymd'),
            ]);

            // Apply to bill
            Carbon::setTestNow($this->baseDate->copy()->addDays(8));
            $applyAmount = min($dp2->remaining_amount, $bill->getOutstandingAmount());
            if ($applyAmount > 0) {
                $this->downPaymentService->applyToBill($dp2, $bill, [
                    'amount' => $applyAmount,
                    'applied_date' => now()->toDateString(),
                    'notes' => 'Aplikasi DP ke Bill '.$bill->bill_number,
                ]);
            }

            $dpCount++;
        }

        Carbon::setTestNow(null);

        $this->command->info("    Created {$dpCount} down payments with applications");
    }

    /**
     * Seed Work Orders from BOMs.
     */
    private function seedWorkOrders(): void
    {
        $this->command->info('  → Seeding Work Orders...');

        // Find BOMs with items to create work orders from (active = approved status)
        $boms = Bom::whereHas('items')
            ->where('status', 'approved')
            ->limit(2)
            ->get();

        if ($boms->isEmpty()) {
            $this->command->warn('    No BOMs found, skipping work orders');

            return;
        }

        $woCount = 0;

        foreach ($boms as $index => $bom) {
            Carbon::setTestNow($this->baseDate->copy()->addDays($index + 5));

            // Create work order from BOM
            $wo = $this->workOrderService->createFromBom($bom, [
                'name' => 'Produksi: '.$bom->name,
                'quantity_ordered' => $index === 0 ? 2 : 1,
                'warehouse_id' => $this->warehouse->id,
                'planned_start_date' => now()->toDateString(),
                'planned_end_date' => now()->addDays(14)->toDateString(),
                'priority' => $index === 0 ? 'high' : 'medium',
                'notes' => 'Work Order untuk produksi '.$bom->name,
            ]);

            // Confirm the work order
            Carbon::setTestNow($this->baseDate->copy()->addDays($index + 6));
            $wo = $this->workOrderService->confirm($wo, $this->productionUser->id);

            // Start the work order
            Carbon::setTestNow($this->baseDate->copy()->addDays($index + 7));
            $wo = $this->workOrderService->start($wo, $this->productionUser->id);

            // For the first WO, complete it
            if ($index === 0) {
                Carbon::setTestNow($this->baseDate->copy()->addDays($index + 14));
                $this->workOrderService->recordOutput($wo, $wo->quantity_ordered, 0);
                $this->workOrderService->complete($wo, $this->productionUser->id);
            }

            $woCount++;
        }

        Carbon::setTestNow(null);

        $this->command->info("    Created {$woCount} work orders (1 completed, 1 in progress)");
    }

    /**
     * Seed Material Requisitions from Work Orders.
     */
    private function seedMaterialRequisitions(): void
    {
        $this->command->info('  → Seeding Material Requisitions...');

        // Get work orders in progress that have items
        $workOrders = \App\Models\Manufacturing\WorkOrder::where('status', 'in_progress')
            ->whereHas('items')
            ->limit(2)
            ->get();

        if ($workOrders->isEmpty()) {
            $this->command->warn('    No in-progress work orders found, skipping material requisitions');

            return;
        }

        $mrCount = 0;

        foreach ($workOrders as $index => $wo) {
            Carbon::setTestNow($this->baseDate->copy()->addDays(10 + $index));

            // Create material requisition from work order
            $mr = $this->mrService->create($wo, [
                'warehouse_id' => $this->warehouse->id,
                'requested_date' => now()->toDateString(),
                'required_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Material requisition untuk '.$wo->name,
            ]);

            // Populate from work order BOM items
            $this->mrService->populateFromWorkOrder($mr, $wo);

            // Approve the requisition
            Carbon::setTestNow($this->baseDate->copy()->addDays(11 + $index));
            $mr = $this->mrService->approve($mr, $this->adminUser->id);

            // Issue materials for the first MR (full issue)
            if ($index === 0 && $mr->items->isNotEmpty()) {
                Carbon::setTestNow($this->baseDate->copy()->addDays(12 + $index));
                $issueItems = $mr->items->map(function ($item) {
                    return [
                        'item_id' => $item->id,
                        'quantity' => $item->quantity_approved,
                    ];
                })->toArray();

                $this->mrService->issue($mr, $issueItems, $this->warehouseUser->id);
            }

            $mrCount++;
        }

        Carbon::setTestNow(null);

        $this->command->info("    Created {$mrCount} material requisitions");
    }

    /**
     * Seed Stock Opnames with variance.
     */
    private function seedStockOpnames(): void
    {
        $this->command->info('  → Seeding Stock Opnames...');

        // Check if there's inventory with stock
        $hasStock = ProductStock::where('quantity', '>', 0)->exists();

        if (! $hasStock) {
            $this->command->warn('    No inventory with stock found, skipping stock opnames');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(20));

        // Create stock opname
        $opname = $this->stockOpnameService->create([
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Stock Opname Bulanan - '.now()->format('F Y'),
            'notes' => 'Penghitungan stok bulanan rutin',
        ]);

        // Refresh to get proper status from DB (enum value becomes string)
        $opname = $opname->fresh();

        // Generate items from current inventory
        $opname = $this->stockOpnameService->generateItems($opname);

        if ($opname->items->isEmpty()) {
            $this->command->warn('    No items generated for stock opname');
            Carbon::setTestNow(null);

            return;
        }

        // Start counting
        Carbon::setTestNow($this->baseDate->copy()->addDays(21));
        $opname = $this->stockOpnameService->startCounting($opname, $this->warehouseUser->id);

        // Record counts with small variances
        $opname->load('items');
        foreach ($opname->items as $item) {
            // Add small random variance (-2 to +2)
            $variance = rand(-2, 2);
            $countedQty = max(0, $item->system_quantity + $variance);

            $this->stockOpnameService->updateItem($item, [
                'counted_quantity' => $countedQty,
                'notes' => $variance !== 0 ? 'Variance detected during count' : null,
            ]);
        }

        // Submit for review
        Carbon::setTestNow($this->baseDate->copy()->addDays(22));
        $opname = $this->stockOpnameService->submitForReview($opname, $this->warehouseUser->id);

        // Approve (creates adjustment journal entries)
        Carbon::setTestNow($this->baseDate->copy()->addDays(23));
        $this->stockOpnameService->approve($opname, $this->adminUser->id);

        Carbon::setTestNow(null);

        $this->command->info('    Created 1 stock opname with variance adjustments');
    }

    /**
     * Output summary of seeded data.
     */
    private function outputSummary(): void
    {
        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║              Extended Feature Demo Summary                         ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');

        $counts = [
            'Projects' => \App\Models\Projects\Project::count(),
            'Down Payments' => \App\Models\Sales\DownPayment::count(),
            'Work Orders' => \App\Models\Manufacturing\WorkOrder::count(),
            'Material Requisitions' => \App\Models\Manufacturing\MaterialRequisition::count(),
            'Stock Opnames' => \App\Models\Inventory\StockOpname::count(),
        ];

        foreach ($counts as $feature => $count) {
            $paddedFeature = str_pad($feature, 24);
            $paddedCount = str_pad((string) $count, 5, ' ', STR_PAD_LEFT);
            $this->command->info("║    {$paddedFeature} {$paddedCount}                            ║");
        }

        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
