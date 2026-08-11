<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Contracts\Manufacturing\MrpServiceInterface;
use App\Contracts\Manufacturing\SubcontractorServiceInterface;
use App\Contracts\Projects\TaskServiceInterface;
use App\Contracts\Purchasing\LandedCostServiceInterface;
use App\Contracts\Sales\RecurringServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Contracts\Shared\ReminderServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Sales\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds advanced operations demo data.
 *
 * Features covered:
 * - Landed Costs (additional costs applied to GRN)
 * - Stock Transfer (between warehouses)
 * - PPh Withholding Payment (tax withholding on vendor payment)
 * - Recurring Templates (auto-generate invoices/bills)
 * - Payment Reminders (scheduled invoice reminders)
 * - MRP Run (material requirements planning)
 * - Subcontracting (full lifecycle with invoicing)
 * - Project Tasks (with subtasks and dependencies)
 */
class DemoAdvancedOperationsSeeder extends Seeder
{
    private ?InventoryServiceInterface $inventoryService = null;

    private ?LandedCostServiceInterface $landedCostService = null;

    private ?PaymentServiceInterface $paymentService = null;

    private ?RecurringServiceInterface $recurringService = null;

    private ?ReminderServiceInterface $reminderService = null;

    private ?MrpServiceInterface $mrpService = null;

    private ?SubcontractorServiceInterface $subcontractorService = null;

    private ?TaskServiceInterface $taskService = null;

    private ?User $adminUser = null;

    private ?User $productionUser = null;

    private ?Warehouse $mainWarehouse = null;

    private ?Warehouse $productionWarehouse = null;

    private ?Account $bankAccount = null;

    private Carbon $baseDate;

    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        $this->baseDate = now()->startOfMonth();

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           ADVANCED OPERATIONS DEMO DATA                             ║');
        $this->command->info('║     Landed Costs • Stock Transfer • PPh • MRP • Subcontracting      ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        Auth::guard('web')->login($this->adminUser);

        try {
            $this->seedLandedCosts();
            $this->seedStockTransfer();
            $this->seedPphWithholdingPayment();
            $this->seedRecurringTemplates();
            $this->seedPaymentReminders();
            $this->seedMrpRun();
            $this->seedSubcontracting();
            $this->seedProjectTasks();
        } finally {
            Auth::guard('web')->logout();
            Carbon::setTestNow(null);
        }

        $this->outputSummary();
    }

    private function initializeServices(): void
    {
        $serviceMap = [
            'inventoryService' => InventoryServiceInterface::class,
            'landedCostService' => LandedCostServiceInterface::class,
            'paymentService' => PaymentServiceInterface::class,
            'recurringService' => RecurringServiceInterface::class,
            'reminderService' => ReminderServiceInterface::class,
            'mrpService' => MrpServiceInterface::class,
            'subcontractorService' => SubcontractorServiceInterface::class,
            'taskService' => TaskServiceInterface::class,
        ];

        foreach ($serviceMap as $property => $interface) {
            try {
                $this->{$property} = app($interface);
            } catch (\Throwable) {
                // Service not available
            }
        }
    }

    private function loadDependencies(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first();
        $this->productionUser = User::where('email', 'produksi@demo.com')->first() ?? $this->adminUser;
        $this->mainWarehouse = Warehouse::where('is_default', true)->first();
        $this->productionWarehouse = Warehouse::where('code', 'WH-002')->first();
        $this->bankAccount = Account::where('code', '1-1010')->first();

        if (! $this->adminUser || ! $this->mainWarehouse || ! $this->bankAccount) {
            throw new \RuntimeException(
                'Required dependencies not found. Ensure MasterDataSeeder has run.'
            );
        }
    }

    /**
     * Apply landed costs to a completed GRN.
     */
    private function seedLandedCosts(): void
    {
        $this->command->info('  → Seeding Landed Costs...');

        if (! $this->landedCostService) {
            $this->command->warn('    Landed cost service not available, skipping');

            return;
        }

        // Find a completed GRN with items
        $grn = GoodsReceiptNote::where('status', 'completed')
            ->whereHas('items', fn ($q) => $q->where('quantity_received', '>', 0))
            ->first();

        if (! $grn) {
            $this->command->warn('    No completed GRN found, skipping landed costs');

            return;
        }

        // Find a proper leaf expense account (not a parent header with null subtype)
        $expenseAccount = Account::where('type', Account::TYPE_EXPENSE)
            ->where('is_active', true)
            ->whereNotNull('subtype')
            ->where('subtype', '<>', '')
            ->first();

        if (! $expenseAccount) {
            $this->command->warn('    No expense account found, skipping landed costs');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(12));

        try {
            $landedCost = $this->landedCostService->create($grn, [
                'description' => 'Biaya pengiriman dan handling material',
                'cost_type' => 'freight',
                'total_amount' => 2_500_000,
                'allocation_method' => 'by_value',
                'expense_account_id' => $expenseAccount->id,
            ]);

            $this->landedCostService->apply($landedCost);

            $this->command->info('    Applied landed cost Rp 2,500,000 to GRN');
        } catch (\Throwable $e) {
            $this->command->warn('    Landed cost skipped: '.$e->getMessage());
        }
    }

