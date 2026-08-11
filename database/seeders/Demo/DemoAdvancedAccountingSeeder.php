<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Contracts\Accounting\BankReconciliationServiceInterface;
use App\Contracts\Accounting\BudgetServiceInterface;
use App\Contracts\Accounting\FxRevaluationServiceInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Sales\WriteOffServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Seeds advanced accounting demo data.
 *
 * Features covered:
 * - NSFP Tax Invoice (auto-allocated tax number)
 * - Multi-Currency Invoice & Bill (USD)
 * - FX Revaluation (period-end closing rate adjustment)
 * - Write-Off / Bad Debt
 * - Bank Reconciliation (create bank transactions, match to payments, reconcile session)
 * - Budgets (annual budget with monthly breakdown)
 */
class DemoAdvancedAccountingSeeder extends Seeder
{
    private InvoiceServiceInterface $invoiceService;

    private BillServiceInterface $billService;

    private PaymentServiceInterface $paymentService;

    private ?FxRevaluationServiceInterface $fxRevalService = null;

    private ?WriteOffServiceInterface $writeOffService = null;

    private ?BankReconciliationServiceInterface $bankReconService = null;

    private ?BudgetServiceInterface $budgetService = null;

    private ?User $adminUser = null;

    private ?User $financeUser = null;

    private ?Account $bankAccount = null;

    private Carbon $baseDate;

    public function run(): void
    {
        $this->initializeServices();
        $this->loadDependencies();

        $this->baseDate = now()->startOfMonth();

        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║           ADVANCED ACCOUNTING DEMO DATA                             ║');
        $this->command->info('║     NSFP • Multi-Currency • FX Reval • Write-Off • Bank Recon       ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');

        Auth::guard('web')->login($this->adminUser);

        try {
            $this->seedNsfpTaxInvoice();
            $this->seedMultiCurrencyInvoice();
            $this->seedMultiCurrencyBill();
            $this->seedFxRevaluation();
            $this->seedWriteOff();
            $this->seedBankReconciliation();
            $this->seedBudgets();
        } finally {
            Auth::guard('web')->logout();
            Carbon::setTestNow(null);
        }

        $this->outputSummary();
    }

    private function initializeServices(): void
    {
        $this->invoiceService = app(InvoiceServiceInterface::class);
        $this->billService = app(BillServiceInterface::class);
        $this->paymentService = app(PaymentServiceInterface::class);

        try {
            $this->fxRevalService = app(FxRevaluationServiceInterface::class);
        } catch (\Throwable) {
        }

        try {
            $this->writeOffService = app(WriteOffServiceInterface::class);
        } catch (\Throwable) {
        }

        try {
            $this->bankReconService = app(BankReconciliationServiceInterface::class);
        } catch (\Throwable) {
        }

        try {
            $this->budgetService = app(BudgetServiceInterface::class);
        } catch (\Throwable) {
        }
    }

    private function loadDependencies(): void
    {
        $this->adminUser = User::where('email', 'admin@demo.com')->first();
        $this->financeUser = User::where('email', 'finance@demo.com')->first() ?? $this->adminUser;
        $this->bankAccount = Account::where('code', '1-1010')->first();

        if (! $this->adminUser || ! $this->bankAccount) {
            throw new \RuntimeException(
                'Required dependencies not found. Ensure MasterDataSeeder has run.'
            );
        }
    }

    /**
     * Create an invoice with NSFP tax numbering enabled.
     */
    private function seedNsfpTaxInvoice(): void
    {
        $this->command->info('  → Seeding NSFP Tax Invoice...');

        $customer = Contact::where('code', 'C-PLN-JBR')->first();
        $items = $this->buildSalesItems([
            ['sku' => 'FG-LVMDP-100', 'qty' => 1, 'desc' => 'Panel LVMDP 100A - Faktur Pajak'],
        ]);

        if (! $customer || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping NSFP invoice');

            return;
        }

        // Temporarily enable NSFP
        $originalNsfp = config('accounting.nsfp.enabled');
        config(['accounting.nsfp.enabled' => true]);

        try {
            Carbon::setTestNow($this->baseDate->copy()->addDays(2));

            $invoice = $this->invoiceService->create([
                'contact_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(45)->toDateString(),
                'items' => $items,
            ]);
            $this->invoiceService->post($invoice);

            $this->command->info('    Created 1 NSFP tax invoice');
        } finally {
            config(['accounting.nsfp.enabled' => $originalNsfp]);
        }
    }

