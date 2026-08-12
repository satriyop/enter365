<?php

declare(strict_types=1);

namespace App\Services\Shared\Payment;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Tax\PphCalculationServiceInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Events\PaymentSent;
use App\Domain\Sales\Events\PaymentReceived;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Models\Shared\PaymentAllocation;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

/**
 * Handles payment creation and allocation.
 *
 * Extracted from PaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Shared\PaymentService The coordinator service
 */
class PaymentCreationService extends BaseService
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private PphCalculationServiceInterface $pphCalculationService,
        private PaymentQueryService $query,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a payment for one or more invoices/bills.
     *
     * @param  array<string, mixed>  $data
     */
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

    /**
     * Create a payment specifically for an invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForInvoice(Invoice $invoice, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Create a payment specifically for a bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForBill(Bill $bill, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'bill_id' => $bill->id,
        ]);
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
        if (! $this->query->canReceivePayment($invoice)) {
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
        if (! $this->query->canReceivePayment($bill)) {
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
}