    /**
     * Transfer stock between warehouses.
     */
    private function seedStockTransfer(): void
    {
        $this->command->info('  → Seeding Stock Transfer...');

        if (! $this->inventoryService || ! $this->productionWarehouse) {
            $this->command->warn('    Inventory service or production warehouse not available, skipping');

            return;
        }

        // Find a product with stock in main warehouse
        $product = Product::where('track_inventory', true)
            ->where('current_stock', '>', 5)
            ->first();

        if (! $product) {
            $this->command->warn('    No product with sufficient stock found, skipping stock transfer');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(10));

        try {
            $transferQty = min(5, (int) floor($product->current_stock / 2));
            $this->inventoryService->transfer(
                $product,
                $this->mainWarehouse,
                $this->productionWarehouse,
                $transferQty,
                'Transfer material ke area produksi untuk WO'
            );

            $this->command->info("    Transferred {$transferQty} {$product->unit} of {$product->sku} to production warehouse");
        } catch (\Throwable $e) {
            $this->command->warn('    Stock transfer skipped: '.$e->getMessage());
        }
    }

    /**
     * Create a bill payment with PPh withholding tax.
     */
    private function seedPphWithholdingPayment(): void
    {
        $this->command->info('  → Seeding PPh Withholding Payment...');

        // Find a PPh-subject vendor with an unpaid bill
        $pphVendor = Contact::where('is_pph_subject', true)->first();
        if (! $pphVendor) {
            $this->command->warn('    No PPh-subject vendor found, skipping');

            return;
        }

        // Find or create a posted bill for the PPh vendor
        $bill = Bill::where('contact_id', $pphVendor->id)
            ->whereIn('status', ['received', 'partial'])
            ->first();

        if (! $bill) {
            $this->command->warn('    No unpaid bill for PPh vendor, skipping');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(18));

        // Temporarily enable PPh
        $originalPph = config('accounting.pph.enabled');
        config(['accounting.pph.enabled' => true]);

        try {
            $outstanding = $bill->getOutstandingAmount();
            $this->paymentService->createForBill($bill, [
                'payment_date' => now()->toDateString(),
                'amount' => $outstanding,
                'cash_account_id' => $this->bankAccount->id,
                'reference' => 'PAY-PPH-'.now()->format('Ymd'),
                'notes' => 'Pembayaran dengan pemotongan PPh '.$pphVendor->pph_category?->value,
            ]);

            $this->command->info('    Created PPh withholding payment for '.$pphVendor->name);
        } catch (\Throwable $e) {
            $this->command->warn('    PPh payment skipped: '.$e->getMessage());
        } finally {
            config(['accounting.pph.enabled' => $originalPph]);
        }
    }

    /**
     * Create recurring templates from existing documents.
     */
    private function seedRecurringTemplates(): void
    {
        $this->command->info('  → Seeding Recurring Templates...');

        if (! $this->recurringService) {
            $this->command->warn('    Recurring service not available, skipping');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(15));
        $templateCount = 0;

        // Create recurring invoice template
        $invoice = Invoice::where('status', '!=', 'draft')
            ->whereHas('items')
            ->first();

        if ($invoice) {
            try {
                $this->recurringService->createTemplateFromInvoice($invoice, [
                    'name' => 'Faktur Bulanan - '.$invoice->contact?->name,
                    'frequency' => 'monthly',
                    'interval' => 1,
                    'start_date' => now()->addMonth()->startOfMonth()->toDateString(),
                    'occurrences_limit' => 12,
                    'auto_post' => true,
                    'auto_send' => false,
                ]);
                $templateCount++;
            } catch (\Throwable $e) {
                $this->command->warn('    Invoice recurring template skipped: '.$e->getMessage());
            }
        }

        // Create recurring bill template
        $bill = Bill::where('status', '!=', 'draft')
            ->whereHas('items')
            ->first();

        if ($bill) {
            try {
                $this->recurringService->createTemplateFromBill($bill, [
                    'name' => 'Tagihan Bulanan - '.$bill->contact?->name,
                    'frequency' => 'monthly',
                    'interval' => 1,
                    'start_date' => now()->addMonth()->startOfMonth()->toDateString(),
                    'occurrences_limit' => 12,
                    'auto_post' => false,
                    'auto_send' => false,
                ]);
                $templateCount++;
            } catch (\Throwable $e) {
                $this->command->warn('    Bill recurring template skipped: '.$e->getMessage());
            }
        }

        $this->command->info("    Created {$templateCount} recurring templates");
    }

