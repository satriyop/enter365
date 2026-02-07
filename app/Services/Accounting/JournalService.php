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

        // Determine if there is a payable with a different rate (IS-H4: realized FX)
        $payable = $payment->payable;
        $invoiceRate = $paymentRate;
        if ($payable && $currency !== 'IDR') {
            $invoiceRate = (float) ($payable->exchange_rate ?? 1);
        }

        // Fail-fast: validate required accounts
        $requiredCodes = ['1-1100', '2-1100'];
        $hasFx = $currency !== 'IDR' && abs($paymentRate - $invoiceRate) > 0.0001;
        if ($hasFx) {
            $requiredCodes[] = config('accounting.default_accounts.foreign_exchange_gain');
            $requiredCodes[] = config('accounting.default_accounts.foreign_exchange_loss');
        }
        $defaultAccounts = $this->accountLookup->findByCodesOrFail(
            array_unique($requiredCodes),
            'posting payment'
        );

        // Cash amount at payment rate, AR/AP amount at invoice rate
        $cashBase = $this->toBaseCurrency($payment->amount, $currency, $paymentRate);
        $arApBase = $this->toBaseCurrency($payment->amount, $currency, $invoiceRate);
        $fxDiff = $cashBase - $arApBase; // positive = gain for receive, loss for send

        $currencyMetaPaymentRate = $this->currencyMeta($currency, $payment->amount, $paymentRate);
        $currencyMetaInvoiceRate = $this->currencyMeta($currency, $payment->amount, $invoiceRate);

        $lines = [];

        if ($payment->type === Payment::TYPE_RECEIVE) {
            $receivableAccount = $defaultAccounts->get('1-1100');
            if ($payment->payable_type === Invoice::class && $payable) {
                $receivableAccount = $payable->receivableAccount ?? $receivableAccount;
            }

            // Dr Cash/Bank at payment rate
            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Penerimaan dari '.$payment->contact->name,
                'debit' => $cashBase,
                'credit' => 0,
                ...$currencyMetaPaymentRate,
            ];

            // Cr AR at invoice rate
            $lines[] = [
                'account_id' => $receivableAccount->id,
                'description' => 'Pelunasan piutang '.$payment->contact->name,
                'debit' => 0,
                'credit' => $arApBase,
                ...$currencyMetaInvoiceRate,
            ];

            // FX gain/loss line (IS-H4)
            if ($hasFx) {
                if ($fxDiff > 0) {
                    // FX Gain: Cash > AR → credit gain account
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_gain'))->id,
                        'description' => 'Keuntungan selisih kurs '.$payment->payment_number,
                        'debit' => 0,
                        'credit' => abs($fxDiff),
                    ];
                } else {
                    // FX Loss: Cash < AR → debit loss account
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_loss'))->id,
                        'description' => 'Kerugian selisih kurs '.$payment->payment_number,
                        'debit' => abs($fxDiff),
                        'credit' => 0,
                    ];
                }
            }
        } else {
            $payableAccount = $defaultAccounts->get('2-1100');
            if ($payment->payable_type === Bill::class && $payable) {
                $payableAccount = $payable->payableAccount ?? $payableAccount;
            }

            // Dr AP at invoice rate
            $lines[] = [
                'account_id' => $payableAccount->id,
                'description' => 'Pembayaran utang '.$payment->contact->name,
                'debit' => $arApBase,
                'credit' => 0,
                ...$currencyMetaInvoiceRate,
            ];

            // Cr Cash/Bank at payment rate
            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Pembayaran ke '.$payment->contact->name,
                'debit' => 0,
                'credit' => $cashBase,
                ...$currencyMetaPaymentRate,
            ];

            // FX gain/loss for bill payment (mirrored from receive)
            // For TYPE_SEND: if payment rate > invoice rate, we pay MORE cash → FX Loss
            if ($hasFx) {
                if ($fxDiff > 0) {
                    // Cash > AP → paying more → FX Loss for sender
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_loss'))->id,
                        'description' => 'Kerugian selisih kurs '.$payment->payment_number,
                        'debit' => abs($fxDiff),
                        'credit' => 0,
                    ];
                } else {
                    // Cash < AP → paying less → FX Gain for sender
                    $lines[] = [
                        'account_id' => $defaultAccounts->get(config('accounting.default_accounts.foreign_exchange_gain'))->id,
                        'description' => 'Keuntungan selisih kurs '.$payment->payment_number,
                        'debit' => 0,
                        'credit' => abs($fxDiff),
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
