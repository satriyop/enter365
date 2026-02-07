<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use Carbon\Carbon;

class JournalService extends BaseService implements JournalServiceInterface
{
    public function __construct(
        private AccountLookupServiceInterface $accountLookup,
        private AccountingPolicyManager $policyManager,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a journal entry with lines.
     *
     * @param array{
     *     entry_date: string,
     *     description: string,
     *     reference?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     lines: array<array{account_id: int, debit?: int, credit?: int, description?: string, currency_code?: string|null, amount_currency?: int|null, exchange_rate?: float|null}>
     * } $data
     */
    public function createEntry(array $data, bool $autoPost = false): JournalEntry
    {
        return $this->executeInTransaction('create_entry', function () use ($data, $autoPost) {
            // Resolve fiscal period by entry_date, not today's date
            $entryDate = Carbon::parse($data['entry_date']);
            $fiscalPeriod = FiscalPeriod::forDate($entryDate);

            if ($fiscalPeriod && $fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Closed) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'membuat jurnal',
                    "Periode fiskal '{$fiscalPeriod->name}' sudah ditutup untuk tanggal {$entryDate->toDateString()}."
                );
            }

            if ($fiscalPeriod && $fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Locked) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'membuat jurnal',
                    "Periode fiskal '{$fiscalPeriod->name}' sedang dikunci untuk tanggal {$entryDate->toDateString()}."
                );
            }

            $entry = JournalEntry::create([
                'entry_number' => $data['entry_number'] ?? \App\Domain\Shared\DocumentNumbers::generate(
                    'JE-'.now()->format('Ym').'-',
                    'journal_entries',
                    'entry_number'
                ),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'source_type' => $data['source_type'] ?? JournalEntry::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'fiscal_period_id' => $fiscalPeriod?->id,
                'is_posted' => false,
                'created_by' => $this->getUserId(),
            ]);