    /**
     * Schedule payment reminders for unpaid invoices.
     */
    private function seedPaymentReminders(): void
    {
        $this->command->info('  → Seeding Payment Reminders...');

        if (! $this->reminderService) {
            $this->command->warn('    Reminder service not available, skipping');

            return;
        }

        // Find unpaid invoices to schedule reminders for
        $invoice = Invoice::whereIn('status', ['sent', 'partial', 'overdue'])
            ->first();

        if (! $invoice) {
            $this->command->warn('    No unpaid invoices found, skipping reminders');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(20));

        try {
            $reminders = $this->reminderService->scheduleInvoiceReminders($invoice);
            $this->command->info('    Scheduled '.$reminders->count().' payment reminders');
        } catch (\Throwable $e) {
            $this->command->warn('    Payment reminders skipped: '.$e->getMessage());
        }
    }

    /**
     * Create and execute an MRP run.
     */
    private function seedMrpRun(): void
    {
        $this->command->info('  → Seeding MRP Run...');

        if (! $this->mrpService) {
            $this->command->warn('    MRP service not available, skipping');

            return;
        }

        // Check if there are BOMs and work orders to generate demands from
        $hasBoms = Bom::whereIn('status', ['approved', 'active'])->exists();
        if (! $hasBoms) {
            $this->command->warn('    No approved BOMs found, skipping MRP');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(15));

        try {
            $mrpRun = $this->mrpService->create([
                'name' => 'MRP Run - '.now()->format('F Y'),
                'planning_horizon_start' => now()->toDateString(),
                'planning_horizon_end' => now()->addMonths(3)->toDateString(),
                'warehouse_id' => $this->mainWarehouse->id,
                'notes' => 'Perencanaan material 3 bulan ke depan',
            ]);

            $mrpRun = $this->mrpService->executeRun($mrpRun, $this->productionUser->id);

            $this->command->info("    MRP run completed: {$mrpRun->total_products_analyzed} products analyzed, {$mrpRun->total_shortages} shortages");
        } catch (\Throwable $e) {
            $this->command->warn('    MRP run skipped: '.$e->getMessage());
        }
    }

    /**
     * Subcontracting: create → assign → start → progress → complete → invoice → approve → convert to bill.
     */
    private function seedSubcontracting(): void
    {
        $this->command->info('  → Seeding Subcontracting...');

        if (! $this->subcontractorService) {
            $this->command->warn('    Subcontractor service not available, skipping');

            return;
        }

        // Find a subcontractor contact
        $subcontractor = Contact::where('is_subcontractor', true)->first();
        if (! $subcontractor) {
            $this->command->warn('    No subcontractor found, skipping');

            return;
        }

        // Find a work order to associate with
        $workOrder = WorkOrder::whereIn('status', ['in_progress', 'confirmed'])
            ->first();

        // Find a project to associate with
        $project = Project::first();

        Carbon::setTestNow($this->baseDate->copy()->addDays(8));

        try {
            // Create subcontractor work order
            $scWo = $this->subcontractorService->create([
                'subcontractor_id' => $subcontractor->id,
                'work_order_id' => $workOrder?->id,
                'project_id' => $project?->id,
                'name' => 'Jasa Instalasi & Wiring Panel',
                'description' => 'Instalasi panel dan wiring ke lokasi customer',
                'scope_of_work' => 'Instalasi panel LVMDP, kabel power, grounding, dan commissioning',
                'agreed_amount' => 25_000_000,
                'retention_percent' => 5,
                'scheduled_start_date' => now()->addDays(3)->toDateString(),
                'scheduled_end_date' => now()->addDays(17)->toDateString(),
                'work_location' => 'Kawasan MM2100, Bekasi',
                'location_address' => 'Jl. Industri Raya No. 100',
                'notes' => 'Include tools dan transport',
            ]);

            // Assign
            Carbon::setTestNow($this->baseDate->copy()->addDays(9));
            $scWo = $this->subcontractorService->assign($scWo, $this->adminUser->id);

            // Start work
            Carbon::setTestNow($this->baseDate->copy()->addDays(11));
            $scWo = $this->subcontractorService->start($scWo, $this->productionUser->id);

            // Update progress
            Carbon::setTestNow($this->baseDate->copy()->addDays(15));
            $scWo = $this->subcontractorService->updateProgress($scWo, 50);

            Carbon::setTestNow($this->baseDate->copy()->addDays(20));
            $scWo = $this->subcontractorService->updateProgress($scWo, 85);

            // Complete
            Carbon::setTestNow($this->baseDate->copy()->addDays(24));
            $scWo = $this->subcontractorService->complete($scWo, null, $this->productionUser->id);

            // Create invoice from subcontractor
            Carbon::setTestNow($this->baseDate->copy()->addDays(25));
            $scInvoice = $this->subcontractorService->createInvoice($scWo, [
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'gross_amount' => $scWo->agreed_amount,
                'description' => 'Tagihan jasa instalasi panel - '.$scWo->name,
            ]);

            // Approve invoice
            Carbon::setTestNow($this->baseDate->copy()->addDays(26));
            $scInvoice = $this->subcontractorService->approveInvoice($scInvoice, $this->adminUser->id);

            // Convert to bill
            Carbon::setTestNow($this->baseDate->copy()->addDays(27));
            $this->subcontractorService->convertToBill($scInvoice);

            $this->command->info('    Subcontracting complete: created → assigned → started → completed → invoiced → bill');
        } catch (\Throwable $e) {
            $this->command->warn('    Subcontracting skipped: '.$e->getMessage());
        }
    }