    /**
     * Create a multi-currency (USD) invoice.
     */
    private function seedMultiCurrencyInvoice(): void
    {
        $this->command->info('  → Seeding Multi-Currency Invoice (USD)...');

        $customer = Contact::where('code', 'C-AST')->first();
        $items = $this->buildSalesItems([
            ['sku' => 'FG-MCC-DOL-25', 'qty' => 2, 'desc' => 'MCC Panel DOL 25A (export)'],
        ]);

        if (! $customer || empty($items)) {
            $this->command->warn('    Dependencies missing, skipping multi-currency invoice');

            return;
        }

        // Convert prices to USD equivalent (divide by exchange rate)
        foreach ($items as &$item) {
            $item['unit_price'] = (int) round($item['unit_price'] / 15800);
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(3));

        $invoice = $this->invoiceService->create([
            'contact_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => 15800,
            'items' => $items,
        ]);
        $this->invoiceService->post($invoice);

        $this->command->info('    Created 1 USD invoice (rate: 15,800)');
    }

    /**
     * Create a multi-currency (USD) bill.
     */
    private function seedMultiCurrencyBill(): void
    {
        $this->command->info('  → Seeding Multi-Currency Bill (USD)...');

        $supplier = Contact::where('code', 'S-SCH')->first();

        if (! $supplier) {
            $this->command->warn('    Supplier not found, skipping multi-currency bill');

            return;
        }

        // Use generic items for the USD bill
        $product = Product::where('sku', 'EL-MCCB-100')->first();
        if (! $product) {
            $this->command->warn('    Product not found, skipping multi-currency bill');

            return;
        }

        $usdPrice = (int) round($product->purchase_price / 15800);

        Carbon::setTestNow($this->baseDate->copy()->addDays(4));

        $bill = $this->billService->create([
            'contact_id' => $supplier->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'vendor_invoice_number' => 'SCH-USD-'.now()->format('Ymd'),
            'currency' => 'USD',
            'exchange_rate' => 15800,
            'items' => [
                [
                    'description' => $product->name.' (import)',
                    'quantity' => 10,
                    'unit' => $product->unit,
                    'unit_price' => $usdPrice,
                ],
            ],
        ]);
        $this->billService->post($bill);

        $this->command->info('    Created 1 USD bill (rate: 15,800)');
    }

    /**
     * Run FX revaluation at month-end with closing rate.
     */
    private function seedFxRevaluation(): void
    {
        $this->command->info('  → Seeding FX Revaluation...');

        if (! $this->fxRevalService) {
            $this->command->warn('    FX Revaluation service not available, skipping');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->endOfMonth());

        try {
            $this->fxRevalService->revalue(
                now()->toDateString(),
                ['USD' => 16000.0],
                true // with reversal entries
            );
            $this->command->info('    Created FX revaluation (USD closing rate: 16,000)');
        } catch (\Throwable $e) {
            $this->command->warn('    FX revaluation skipped: '.$e->getMessage());
        }
    }

    /**
     * Write off a small unpaid invoice as bad debt.
     */
    private function seedWriteOff(): void
    {
        $this->command->info('  → Seeding Write-Off (Bad Debt)...');

        if (! $this->writeOffService) {
            $this->command->warn('    Write-off service not available, skipping');

            return;
        }

        // Find an unpaid/partial invoice with outstanding balance
        $invoice = Invoice::whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('currency', 'IDR')
            ->orderBy('total_amount', 'asc')
            ->first();

        if (! $invoice) {
            $this->command->warn('    No unpaid invoice found for write-off');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(25));

        $outstanding = $invoice->getOutstandingAmount();
        $writeOffAmount = min($outstanding, (int) round($outstanding * 0.3));

        if ($writeOffAmount <= 0) {
            $this->command->warn('    Invoice has no outstanding amount');

            return;
        }

        try {
            $this->writeOffService->writeOff($invoice, [
                'amount' => $writeOffAmount,
                'reason' => 'Piutang macet - customer tidak merespon penagihan 3 bulan',
                'write_off_date' => now()->toDateString(),
            ]);
            $this->command->info('    Written off Rp '.number_format($writeOffAmount, 0, ',', '.')." from invoice {$invoice->invoice_number}");
        } catch (\Throwable $e) {
            $this->command->warn('    Write-off skipped: '.$e->getMessage());
        }
    }

