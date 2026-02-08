<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Contracts\Tax\PphCalculationServiceInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Events\PaymentSent;
use App\Domain\Sales\Events\PaymentReceived;
use App\Domain\Sales\Events\PaymentVoided;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Enums\DocumentStatus;
use App\Models\Core\AuditLog;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Models\Shared\PaymentAllocation;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

class PaymentService extends BaseService implements PaymentServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private PphCalculationServiceInterface $pphCalculationService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function create(array $data): Payment
    {
        return $this->executeInTransaction('create', function () use ($data) {
            // Normalize legacy single-document format to allocations array
            $allocations = $this->normalizeAllocations($data);

            // Lock and validate each payable
            $payables = $this->lockAndValidateAllocations($allocations, $data['amount']);

            // Set legacy payable_type/payable_id from first allocation for backward compat
            $payableType = null;
            $payableId = null;
            $firstPayable = null;
            if ($allocations->isNotEmpty()) {
                $first = $allocations->first();
                $payableType = $first['model_class'];
                $payableId = $first['allocatable_id'];
                $firstPayable = $payables->first();
            }

            // Capture currency info from first payable (or data)
            $currency = $data['currency'] ?? 'IDR';
            $exchangeRate = (float) ($data['exchange_rate'] ?? 1);
            if ($firstPayable) {
                /** @var Invoice|Bill $firstPayable */
                $currency = $firstPayable->currency ?? 'IDR';
                $exchangeRate = isset($data['exchange_rate'])
                    ? (float) $data['exchange_rate']
                    : (float) ($firstPayable->exchange_rate ?? 1);
            }

            $amount = $data['amount'];
            $baseCurrencyAmount = ($currency !== 'IDR' && $exchangeRate > 0)
                ? (int) round($amount * $exchangeRate)
                : $amount;

            // Calculate PPh withholding for bill payments
            $pphData = [];
            if ($firstPayable instanceof Bill && config('accounting.pph.enabled')) {
                $pphCategoryOverride = isset($data['pph_category'])
                    ? \App\Enums\PphCategory::tryFrom($data['pph_category'])
                    : null;
                $pphRateOverride = isset($data['pph_rate']) ? (float) $data['pph_rate'] : null;

                $shouldWithhold = $data['pph_withhold'] ?? true;

                if ($shouldWithhold) {
                    $pphResult = $this->pphCalculationService->calculateForBillPayment(
                        $firstPayable,
                        $amount,
                        $pphCategoryOverride,
                        $pphRateOverride
                    );

                    if ($pphResult) {
                        $pphAccount = \App\Models\Accounting\Account::where('code', $pphResult->accountCode)->first();
                        $pphData = [
                            'pph_category' => $pphResult->category->value,
                            'pph_rate' => $pphResult->rate,
                            'pph_base_amount' => $pphResult->baseAmount,
                            'pph_amount' => $pphResult->pphAmount,
                            'pph_account_id' => $pphAccount?->id,
                        ];
                    }
                }
            }

            // Remove allocation keys from data before creating payment
            $cleanData = collect($data)
                ->except(['invoice_id', 'bill_id', 'allocations', 'pph_withhold'])
                ->toArray();

            // Create payment record
            $payment = Payment::create([
                ...$cleanData,
                ...$pphData,
                'payment_number' => Payment::generatePaymentNumber($data['type']),
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency_amount' => $baseCurrencyAmount,
                'created_by' => $this->getUserId(),
            ]);

            // Create allocation records
            foreach ($allocations as $allocation) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'allocatable_type' => $allocation['allocatable_type'],
                    'allocatable_id' => $allocation['allocatable_id'],
                    'amount' => $allocation['amount'],
                ]);
            }

            // Create journal entry
            $payment->load('allocations.allocatable');
            $journalEntry = $this->journalService->postPayment($payment);
            $payment->update(['journal_entry_id' => $journalEntry->id]);

            // Update each payable's amounts and status
            foreach ($allocations as $allocation) {
                $payable = $payables->get($allocation['allocatable_id']);
                if ($payable) {
                    $this->updatePayableAfterPayment($payable, $payment, $allocation['amount']);
                }
            }

            // Dispatch PPh withholding event
            if ($payment->hasPphWithholding()) {
                Event::dispatch(\App\Domain\Tax\Events\PphWithheld::fromPayment(
                    $payment,
                    $this->getUserId() ?? $payment->created_by
                ));
            }

            return $payment->load(['contact', 'cashAccount', 'journalEntry.lines.account', 'allocations']);
        }, ['type' => $data['type'], 'amount' => $data['amount']]);
    }

    public function createForInvoice(Invoice $invoice, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function createForBill(Bill $bill, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'bill_id' => $bill->id,
        ]);
    }

    public function void(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->is_voided) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'membatalkan pembayaran',
                'Pembayaran sudah dibatalkan'
            );
        }

        return $this->executeInTransaction('void', function () use ($payment, $reason) {
            // Load allocations if not already loaded
            $payment->loadMissing('allocations.allocatable');

            // Lock all payables via allocations
            $payables = collect();
            foreach ($payment->allocations as $allocation) {
                $payable = $allocation->allocatable;
                if ($payable) {
                    $locked = $payable::lockForUpdate()->find($payable->getKey());
                    $payables->put($allocation->id, $locked);
                }
            }

            // Fall back to legacy payable if no allocations
            if ($payables->isEmpty() && $payment->payable) {
                $payable = $payment->payable;
                $locked = $payable::lockForUpdate()->find($payable->getKey());
                $payables->put('legacy', $locked);
            }

            // Reverse journal entry
            if ($payment->journalEntry) {
                $this->journalService->reverseEntry(
                    $payment->journalEntry,
                    "Pembatalan pembayaran: {$payment->payment_number}"
                );
            }

            // Update payment as voided
            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $this->getUserId(),
                'void_reason' => $reason,
            ]);

            // Reverse payable amounts and status via allocations
            if ($payment->allocations->isNotEmpty()) {
                foreach ($payment->allocations as $allocation) {
                    $payable = $payables->get($allocation->id);
                    if ($payable) {
                        $this->updatePayableAfterVoid($payable, $allocation->amount);
                    }
                }
            } elseif ($payables->has('legacy')) {
                // Legacy path: single payable
                $this->updatePayableAfterVoid($payables->get('legacy'), $payment->amount);
            }

            // Reverse PPh withheld amount on Bill
            if ($payment->hasPphWithholding()) {
                $bill = $payables->first(fn ($p) => $p instanceof Bill);
                if ($bill) {
                    $bill->pph_withheld_amount = max(0, $bill->pph_withheld_amount - $payment->pph_amount);
                    $bill->save();
                }
            }

            AuditLog::log(AuditLog::ACTION_VOIDED, $payment, null, [
                'void_reason' => $reason,
                'amount' => $payment->amount,
            ]);

            // Dispatch void event
            Event::dispatch(new PaymentVoided(
                invoiceId: $payment->payable_type === Invoice::class ? $payment->payable_id : null,
                paymentId: $payment->id,
                amount: $payment->amount,
                currency: 'IDR',
                customerId: $payment->contact_id,
                userId: $this->getUserId() ?? $payment->created_by,
                voidedAt: now(),
                reason: $reason
            ));

            return $payment->fresh(['contact', 'cashAccount']);
        }, ['payment_id' => $payment->id]);
    }

    public function getForInvoice(Invoice $invoice): Collection
    {
        return Payment::query()
            ->where(function ($q) use ($invoice) {
                // Legacy path
                $q->where(function ($q2) use ($invoice) {
                    $q2->where('payable_type', Invoice::class)
                        ->where('payable_id', $invoice->id);
                })
                // Multi-allocation path
                    ->orWhereHas('allocations', function ($q2) use ($invoice) {
                        $q2->where('allocatable_type', 'invoice')
                            ->where('allocatable_id', $invoice->id);
                    });
            })
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function getForBill(Bill $bill): Collection
    {
        return Payment::query()
            ->where(function ($q) use ($bill) {
                $q->where(function ($q2) use ($bill) {
                    $q2->where('payable_type', Bill::class)
                        ->where('payable_id', $bill->id);
                })
                    ->orWhereHas('allocations', function ($q2) use ($bill) {
                        $q2->where('allocatable_type', 'bill')
                            ->where('allocatable_id', $bill->id);
                    });
            })
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function getOutstandingAmount(Model $payable): int
    {
        if (! method_exists($payable, 'getOutstandingAmount')) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan model sebagai payable',
                'Model does not support outstanding amount calculation'
            );
        }

        return $payable->getOutstandingAmount();
    }

    public function canReceivePayment(Model $payable): bool
    {
        if ($payable instanceof Invoice) {
            return in_array($payable->status, [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Overdue,
            ]);
        }

        if ($payable instanceof Bill) {
            return in_array($payable->status, [
                DocumentStatus::Received,
                DocumentStatus::Partial,
                DocumentStatus::Overdue,
            ]);
        }

        return false;
    }

    /**
     * Normalize legacy invoice_id/bill_id to allocations collection.
     *
     * @return Collection<int, array{allocatable_type: string, allocatable_id: int, amount: int, model_class: string}>
     */
    private function normalizeAllocations(array &$data): Collection
    {
        // If explicit allocations provided, use them
        if (isset($data['allocations']) && is_array($data['allocations'])) {
            /** @var array<int, array{allocatable_type: string, allocatable_id: int, amount: int}> $allocations */
            $allocations = $data['allocations'];

            return collect($allocations)->map(function (array $alloc) {
                $modelClass = $this->resolveModelClass($alloc['allocatable_type']);

                return [
                    'allocatable_type' => $alloc['allocatable_type'],
                    'allocatable_id' => (int) $alloc['allocatable_id'],
                    'amount' => (int) $alloc['amount'],
                    'model_class' => $modelClass,
                ];
            });
        }

        // Legacy: single invoice_id
        if (isset($data['invoice_id'])) {
            $invoiceId = (int) $data['invoice_id'];
            unset($data['invoice_id']);

            return collect([[
                'allocatable_type' => 'invoice',
                'allocatable_id' => $invoiceId,
                'amount' => (int) $data['amount'],
                'model_class' => Invoice::class,
            ]]);
        }

        // Legacy: single bill_id
        if (isset($data['bill_id'])) {
            $billId = (int) $data['bill_id'];
            unset($data['bill_id']);

            return collect([[
                'allocatable_type' => 'bill',
                'allocatable_id' => $billId,
                'amount' => (int) $data['amount'],
                'model_class' => Bill::class,
            ]]);
        }

        // No payable — standalone payment
        return collect();
    }

    /**
     * Resolve morph alias to model class.
     *
     * @return class-string
     */
    private function resolveModelClass(string $type): string
    {
        return match ($type) {
            'invoice' => Invoice::class,
            'bill' => Bill::class,
            default => throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'alokasi pembayaran',
                "Tipe dokumen tidak dikenal: {$type}"
            ),
        };
    }

    /**
     * Lock each payable and validate allocation amounts.
     *
     * @param  Collection<int, array{allocatable_type: string, allocatable_id: int, amount: int, model_class: string}>  $allocations
     * @return Collection<int, Invoice|Bill> Keyed by payable ID
     */
    private function lockAndValidateAllocations(Collection $allocations, int $totalAmount): Collection
    {
        if ($allocations->isEmpty()) {
            return collect();
        }

        // Validate total allocations match payment amount
        $allocatedTotal = $allocations->sum('amount');
        if ($allocatedTotal !== $totalAmount) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'alokasi pembayaran',
                "Total alokasi ({$allocatedTotal}) harus sama dengan jumlah pembayaran ({$totalAmount})."
            );
        }

        $payables = collect();

        foreach ($allocations as $allocation) {
            /** @var class-string<Invoice|Bill> $modelClass */
            $modelClass = $allocation['model_class'];
            $payable = $modelClass::lockForUpdate()->findOrFail($allocation['allocatable_id']);

            // Validate status
            if ($payable instanceof Invoice) {
                $this->validateInvoicePayment($payable, $allocation['amount']);
            } elseif ($payable instanceof Bill) {
                $this->validateBillPayment($payable, $allocation['amount']);
            }

            $payables->put($allocation['allocatable_id'], $payable);
        }

        return $payables;
    }

    /**
     * Validate that an invoice can receive the payment amount.
     */
    private function validateInvoicePayment(Invoice $invoice, int $amount): void
    {
        if (! $this->canReceivePayment($invoice)) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Invoice',
                'menerima pembayaran',
                $invoice->status->value,
                'approved atau partial'
            );
        }

        if ($amount > $invoice->getOutstandingAmount()) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah pembayaran',
                $amount,
                $invoice->getOutstandingAmount(),
                'exceeds'
            );
        }
    }

    /**
     * Validate that a bill can receive the payment amount.
     */
    private function validateBillPayment(Bill $bill, int $amount): void
    {
        if (! $this->canReceivePayment($bill)) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Bill',
                'menerima pembayaran',
                $bill->status->value,
                'approved atau partial'
            );
        }

        if ($amount > $bill->getOutstandingAmount()) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah pembayaran',
                $amount,
                $bill->getOutstandingAmount(),
                'exceeds'
            );
        }
    }

    /**
     * Update payable (invoice/bill) after a payment allocation.
     */
    private function updatePayableAfterPayment(Model $payable, Payment $payment, int $allocationAmount): void
    {
        /** @var Invoice|Bill $payable */
        $previousPaidAmount = $payable->paid_amount;
        $payable->paid_amount += $allocationAmount;

        // Track PPh withheld on bills (only for first/primary payable)
        if ($payable instanceof Bill && $payment->hasPphWithholding() && $payable->id === $payment->payable_id) {
            $payable->pph_withheld_amount += $payment->pph_amount;
        }

        $payable->save();

        // Dispatch payment events
        if ($payment->type === Payment::TYPE_RECEIVE && $payable instanceof Invoice) {
            Event::dispatch(PaymentReceived::fromPayment(
                invoice: $payable,
                paymentId: $payment->id,
                amount: $allocationAmount,
                userId: $this->getUserId() ?? $payment->created_by
            ));
        } elseif ($payment->type === Payment::TYPE_SEND && $payable instanceof Bill) {
            Event::dispatch(PaymentSent::fromPayment(
                bill: $payable,
                paymentId: $payment->id,
                amount: $allocationAmount,
                userId: $this->getUserId() ?? $payment->created_by
            ));
        }

        // Transition status based on payment
        $this->transitionPayableStatus($payable, $previousPaidAmount);
    }

    /**
     * Update payable after a payment void (per-allocation amount).
     */
    private function updatePayableAfterVoid(Model $payable, int $allocationAmount): void
    {
        /** @var Invoice|Bill $payable */
        $payable->paid_amount = max(0, $payable->paid_amount - $allocationAmount);
        $payable->save();

        // Revert status if needed
        $this->revertPayableStatus($payable);
    }

    /**
     * Transition payable status after payment.
     */
    private function transitionPayableStatus(Model $payable, int $previousPaidAmount): void
    {
        /** @var Invoice|Bill $payable */
        $isFullyPaid = $payable->paid_amount >= $payable->total_amount;
        $targetStatus = $isFullyPaid ? DocumentStatus::Paid : DocumentStatus::Partial;

        if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
            $payable->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }

        // Dispatch fully paid events
        if ($isFullyPaid && $previousPaidAmount < $payable->total_amount) {
            if ($payable instanceof Invoice) {
                Event::dispatch(InvoiceFullyPaid::fromInvoice($payable, $this->getUserId()));
            } elseif ($payable instanceof Bill) {
                Event::dispatch(BillFullyPaid::fromBill($payable, $this->getUserId()));
            }
        }
    }

    /**
     * Revert payable status after void.
     */
    private function revertPayableStatus(Model $payable): void
    {
        /** @var Invoice|Bill $payable */
        if ($payable->paid_amount <= 0) {
            $targetStatus = $payable instanceof Invoice
                ? DocumentStatus::Sent
                : DocumentStatus::Received;
        } else {
            $targetStatus = DocumentStatus::Partial;
        }

        if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
            $payable->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }
    }
}
