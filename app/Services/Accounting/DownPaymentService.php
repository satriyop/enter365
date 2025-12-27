<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DownPayment;
use App\Models\Accounting\DownPaymentApplication;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use Illuminate\Support\Facades\DB;

class DownPaymentService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Create a new down payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DownPayment
    {
        return DB::transaction(function () use ($data) {
            $downPayment = new DownPayment($data);
            $downPayment->dp_number = DownPayment::generateDpNumber($data['type']);
            $downPayment->save();

            // Create journal entry for the down payment
            $this->createDownPaymentJournalEntry($downPayment);

            return $downPayment->fresh(['contact', 'cashAccount', 'journalEntry']);
        });
    }

    /**
     * Update a down payment (only active with no applications).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DownPayment $downPayment, array $data): DownPayment
    {
        if ($downPayment->applications()->exists()) {
            throw new \InvalidArgumentException('Cannot update down payment with existing applications.');
        }

        if ($downPayment->status !== DownPayment::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('Can only update active down payments.');
        }

        return DB::transaction(function () use ($downPayment, $data) {
            // If amount or account changed, reverse old journal and create new
            $needsJournalUpdate = isset($data['amount']) && $data['amount'] !== $downPayment->amount
                || isset($data['cash_account_id']) && $data['cash_account_id'] !== $downPayment->cash_account_id;

            if ($needsJournalUpdate && $downPayment->journalEntry) {
                // Reverse the old journal entry
                $this->journalService->reverseEntry($downPayment->journalEntry);
                $downPayment->journal_entry_id = null;
            }

            $downPayment->fill($data);
            $downPayment->save();

            if ($needsJournalUpdate) {
                $this->createDownPaymentJournalEntry($downPayment);
            }

            return $downPayment->fresh(['contact', 'cashAccount', 'journalEntry']);
        });
    }

    /**
     * Delete a down payment (only if no applications).
     */
    public function delete(DownPayment $downPayment): bool
    {
        if ($downPayment->applications()->exists()) {
            throw new \InvalidArgumentException('Cannot delete down payment with existing applications.');
        }

        return DB::transaction(function () use ($downPayment) {
            // Reverse journal entry if exists
            if ($downPayment->journalEntry) {
                $this->journalService->reverseEntry($downPayment->journalEntry);
            }

            return $downPayment->delete();
        });
    }

    /**
     * Apply down payment to an invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToInvoice(DownPayment $downPayment, Invoice $invoice, array $data): DownPaymentApplication
    {
        if (! $downPayment->canBeApplied()) {
            throw new \InvalidArgumentException('Down payment cannot be applied.');
        }

        if ($downPayment->type !== DownPayment::TYPE_RECEIVABLE) {
            throw new \InvalidArgumentException('Only receivable down payments can be applied to invoices.');
        }

        if ($invoice->contact_id !== $downPayment->contact_id) {
            throw new \InvalidArgumentException('Down payment and invoice must belong to the same contact.');
        }

        $amount = $data['amount'];
        $outstandingAmount = $invoice->getOutstandingAmount();

        if ($amount > $downPayment->getRemainingAmount()) {
            throw new \InvalidArgumentException('Amount exceeds remaining down payment balance.');
        }

        if ($amount > $outstandingAmount) {
            throw new \InvalidArgumentException('Amount exceeds invoice outstanding balance.');
        }

        return DB::transaction(function () use ($downPayment, $invoice, $data, $amount) {
            $application = new DownPaymentApplication([
                'down_payment_id' => $downPayment->id,
                'applicable_type' => Invoice::class,
                'applicable_id' => $invoice->id,
                'amount' => $amount,
                'applied_date' => $data['applied_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $application->save();

            // Create journal entry for application
            $this->createApplicationJournalEntry($application);

            // Update down payment applied amount
            $downPayment->applied_amount += $amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Update invoice paid amount
            $invoice->paid_amount += $amount;
            $invoice->updatePaymentStatus();
            $invoice->save();

            return $application->fresh(['downPayment', 'applicable', 'journalEntry']);
        });
    }

    /**
     * Apply down payment to a bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToBill(DownPayment $downPayment, Bill $bill, array $data): DownPaymentApplication
    {
        if (! $downPayment->canBeApplied()) {
            throw new \InvalidArgumentException('Down payment cannot be applied.');
        }

        if ($downPayment->type !== DownPayment::TYPE_PAYABLE) {
            throw new \InvalidArgumentException('Only payable down payments can be applied to bills.');
        }

        if ($bill->contact_id !== $downPayment->contact_id) {
            throw new \InvalidArgumentException('Down payment and bill must belong to the same contact.');
        }

        $amount = $data['amount'];
        $outstandingAmount = $bill->getOutstandingAmount();

        if ($amount > $downPayment->getRemainingAmount()) {
            throw new \InvalidArgumentException('Amount exceeds remaining down payment balance.');
        }

        if ($amount > $outstandingAmount) {
            throw new \InvalidArgumentException('Amount exceeds bill outstanding balance.');
        }

        return DB::transaction(function () use ($downPayment, $bill, $data, $amount) {
            $application = new DownPaymentApplication([
                'down_payment_id' => $downPayment->id,
                'applicable_type' => Bill::class,
                'applicable_id' => $bill->id,
                'amount' => $amount,
                'applied_date' => $data['applied_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $application->save();

            // Create journal entry for application
            $this->createApplicationJournalEntry($application);

            // Update down payment applied amount
            $downPayment->applied_amount += $amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Update bill paid amount
            $bill->paid_amount += $amount;
            $bill->updatePaymentStatus();
            $bill->save();

            return $application->fresh(['downPayment', 'applicable', 'journalEntry']);
        });
    }

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPaymentApplication $application): bool
    {
        return DB::transaction(function () use ($application) {
            $downPayment = $application->downPayment;
            $applicable = $application->applicable;

            // Reverse journal entry
            if ($application->journalEntry) {
                $this->journalService->reverseEntry($application->journalEntry);
            }

            // Restore down payment applied amount
            $downPayment->applied_amount -= $application->amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Restore document paid amount
            if ($applicable instanceof Invoice || $applicable instanceof Bill) {
                $applicable->paid_amount -= $application->amount;
                $applicable->updatePaymentStatus();
                $applicable->save();
            }

            return $application->delete();
        });
    }

    /**
     * Refund remaining down payment balance.
     *
     * @param  array<string, mixed>  $data
     */
    public function refund(DownPayment $downPayment, array $data): Payment
    {
        if (! $downPayment->canBeRefunded()) {
            throw new \InvalidArgumentException('Down payment cannot be refunded.');
        }

        $refundAmount = $data['amount'] ?? $downPayment->getRemainingAmount();

        if ($refundAmount > $downPayment->getRemainingAmount()) {
            throw new \InvalidArgumentException('Refund amount exceeds remaining balance.');
        }

        return DB::transaction(function () use ($downPayment, $data, $refundAmount) {
            // Create refund payment
            // Receivable DP: we refund TO customer (outgoing for us)
            // Payable DP: vendor refunds TO us (incoming for us)
            $paymentType = $downPayment->isReceivable() ? Payment::TYPE_SEND : Payment::TYPE_RECEIVE;

            $payment = new Payment([
                'payment_number' => Payment::generatePaymentNumber($paymentType),
                'type' => $paymentType,
                'contact_id' => $downPayment->contact_id,
                'payment_date' => $data['refund_date'] ?? now()->toDateString(),
                'amount' => $refundAmount,
                'payment_method' => $data['payment_method'] ?? $downPayment->payment_method,
                'reference' => 'Refund: '.$downPayment->dp_number,
                'notes' => $data['notes'] ?? 'Down payment refund',
                'cash_account_id' => $data['cash_account_id'] ?? $downPayment->cash_account_id,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $payment->save();

            // Update down payment
            $downPayment->refund_payment_id = $payment->id;
            $downPayment->refunded_at = now();
            $downPayment->status = DownPayment::STATUS_REFUNDED;
            $downPayment->save();

            // Create refund journal entry
            $this->createRefundJournalEntry($downPayment, $payment, $refundAmount);

            return $payment;
        });
    }

    /**
     * Cancel a down payment (only if no applications).
     */
    public function cancel(DownPayment $downPayment, ?string $reason = null): DownPayment
    {
        if ($downPayment->applications()->exists()) {
            throw new \InvalidArgumentException('Cannot cancel down payment with existing applications.');
        }

        if ($downPayment->status !== DownPayment::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('Can only cancel active down payments.');
        }

        return DB::transaction(function () use ($downPayment, $reason) {
            // Reverse journal entry
            if ($downPayment->journalEntry) {
                $this->journalService->reverseEntry($downPayment->journalEntry);
            }

            $downPayment->status = DownPayment::STATUS_CANCELLED;
            if ($reason) {
                $downPayment->notes = ($downPayment->notes ? $downPayment->notes."\n" : '').'Cancelled: '.$reason;
            }
            $downPayment->save();

            return $downPayment;
        });
    }

    /**
     * Get available down payments for a contact.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DownPayment>
     */
    public function getAvailableForContact(int $contactId, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return DownPayment::query()
            ->where('contact_id', $contactId)
            ->where('type', $type)
            ->where('status', DownPayment::STATUS_ACTIVE)
            ->whereRaw('applied_amount < amount')
            ->orderBy('dp_date')
            ->get();
    }

    /**
     * Create journal entry for initial down payment receipt.
     */
    private function createDownPaymentJournalEntry(DownPayment $downPayment): void
    {
        // Get DP account based on type
        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            // Fallback: create or find default account
            $dpAccount = $downPayment->cashAccount; // Simplified
        }

        $lines = [];

        if ($downPayment->isReceivable()) {
            // Customer pays us: Dr Cash, Cr Uang Muka Penjualan (liability)
            $lines = [
                [
                    'account_id' => $downPayment->cash_account_id,
                    'debit' => $downPayment->amount,
                    'credit' => 0,
                    'description' => 'Down payment received from '.$downPayment->contact->name,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $downPayment->amount,
                    'description' => 'Down payment liability - '.$downPayment->dp_number,
                ],
            ];
        } else {
            // We pay vendor: Dr Uang Muka Pembelian (asset), Cr Cash
            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $downPayment->amount,
                    'credit' => 0,
                    'description' => 'Down payment advance - '.$downPayment->dp_number,
                ],
                [
                    'account_id' => $downPayment->cash_account_id,
                    'debit' => 0,
                    'credit' => $downPayment->amount,
                    'description' => 'Down payment to '.$downPayment->contact->name,
                ],
            ];
        }

        $journalEntry = $this->journalService->createEntry([
            'entry_date' => $downPayment->dp_date,
            'reference' => $downPayment->dp_number,
            'description' => ($downPayment->isReceivable() ? 'Down payment received: ' : 'Down payment paid: ').$downPayment->dp_number,
            'lines' => $lines,
        ], autoPost: true);

        $downPayment->journal_entry_id = $journalEntry->id;
        $downPayment->save();
    }

    /**
     * Create journal entry for applying down payment.
     */
    private function createApplicationJournalEntry(DownPaymentApplication $application): void
    {
        $downPayment = $application->downPayment;
        $applicable = $application->applicable;

        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            $dpAccount = $downPayment->cashAccount;
        }

        $lines = [];

        if ($downPayment->isReceivable() && $applicable instanceof Invoice) {
            // Apply to invoice: Dr Uang Muka Penjualan, Cr Piutang
            $receivableAccount = $applicable->receivableAccount ?? Account::where('code', '1130')->first();

            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $application->amount,
                    'credit' => 0,
                    'description' => 'DP applied to '.$applicable->invoice_number,
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => $application->amount,
                    'description' => 'Reduce receivable - '.$applicable->invoice_number,
                ],
            ];
        } elseif ($downPayment->isPayable() && $applicable instanceof Bill) {
            // Apply to bill: Dr Hutang, Cr Uang Muka Pembelian
            $payableAccount = $applicable->payableAccount ?? Account::where('code', '2110')->first();

            $lines = [
                [
                    'account_id' => $payableAccount->id,
                    'debit' => $application->amount,
                    'credit' => 0,
                    'description' => 'Reduce payable - '.$applicable->bill_number,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $application->amount,
                    'description' => 'DP applied to '.$applicable->bill_number,
                ],
            ];
        }

        if (! empty($lines)) {
            $journalEntry = $this->journalService->createEntry([
                'entry_date' => $application->applied_date,
                'reference' => $downPayment->dp_number,
                'description' => 'Apply DP '.$downPayment->dp_number.' to '.
                    ($applicable instanceof Invoice ? $applicable->invoice_number : $applicable->bill_number),
                'lines' => $lines,
            ], autoPost: true);

            $application->journal_entry_id = $journalEntry->id;
            $application->save();
        }
    }

    /**
     * Create journal entry for refund.
     */
    private function createRefundJournalEntry(DownPayment $downPayment, Payment $payment, int $refundAmount): void
    {
        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            $dpAccount = $downPayment->cashAccount;
        }

        $lines = [];

        if ($downPayment->isReceivable()) {
            // Refund to customer: Dr Uang Muka Penjualan, Cr Cash
            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $refundAmount,
                    'credit' => 0,
                    'description' => 'DP refund - '.$downPayment->dp_number,
                ],
                [
                    'account_id' => $payment->cash_account_id,
                    'debit' => 0,
                    'credit' => $refundAmount,
                    'description' => 'Refund to '.$downPayment->contact->name,
                ],
            ];
        } else {
            // Refund from vendor: Dr Cash, Cr Uang Muka Pembelian
            $lines = [
                [
                    'account_id' => $payment->cash_account_id,
                    'debit' => $refundAmount,
                    'credit' => 0,
                    'description' => 'Refund from '.$downPayment->contact->name,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $refundAmount,
                    'description' => 'DP refund - '.$downPayment->dp_number,
                ],
            ];
        }

        $journalEntry = $this->journalService->createEntry([
            'entry_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => 'Down payment refund: '.$downPayment->dp_number,
            'lines' => $lines,
        ], autoPost: true);

        $payment->journal_entry_id = $journalEntry->id;
        $payment->save();
    }
}