    /**
     * Create bank transactions and reconcile against existing payments.
     */
    private function seedBankReconciliation(): void
    {
        $this->command->info('  → Seeding Bank Reconciliation...');

        if (! $this->bankReconService) {
            $this->command->warn('    Bank reconciliation service not available, skipping');

            return;
        }

        // Find existing payments to match bank transactions against
        $payments = Payment::whereNotNull('amount')
            ->where('amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        if ($payments->isEmpty()) {
            $this->command->warn('    No payments found for bank reconciliation');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(28));

        // Start a reconciliation session
        $statementBalance = $payments->sum('amount');
        $session = $this->bankReconService->startSession(
            $this->bankAccount,
            now()->toDateString(),
            $statementBalance
        );

        $matchedCount = 0;

        foreach ($payments as $payment) {
            try {
                // Create a bank transaction that mirrors the payment
                $txn = $this->bankReconService->create([
                    'account_id' => $this->bankAccount->id,
                    'transaction_date' => $payment->payment_date ?? now()->toDateString(),
                    'description' => 'Bank statement: '.$payment->reference,
                    'reference' => $payment->reference,
                    'debit' => $payment->amount,
                    'credit' => 0,
                    'balance' => 0,
                    'session_id' => $session->id,
                    'created_by' => $this->financeUser->id,
                ]);

                // Match the bank transaction to the payment
                $this->bankReconService->matchToPayment($txn, $payment);
                $this->bankReconService->reconcile($txn);

                $matchedCount++;
            } catch (\Throwable $e) {
                $this->command->warn("    Could not reconcile payment: {$e->getMessage()}");
            }
        }

        // Complete the session
        try {
            $this->bankReconService->completeSession($session);
        } catch (\Throwable $e) {
            $this->command->warn('    Session completion skipped: '.$e->getMessage());
        }

        $this->command->info("    Reconciled {$matchedCount} bank transactions in 1 session");
    }

    /**
     * Create an annual budget with monthly line items.
     */
    private function seedBudgets(): void
    {
        $this->command->info('  → Seeding Budgets...');

        if (! $this->budgetService) {
            $this->command->warn('    Budget service not available, skipping');

            return;
        }

        // Find current fiscal period
        $fiscalPeriod = FiscalPeriod::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (! $fiscalPeriod) {
            $this->command->warn('    No active fiscal period found, skipping budgets');

            return;
        }

        // Find expense accounts to budget against
        $expenseAccounts = Account::where('code', 'like', '5-%')
            ->where('is_active', true)
            ->limit(4)
            ->get();

        $revenueAccounts = Account::where('code', 'like', '4-%')
            ->where('is_active', true)
            ->limit(2)
            ->get();

        if ($expenseAccounts->isEmpty()) {
            $this->command->warn('    No expense accounts found, skipping budgets');

            return;
        }

        Carbon::setTestNow($this->baseDate->copy()->addDays(1));

        $lines = [];

        // Revenue budget lines
        foreach ($revenueAccounts as $account) {
            $lines[] = [
                'account_id' => $account->id,
                'annual_amount' => 1_200_000_000, // Rp 1.2B per year = Rp 100M/month
            ];
        }

        // Expense budget lines
        $expenseBudgets = [120_000_000, 60_000_000, 36_000_000, 24_000_000];
        foreach ($expenseAccounts as $index => $account) {
            $lines[] = [
                'account_id' => $account->id,
                'annual_amount' => $expenseBudgets[$index] ?? 24_000_000,
            ];
        }

        try {
            $this->budgetService->createBudget([
                'name' => 'Anggaran Operasional '.now()->format('Y'),
                'description' => 'Budget tahunan untuk operasional perusahaan',
                'fiscal_period_id' => $fiscalPeriod->id,
                'type' => 'annual',
                'status' => 'draft',
            ], $lines);

            $this->command->info('    Created 1 budget with '.count($lines).' line items');
        } catch (\Throwable $e) {
            $this->command->warn('    Budget creation skipped: '.$e->getMessage());
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

    private function outputSummary(): void
    {
        $this->command->info('');
        $this->command->info('╔═══════════════════════════════════════════════════════════════════╗');
        $this->command->info('║              Advanced Accounting Demo Summary                       ║');
        $this->command->info('╠═══════════════════════════════════════════════════════════════════╣');
        $this->command->info('║    NSFP Tax Invoice              1                                  ║');
        $this->command->info('║    Multi-Currency Invoice         1 (USD)                            ║');
        $this->command->info('║    Multi-Currency Bill            1 (USD)                            ║');
        $this->command->info('║    FX Revaluation                1 (month-end)                      ║');
        $this->command->info('║    Write-Off (Bad Debt)          1                                  ║');
        $this->command->info('║    Bank Reconciliation           1 session                          ║');
        $this->command->info('║    Budget                        1 (annual)                         ║');
        $this->command->info('╚═══════════════════════════════════════════════════════════════════╝');
        $this->command->info('');
    }
}
