<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Payment;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Create a journal entry with lines.
     *
     * @param array{
     *     entry_date: string,
     *     description: string,
     *     reference?: string,
     *     source_type?: string,
     *     source_id?: int,
     *     lines: array<array{account_id: int, debit?: int, credit?: int, description?: string}>
     * } $data
     */
    public function createEntry(array $data, bool $autoPost = false): JournalEntry
    {
        return DB::transaction(function () use ($data, $autoPost) {
            // Find or create fiscal period
            $fiscalPeriod = FiscalPeriod::current();

            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'source_type' => $data['source_type'] ?? JournalEntry::SOURCE_MANUAL,
                'source_id' => $data['source_id'] ?? null,
                'fiscal_period_id' => $fiscalPeriod?->id,
                'is_posted' => false,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['lines'] as $lineData) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $lineData['account_id'],
                    'description' => $lineData['description'] ?? null,
                    'debit' => $lineData['debit'] ?? 0,
                    'credit' => $lineData['credit'] ?? 0,
                ]);
            }

            if ($autoPost) {
                $this->postEntry($entry);
            }

            return $entry->fresh(['lines', 'lines.account']);
        });
    }

    /**
     * Validate and post a journal entry.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        if ($entry->is_posted) {
            throw new \InvalidArgumentException('Journal entry is already posted.');
        }

        if (! $entry->isBalanced()) {
            throw new \InvalidArgumentException(
                'Journal entry is not balanced. Debit: '.$entry->getTotalDebit().', Credit: '.$entry->getTotalCredit()
            );
        }

        if ($entry->lines()->count() < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least two lines.');
        }

        // Check fiscal period
        if ($entry->fiscalPeriod && $entry->fiscalPeriod->is_closed) {
            throw new \InvalidArgumentException('Cannot post to a closed fiscal period.');
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
            throw new \InvalidArgumentException('Cannot reverse an unposted journal entry.');
        }

        if ($entry->is_reversed) {
            throw new \InvalidArgumentException('Journal entry is already reversed.');
        }

        return DB::transaction(function () use ($entry, $description) {
            // Create reversal entry with swapped debits/credits
            $reversalLines = [];
            foreach ($entry->lines as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'description' => $line->description,
                    'debit' => $line->credit, // Swap
                    'credit' => $line->debit, // Swap
                ];
            }

            $reversalEntry = $this->createEntry([
                'entry_date' => now()->toDateString(),
                'description' => $description ?? 'Reversal of '.$entry->entry_number,
                'reference' => $entry->entry_number,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'lines' => $reversalLines,
            ], autoPost: true);

            // Update reversal relationships
            $reversalEntry->update(['reversal_of_id' => $entry->id]);
            $entry->update([
                'is_reversed' => true,
                'reversed_by_id' => $reversalEntry->id,
            ]);

            return $reversalEntry->fresh(['lines', 'lines.account']);
        });
    }

    /**
     * Create journal entry for an invoice when posted.
     */
    public function postInvoice(Invoice $invoice): JournalEntry
    {
        if ($invoice->journal_entry_id) {
            throw new \InvalidArgumentException('Invoice is already posted to journal.');
        }

        $receivableAccount = $invoice->receivableAccount
            ?? Account::where('code', '1-1100')->first(); // Piutang Usaha

        $taxPayableAccount = Account::where('code', '2-1200')->first(); // PPN Keluaran
        $defaultRevenueAccount = Account::where('code', '4-1001')->first(); // Pendapatan Penjualan

        $lines = [];

        // Debit: Accounts Receivable (total amount including tax)
        $lines[] = [
            'account_id' => $receivableAccount->id,
            'description' => 'Piutang '.$invoice->contact->name,
            'debit' => $invoice->total_amount,
            'credit' => 0,
        ];

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
            $lines[] = [
                'account_id' => $accountId,
                'description' => 'Pendapatan '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => $amount,
            ];
        }

        // Credit: Tax Payable (if tax exists)
        if ($invoice->tax_amount > 0 && $taxPayableAccount) {
            $lines[] = [
                'account_id' => $taxPayableAccount->id,
                'description' => 'PPN Keluaran '.$invoice->invoice_number,
                'debit' => 0,
                'credit' => $invoice->tax_amount,
            ];
        }

        $entry = $this->createEntry([
            'entry_date' => $invoice->invoice_date->toDateString(),
            'description' => 'Faktur penjualan: '.$invoice->invoice_number,
            'reference' => $invoice->invoice_number,
            'source_type' => JournalEntry::SOURCE_INVOICE,
            'source_id' => $invoice->id,
            'lines' => $lines,
        ], autoPost: true);

        $invoice->update([
            'journal_entry_id' => $entry->id,
            'receivable_account_id' => $receivableAccount->id,
            'status' => Invoice::STATUS_SENT,
        ]);

        return $entry;
    }

    /**
     * Create journal entry for a bill when posted.
     */
    public function postBill(Bill $bill): JournalEntry
    {
        if ($bill->journal_entry_id) {
            throw new \InvalidArgumentException('Bill is already posted to journal.');
        }

        $payableAccount = $bill->payableAccount
            ?? Account::where('code', '2-1100')->first(); // Utang Usaha

        $taxReceivableAccount = Account::where('code', '1-1300')->first(); // PPN Masukan
        $defaultExpenseAccount = Account::where('code', '5-1002')->first(); // Pembelian

        $lines = [];

        // Debit: Expense accounts (per item)
        $expenseByAccount = [];
        foreach ($bill->items as $item) {
            $accountId = $item->expense_account_id ?? $defaultExpenseAccount->id;
            if (! isset($expenseByAccount[$accountId])) {
                $expenseByAccount[$accountId] = 0;
            }
            $expenseByAccount[$accountId] += $item->line_total;
        }

        foreach ($expenseByAccount as $accountId => $amount) {
            $lines[] = [
                'account_id' => $accountId,
                'description' => 'Pembelian '.$bill->bill_number,
                'debit' => $amount,
                'credit' => 0,
            ];
        }

        // Debit: Tax Receivable (if tax exists)
        if ($bill->tax_amount > 0 && $taxReceivableAccount) {
            $lines[] = [
                'account_id' => $taxReceivableAccount->id,
                'description' => 'PPN Masukan '.$bill->bill_number,
                'debit' => $bill->tax_amount,
                'credit' => 0,
            ];
        }

        // Credit: Accounts Payable (total amount)
        $lines[] = [
            'account_id' => $payableAccount->id,
            'description' => 'Utang '.$bill->contact->name,
            'debit' => 0,
            'credit' => $bill->total_amount,
        ];

        $entry = $this->createEntry([
            'entry_date' => $bill->bill_date->toDateString(),
            'description' => 'Faktur pembelian: '.$bill->bill_number,
            'reference' => $bill->bill_number,
            'source_type' => JournalEntry::SOURCE_BILL,
            'source_id' => $bill->id,
            'lines' => $lines,
        ], autoPost: true);

        $bill->update([
            'journal_entry_id' => $entry->id,
            'payable_account_id' => $payableAccount->id,
            'status' => Bill::STATUS_RECEIVED,
        ]);

        return $entry;
    }

    /**
     * Create journal entry for a payment.
     */
    public function postPayment(Payment $payment): JournalEntry
    {
        if ($payment->journal_entry_id) {
            throw new \InvalidArgumentException('Payment is already posted to journal.');
        }

        $lines = [];

        if ($payment->type === Payment::TYPE_RECEIVE) {
            // Receiving payment from customer
            $receivableAccount = Account::where('code', '1-1100')->first();

            // Get the invoice's receivable account if linked
            if ($payment->payable_type === Invoice::class && $payment->payable) {
                $receivableAccount = $payment->payable->receivableAccount ?? $receivableAccount;
            }

            // Debit: Cash/Bank
            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Penerimaan dari '.$payment->contact->name,
                'debit' => $payment->amount,
                'credit' => 0,
            ];

            // Credit: Accounts Receivable
            $lines[] = [
                'account_id' => $receivableAccount->id,
                'description' => 'Pelunasan piutang '.$payment->contact->name,
                'debit' => 0,
                'credit' => $payment->amount,
            ];
        } else {
            // Sending payment to supplier
            $payableAccount = Account::where('code', '2-1100')->first();

            // Get the bill's payable account if linked
            if ($payment->payable_type === Bill::class && $payment->payable) {
                $payableAccount = $payment->payable->payableAccount ?? $payableAccount;
            }

            // Debit: Accounts Payable
            $lines[] = [
                'account_id' => $payableAccount->id,
                'description' => 'Pembayaran utang '.$payment->contact->name,
                'debit' => $payment->amount,
                'credit' => 0,
            ];

            // Credit: Cash/Bank
            $lines[] = [
                'account_id' => $payment->cash_account_id,
                'description' => 'Pembayaran ke '.$payment->contact->name,
                'debit' => 0,
                'credit' => $payment->amount,
            ];
        }

        $entry = $this->createEntry([
            'entry_date' => $payment->payment_date->toDateString(),
            'description' => ($payment->type === Payment::TYPE_RECEIVE ? 'Penerimaan: ' : 'Pembayaran: ').$payment->payment_number,
            'reference' => $payment->payment_number,
            'source_type' => JournalEntry::SOURCE_PAYMENT,
            'source_id' => $payment->id,
            'lines' => $lines,
        ], autoPost: true);

        $payment->update(['journal_entry_id' => $entry->id]);

        // Update invoice/bill paid amount
        if ($payment->payable) {
            $payable = $payment->payable;
            $payable->paid_amount += $payment->amount;
            $payable->updatePaymentStatus();
            $payable->save();
        }

        return $entry;
    }

    /**
     * Void a payment and reverse its journal entry.
     */
    public function voidPayment(Payment $payment): void
    {
        if ($payment->is_voided) {
            throw new \InvalidArgumentException('Payment is already voided.');
        }

        DB::transaction(function () use ($payment) {
            // Reverse the journal entry
            if ($payment->journalEntry) {
                $this->reverseEntry($payment->journalEntry, 'Pembatalan: '.$payment->payment_number);
            }

            // Revert the paid amount on invoice/bill
            if ($payment->payable) {
                $payable = $payment->payable;
                $payable->paid_amount = max(0, $payable->paid_amount - $payment->amount);
                $payable->updatePaymentStatus();
                $payable->save();
            }

            $payment->update(['is_voided' => true]);
        });
    }
}