            foreach ($data['lines'] as $lineData) {
                // Support both account_id and account_code for flexibility
                $accountId = $lineData['account_id'] ?? null;
                if (! $accountId && isset($lineData['account_code'])) {
                    $account = $this->accountLookup->findByCodeOrFail(
                        $lineData['account_code'],
                        'journal entry line'
                    );
                    $accountId = $account->id;
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accountId,
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                    'currency_code' => $lineData['currency_code'] ?? null,
                    'amount_currency' => $lineData['amount_currency'] ?? null,
                    'exchange_rate' => $lineData['exchange_rate'] ?? null,
                ]);
            }

            if ($autoPost) {
                $this->postEntry($entry);
            }

            return $entry->fresh(['lines', 'lines.account']);
        }, ['source_type' => $data['source_type'] ?? 'manual', 'source_id' => $data['source_id'] ?? null]);
    }

    /**
     * Validate and post a journal entry.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        if ($entry->is_posted) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($entry, 'Journal entry is already posted.');
        }

        if (! $entry->isBalanced()) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting journal entry',
                'Journal entry is not balanced. Debit: '.$entry->getTotalDebit().', Credit: '.$entry->getTotalCredit()
            );
        }

        if ($entry->lines()->count() < 2) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting journal entry',
                'Journal entry must have at least two lines'
            );
        }

        // Check fiscal period is open for posting
        if ($entry->fiscalPeriod) {
            if ($entry->fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Closed) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'posting journal entry',
                    "Tidak bisa posting ke periode fiskal '{$entry->fiscalPeriod->name}' yang sudah ditutup."
                );
            }

            if ($entry->fiscalPeriod->getStatus() === \App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus::Locked) {
                throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                    'posting journal entry',
                    "Tidak bisa posting ke periode fiskal '{$entry->fiscalPeriod->name}' yang sedang dikunci."
                );
            }
        }

        $entry->update(['is_posted' => true]);

        return $entry->fresh();
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverseEntry(JournalEntry $entry, ?string $description = null): JournalEntry
    {
        if (! $entry->is_posted) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'reversing journal entry',
                'Cannot reverse an unposted journal entry'
            );
        }

        if ($entry->is_reversed) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'reversing journal entry',
                'Journal entry is already reversed'
            );
        }

        return $this->executeInTransaction('reverse_entry', function () use ($entry, $description) {
            // Create reversal entry with swapped debits/credits
            $reversalLines = [];
            foreach ($entry->lines as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit, // Swap
                    'credit' => $line->debit, // Swap
                    'currency_code' => $line->currency_code,
                    'amount_currency' => $line->amount_currency,
                    'exchange_rate' => $line->exchange_rate,
                ];
            }

            $entryDate = $entry->entry_date;
            $description = 'Reversal of '.$entry->entry_number.': '.$entry->description;

            $reversalEntry = $this->createEntry([
                'entry_date' => $entryDate instanceof \Carbon\Carbon ? $entryDate->toDateString() : (string) $entryDate,
                'description' => $description,
                'reference' => $entry->entry_number,
                'source_type' => JournalEntry::SOURCE_REVERSAL,
                'source_id' => $entry->id,
                'lines' => $reversalLines,
            ], autoPost: true);

            // Update reversal relationships
            $reversalEntry->update(['reversal_of_id' => $entry->id]);
            $entry->update([
                'is_reversed' => true,
                'reversed_by_id' => $reversalEntry->id,
            ]);

            return $reversalEntry->fresh(['lines', 'lines.account']);
        }, ['entry_id' => $entry->id]);
    }

    /**
     * Convert a foreign currency amount to base currency (IDR).
     */
    private function toBaseCurrency(int $amount, string $currency, float $exchangeRate): int
    {
        if ($currency === 'IDR' || $exchangeRate <= 0) {
            return $amount;
        }

        return (int) round($amount * $exchangeRate);
    }

    /**
     * Build currency metadata fields for a JE line (IS-M1 audit trail).
     *
     * Returns empty array for IDR transactions (columns stay NULL).
     *
     * @return array{currency_code?: string, amount_currency?: int, exchange_rate?: float}
     */
    private function currencyMeta(string $currency, int $originalAmount, float $exchangeRate): array
    {
        if ($currency === 'IDR') {
            return [];
        }

        return [
            'currency_code' => $currency,
            'amount_currency' => $originalAmount,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * Create journal entry for an invoice when posted.
     *
     * All JE amounts are recorded in base currency (IDR).
     * AR is derived as the balancing figure to guarantee DR == CR.
     */
    public function postInvoice(Invoice $invoice): JournalEntry
    {
        if ($invoice->journal_entry_id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting invoice',
                'Invoice is already posted to journal'
            );
        }

        // Fail-fast: validate required accounts exist
        $requiredCodes = ['1-1100', '2-1200', '4-1001'];
        if ($invoice->discount_amount > 0) {
            $requiredCodes[] = '4-1003'; // Diskon Penjualan
        }
        $accounts = $this->accountLookup->findByCodesOrFail($requiredCodes, 'posting invoice');

        $receivableAccount = $invoice->receivableAccount ?? $accounts->get('1-1100');
        $taxPayableAccount = $accounts->get('2-1200'); // PPN Keluaran
        $defaultRevenueAccount = $accounts->get('4-1001'); // Pendapatan Penjualan

        $currency = $invoice->currency ?? 'IDR';
        $exchangeRate = (float) ($invoice->exchange_rate ?? 1);

        $lines = [];
        $totalCredits = 0;
        $totalDebits = 0;

        // Debit: Sales Discount contra-revenue (if discount exists)
        if ($invoice->discount_amount > 0) {
            $discountBase = $this->toBaseCurrency($invoice->discount_amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $accounts->get('4-1003')->id,
                'description' => 'Diskon penjualan '.$invoice->invoice_number,
                'debit' => $discountBase,
                'credit' => 0,
                ...$this->currencyMeta($currency, $invoice->discount_amount, $exchangeRate),
            ];
            $totalDebits += $discountBase;
        }

        // Credit: Revenue accounts (subtotal per item or single entry)
        $revenueByAccount = [];
        foreach ($invoice->items as $item) {
            $accountId = $item->revenue_account_id ?? $defaultRevenueAccount->id;
            if (! isset($revenueByAccount[$accountId])) {
                $revenueByAccount[$accountId] = 0;
            }
            $revenueByAccount[$accountId] += $item->line_total;
        }

        foreach ($revenueByAccount as $accountId => $amount) {
            $revenueBase = $this->toBaseCurrency($amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $accountId,
                'description' => 'Pendapatan '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => $revenueBase,
                ...$this->currencyMeta($currency, $amount, $exchangeRate),
            ];
            $totalCredits += $revenueBase;
        }

        // Credit: Tax Payable (if tax exists)
        if ($invoice->tax_amount > 0 && $taxPayableAccount) {
            $taxBase = $this->toBaseCurrency($invoice->tax_amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $taxPayableAccount->id,
                'description' => 'PPN Keluaran '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => $taxBase,
                ...$this->currencyMeta($currency, $invoice->tax_amount, $exchangeRate),
            ];
            $totalCredits += $taxBase;
        }

        // Debit: AR as balancing figure (guarantees DR == CR despite rounding)
        $arAmount = $totalCredits - $totalDebits;
        array_unshift($lines, [
            'account_id' => $receivableAccount->id,
            'description' => 'Piutang '.$invoice->contact->name,
            'debit' => $arAmount,
            'credit' => 0,
            ...$this->currencyMeta($currency, $invoice->total_amount, $exchangeRate),
        ]);

        $entryDate = $invoice->invoice_date;
        $entry = $this->createEntry([
            'entry_date' => $entryDate instanceof \Carbon\Carbon ? $entryDate->toDateString() : (string) $entryDate,
            'description' => 'Faktur penjualan: '.$invoice->invoice_number,
            'reference' => $invoice->invoice_number,
            'source_type' => JournalEntry::SOURCE_INVOICE,
            'source_id' => $invoice->id,
            'lines' => $lines,
        ], autoPost: true);

        // Only update journal reference - status transition is handled by InvoiceService state machine
        $invoice->update([
            'journal_entry_id' => $entry->id,
            'receivable_account_id' => $receivableAccount->id,
        ]);

        return $entry;
    }

    /**
     * Create journal entry for a bill when posted.
     *
     * In perpetual/hybrid inventory mode, inventory-tracked items debit GRNI
     * (clearing the liability created at GRN time) instead of Expense.
     */
    public function postBill(Bill $bill): JournalEntry
    {
        if ($bill->journal_entry_id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'posting bill',
                'Bill is already posted to journal'
            );
        }

        $bill->loadMissing(['items.product']);

        // Only perpetual mode creates GRN→GRNI journal entries that need clearing
        $inventoryStrategy = $this->policyManager->inventory()->getIdentifier();
        $usesGrni = $inventoryStrategy === 'perpetual';

        // Fail-fast: validate required accounts exist
        $requiredCodes = ['2-1100', '1-1300', '5-1002'];
        if ($usesGrni) {
            $requiredCodes[] = '2-1300'; // GRNI account
        }
        if ($bill->discount_amount > 0) {
            $requiredCodes[] = '5-1003'; // Diskon Pembelian
        }
        $accounts = $this->accountLookup->findByCodesOrFail($requiredCodes, 'posting bill');

        $payableAccount = $bill->payableAccount ?? $accounts->get('2-1100');
        $taxReceivableAccount = $accounts->get('1-1300'); // PPN Masukan
        $defaultExpenseAccount = $accounts->get('5-1002'); // Pembelian
        $grniAccount = $usesGrni ? $accounts->get('2-1300') : null; // GRNI

        $currency = $bill->currency ?? 'IDR';
        $exchangeRate = (float) ($bill->exchange_rate ?? 1);

        $lines = [];
        $totalDebits = 0;
        $totalCredits = 0;

        // Debit: Expense or GRNI accounts (per item)
        $debitByAccount = [];
        foreach ($bill->items as $item) {
            $isInventoryItem = $usesGrni
                && $item->product_id
                && $item->product
                && $item->product->track_inventory;

            // Inventory items clear GRNI; non-inventory items go to Expense
            $accountId = $isInventoryItem
                ? $grniAccount->id
                : ($item->expense_account_id ?? $defaultExpenseAccount->id);

            if (! isset($debitByAccount[$accountId])) {
                $debitByAccount[$accountId] = 0;
            }
            $debitByAccount[$accountId] += $item->line_total;
        }

        foreach ($debitByAccount as $accountId => $amount) {
            $amountBase = $this->toBaseCurrency($amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $accountId,
                'description' => 'Pembelian '.$bill->bill_number,
                'debit' => $amountBase,
                'credit' => 0,
                ...$this->currencyMeta($currency, $amount, $exchangeRate),
            ];
            $totalDebits += $amountBase;
        }

        // Debit: Tax Receivable (if tax exists)
        if ($bill->tax_amount > 0 && $taxReceivableAccount) {
            $taxBase = $this->toBaseCurrency($bill->tax_amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $taxReceivableAccount->id,
                'description' => 'PPN Masukan '.$bill->bill_number,
                'debit' => $taxBase,
                'credit' => 0,
                ...$this->currencyMeta($currency, $bill->tax_amount, $exchangeRate),
            ];
            $totalDebits += $taxBase;
        }

        // Credit: Purchase Discount contra-expense (if discount exists)
        if ($bill->discount_amount > 0) {
            $discountBase = $this->toBaseCurrency($bill->discount_amount, $currency, $exchangeRate);
            $lines[] = [
                'account_id' => $accounts->get('5-1003')->id,
                'description' => 'Diskon pembelian '.$bill->bill_number,
                'debit' => 0,
                'credit' => $discountBase,
                ...$this->currencyMeta($currency, $bill->discount_amount, $exchangeRate),
            ];
            $totalCredits += $discountBase;
        }

        // Credit: AP as balancing figure (guarantees DR == CR despite rounding)
        $apAmount = $totalDebits - $totalCredits;
        $lines[] = [
            'account_id' => $payableAccount->id,
            'description' => 'Utang '.$bill->contact->name,
            'debit' => 0,
            'credit' => $apAmount,
            ...$this->currencyMeta($currency, $bill->total_amount, $exchangeRate),
        ];

        $entryDate = $bill->bill_date;
        $entry = $this->createEntry([
            'entry_date' => $entryDate instanceof \Carbon\Carbon ? $entryDate->toDateString() : (string) $entryDate,
            'description' => 'Faktur pembelian: '.$bill->bill_number,
            'reference' => $bill->bill_number,
            'source_type' => JournalEntry::SOURCE_BILL,
            'source_id' => $bill->id,
            'lines' => $lines,
        ], autoPost: true);

        // Only update journal reference - status transition is handled by BillService state machine
        $bill->update([
            'journal_entry_id' => $entry->id,
            'payable_account_id' => $payableAccount->id,
        ]);

        return $entry;
    }

    /**
     * Create journal entry for a payment.
     *
     * All JE amounts are recorded in base currency (IDR).
     * Uses payment's base_currency_amount when available.
     *
     * Note: This method ONLY creates the journal entry. Payment status updates,
     * payable amount tracking, and event dispatching are handled by PaymentService.
     */
    public function postPayment(Payment $payment): JournalEntry
    {
        $currency = $payment->currency ?? 'IDR';
        $paymentRate = (float) ($payment->exchange_rate ?? 1);

        // Build allocation list for JE generation
        // Multi-allocation: each allocation may target a different document
        $allocations = $payment->relationLoaded('allocations')
            ? $payment->allocations
            : $payment->allocations()->with('allocatable')->get();

        // Determine if FX is involved (any allocation with different rate)
        $hasFx = false;
        if ($currency !== 'IDR') {
            if ($allocations->isNotEmpty()) {
                foreach ($allocations as $alloc) {
                    $docRate = (float) ($alloc->allocatable->exchange_rate ?? $paymentRate);
                    if (abs($paymentRate - $docRate) > 0.0001) {
                        $hasFx = true;
                        break;
                    }
                }
            } else {
                // Legacy single payable
                $payable = $payment->payable;
                if ($payable) {
                    $invoiceRate = (float) ($payable->exchange_rate ?? $paymentRate);
                    $hasFx = abs($paymentRate - $invoiceRate) > 0.0001;
                }
            }
        }

        // Fail-fast: validate required accounts
        $requiredCodes = ['1-1100', '2-1100'];
        if ($hasFx) {
            $requiredCodes[] = config('accounting.default_accounts.foreign_exchange_gain');
            $requiredCodes[] = config('accounting.default_accounts.foreign_exchange_loss');
        }
        $hasPph = $payment->hasPphWithholding();
        if ($hasPph && $payment->pphAccount) {
            $requiredCodes[] = $payment->pphAccount->code;
        }
        $defaultAccounts = $this->accountLookup->findByCodesOrFail(
            array_unique($requiredCodes),
            'posting payment'
        );

        $cashBase = $this->toBaseCurrency($payment->amount, $currency, $paymentRate);
        $currencyMetaPaymentRate = $this->currencyMeta($currency, $payment->amount, $paymentRate);

        $lines = [];
        $totalFxDiff = 0;

        if ($allocations->isNotEmpty()) {
            // Multi-allocation path: per-document AR/AP lines
            foreach ($allocations as $alloc) {
                $doc = $alloc->allocatable;
                $docRate = ($doc && $currency !== 'IDR')
                    ? (float) ($doc->exchange_rate ?? $paymentRate)
                    : $paymentRate;

                $allocArApBase = $this->toBaseCurrency($alloc->amount, $currency, $docRate);
                $allocCashBase = $this->toBaseCurrency($alloc->amount, $currency, $paymentRate);
                $totalFxDiff += ($allocCashBase - $allocArApBase);

                $allocCurrencyMeta = $this->currencyMeta($currency, $alloc->amount, $docRate);

                if ($payment->type === Payment::TYPE_RECEIVE) {
                    $receivableAccount = $defaultAccounts->get('1-1100');
                    if ($doc instanceof Invoice) {
                        $receivableAccount = $doc->receivableAccount ?? $receivableAccount;
                    }

                    $lines[] = [
                        'account_id' => $receivableAccount->id,
                        'description' => 'Pelunasan piutang '.$payment->contact->name,
                        'debit' => 0,
                        'credit' => $allocArApBase,
                        ...$allocCurrencyMeta,
                    ];
                } else {
                    $payableAccount = $defaultAccounts->get('2-1100');
                    if ($doc instanceof Bill) {
                        $payableAccount = $doc->payableAccount ?? $payableAccount;
                    }

                    $lines[] = [
                        'account_id' => $payableAccount->id,
                        'description' => 'Pembayaran utang '.$payment->contact->name,
                        'debit' => $allocArApBase,
                        'credit' => 0,
                        ...$allocCurrencyMeta,
                    ];
                }
            }
        } else {
            // Legacy single-payable path
            $payable = $payment->payable;
            $invoiceRate = $paymentRate;
            if ($payable && $currency !== 'IDR') {
                $invoiceRate = (float) ($payable->exchange_rate ?? $paymentRate);
            }

            $arApBase = $this->toBaseCurrency($payment->amount, $currency, $invoiceRate);
            $totalFxDiff = $cashBase - $arApBase;
            $currencyMetaInvoiceRate = $this->currencyMeta($currency, $payment->amount, $invoiceRate);

            if ($payment->type === Payment::TYPE_RECEIVE) {
                $receivableAccount = $defaultAccounts->get('1-1100');
                if ($payable instanceof Invoice) {
                    $receivableAccount = $payable->receivableAccount ?? $receivableAccount;
                }

                $lines[] = [
                    'account_id' => $receivableAccount->id,
                    'description' => 'Pelunasan piutang '.$payment->contact->name,
                    'debit' => 0,
                    'credit' => $arApBase,
                    ...$currencyMetaInvoiceRate,
                ];
            } else {
                $payableAccount = $defaultAccounts->get('2-1100');
                if ($payable instanceof Bill) {
                    $payableAccount = $payable->payableAccount ?? $payableAccount;
                }

                $lines[] = [
                    'account_id' => $payableAccount->id,
                    'description' => 'Pembayaran utang '.$payment->contact->name,
                    'debit' => $arApBase,
                    'credit' => 0,
                    ...$currencyMetaInvoiceRate,
                ];
            }
        }

        // Cash line (always one consolidated line)
        if ($payment->type === Payment::TYPE_RECEIVE) {
            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Penerimaan dari '.$payment->contact->name,
                'debit' => $cashBase,
                'credit' => 0,
                ...$currencyMetaPaymentRate,
            ];
        } else {
            $cashCredit = $cashBase;
            $pphCredit = 0;
            if ($hasPph) {
                $pphCredit = $payment->pph_amount;
                $cashCredit = $cashBase - $pphCredit;
            }

            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Pembayaran ke '.$payment->contact->name,
                'debit' => 0,
                'credit' => $cashCredit,
                ...$currencyMetaPaymentRate,
            ];

            if ($hasPph && $pphCredit > 0 && $payment->pph_account_id) {
                $lines[] = [
                    'account_id' => $payment->pph_account_id,
                    'description' => 'Pemotongan '.$payment->pph_category->label().' - '.$payment->contact->name,
                    'debit' => 0,
                    'credit' => $pphCredit,
                ];
            }
        }

        // FX gain/loss (aggregated across all allocations)
        $hasFxActual = $currency !== 'IDR' && abs($totalFxDiff) > 0;
        if ($hasFxActual) {
            if ($payment->type === Payment::TYPE_RECEIVE) {
                if ($totalFxDiff > 0) {
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_gain'))->id,
                        'description' => 'Keuntungan selisih kurs '.$payment->payment_number,
                        'debit' => 0,
                        'credit' => abs($totalFxDiff),
                    ];
                } else {
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_loss'))->id,
                        'description' => 'Kerugian selisih kurs '.$payment->payment_number,
                        'debit' => abs($totalFxDiff),
                        'credit' => 0,
                    ];
                }
            } else {
                if ($totalFxDiff > 0) {
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_loss'))->id,
                        'description' => 'Kerugian selisih kurs '.$payment->payment_number,
                        'debit' => abs($totalFxDiff),
                        'credit' => 0,
                    ];
                } else {
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_gain'))->id,
                        'description' => 'Keuntungan selisih kurs '.$payment->payment_number,
                        'debit' => 0,
                        'credit' => abs($totalFxDiff),
                    ];
                }
            }
        }

        $entryDate = $payment->payment_date;

        return $this->createEntry([
            'entry_date' => $entryDate instanceof \Carbon\Carbon ? $entryDate->toDateString() : (string) $entryDate,
            'description' => ($payment->type === Payment::TYPE_RECEIVE ? 'Penerimaan: ' : 'Pembayaran: ').$payment->payment_number,
            'reference' => $payment->payment_number,
            'source_type' => JournalEntry::SOURCE_PAYMENT,
            'source_id' => $payment->id,
            'lines' => $lines,
        ], autoPost: true);
    }
}