    /**
     * Create project tasks with subtasks and dependencies.
     */
    private function seedProjectTasks(): void
    {
        $this->command->info('  → Seeding Project Tasks...');

        if (! $this->taskService) {
            $this->command->warn('    Task service not available, skipping');

            return;
        }

        $project = Project::whereIn('status', ['in_progress', 'active'])
            ->first();

        if (! $project) {
            $this->command->warn('    No active project found, skipping tasks');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(5));

        try {
            // Create parent task: Design
            $designTask = $this->taskService->create($project, [
                'title' => 'Design & Engineering',
                'description' => 'Single-line diagram, wiring diagram, dan layout panel',
                'priority' => 'high',
                'assigned_to' => $this->adminUser->id,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(5)->toDateString(),
                'estimated_hours' => 40,
            ]);

            // Add subtasks to design
            $sldTask = $this->taskService->addSubtask($designTask, [
                'title' => 'Gambar Single Line Diagram',
                'description' => 'SLD sesuai spesifikasi teknis',
                'priority' => 'high',
                'assigned_to' => $this->adminUser->id,
                'estimated_hours' => 16,
            ]);

            $wiringTask = $this->taskService->addSubtask($designTask, [
                'title' => 'Gambar Wiring Diagram',
                'description' => 'Wiring diagram control dan power',
                'priority' => 'medium',
                'assigned_to' => $this->adminUser->id,
                'estimated_hours' => 24,
            ]);

            // Create parent task: Production
            $prodTask = $this->taskService->create($project, [
                'title' => 'Produksi & Assembly',
                'description' => 'Assembly panel, wiring, dan testing',
                'priority' => 'high',
                'assigned_to' => $this->productionUser->id,
                'start_date' => now()->addDays(6)->toDateString(),
                'due_date' => now()->addDays(20)->toDateString(),
                'estimated_hours' => 80,
            ]);

            // Add dependency: production depends on design
            $this->taskService->addDependency($prodTask, $designTask);

            // Create parent task: Installation
            $installTask = $this->taskService->create($project, [
                'title' => 'Instalasi & Commissioning',
                'description' => 'Instalasi di lokasi customer dan commissioning',
                'priority' => 'medium',
                'assigned_to' => $this->productionUser->id,
                'start_date' => now()->addDays(21)->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'estimated_hours' => 40,
            ]);

            // Add dependency: installation depends on production
            $this->taskService->addDependency($installTask, $prodTask);

            // Complete the SLD subtask and start the wiring one
            Carbon::setTestNow($this->baseDate->copy()->addDays(8));
            $this->taskService->start($sldTask, $this->adminUser->id);

            Carbon::setTestNow($this->baseDate->copy()->addDays(10));
            $this->taskService->complete($sldTask, $this->adminUser->id);
            $this->taskService->start($wiringTask, $this->adminUser->id);

            $this->command->info('    Created 3 parent tasks + 2 subtasks with dependencies');
        } catch (\Throwable $e) {
            $this->command->warn('    Project tasks skipped: '.$e->getMessage());
        }
    }

    private function outputSummary(): void
    {
        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║              Advanced Operations Demo Summary                       ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║    Landed Cost                   1 (applied to GRN)                 ║');
        $this->command->info('║    Stock Transfer                1 (warehouse to warehouse)          ║');
        $this->command->info('║    PPh Withholding Payment       1 (if PPh vendor exists)            ║');
        $this->command->info('║    Recurring Templates           2 (invoice + bill)                  ║');
        $this->command->info('║    Payment Reminders             1 set (per invoice)                 ║');
        $this->command->info('║    MRP Run                       1 (3-month horizon)                 ║');
        $this->command->info('║    Subcontractor Work Order      1 (full lifecycle)                  ║');
        $this->command->info('║    Project Tasks                 3 + 2 subtasks                      ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
