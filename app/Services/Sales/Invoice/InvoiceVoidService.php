<?php

declare(strict_types=1);

namespace App\Services\Sales\Invoice;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\DeliveryOrderServiceInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Domain\Sales\Invoices\InvoiceDomainFactory;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\AuditLog;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles invoice void/cancel with full cascade.
 *
 * Cascades to all child records in safe order:
 * 1. Blocks if Delivered DOs or Completed SRs exist (irreversible real-world events)
 * 2. Reverses Shipped DOs (inventory restore + COGS JE reversal)
 * 3. Cancels Draft/Confirmed DOs
 * 4. Cancels Approved SRs (JE reversal + inventory reversal via SalesReturnService)
 * 5. Cancels Draft/Submitted SRs
 * 6. Voids all active payments
 * 7. Unapplies all DP applications
 * 8. Reverses COGS journal entries (for on_invoice strategy)
 * 9. Reverses the invoice's own AR/Revenue journal entry
 *
 * Extracted from InvoiceService as part of the Coordinator Pattern refactoring.
 * Cascade order and side effects must remain atomic and identical to the original.
 *
 * @see \App\Services\Sales\InvoiceService The coordinator service
 */
class InvoiceVoidService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalServiceInterface $journalService,
        private PaymentServiceInterface $paymentService,
        private InvoiceDomainFactory $domainFactory,
        private DeliveryOrderServiceInterface $deliveryOrderService,
        private SalesReturnServiceInterface $salesReturnService,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Void/cancel posted invoice.
     *
     * @throws StateTransitionException
     * @throws BusinessRuleException
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        return $this->executeInTransaction('void', function () use ($invoice, $reason) {
            // Pessimistic lock to prevent concurrent void/payment operations
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if (! $this->domainFactory->stateMachine($invoice)->canCancel()) {
                throw StateTransitionException::actionNotAvailable(
                    'void',
                    $invoice->status->label()
                );
            }

            // Block if Delivered DOs exist (customer confirmed receipt — irreversible)
            $deliveredDOs = DeliveryOrder::where('invoice_id', $invoice->id)
                ->where('status', DocumentStatus::Delivered)
                ->exists();

            if ($deliveredDOs) {
                throw BusinessRuleException::operationNotAllowed(
                    'membatalkan faktur',
                    'Faktur memiliki surat jalan yang sudah diterima. Batalkan surat jalan terlebih dahulu.'
                );
            }

            // Block if Completed SRs exist (fully processed — requires separate handling)
            $completedSRs = SalesReturn::where('invoice_id', $invoice->id)
                ->where('status', DocumentStatus::Completed)
                ->exists();

            if ($completedSRs) {
                throw BusinessRuleException::operationNotAllowed(
                    'membatalkan faktur',
                    'Faktur memiliki retur penjualan yang sudah selesai diproses.'
                );
            }

            // Reverse Shipped DOs (inventory + COGS JE reversal)
            $cascadeReason = "Faktur dibatalkan: {$reason}";

            $cancelledDeliveryOrders = $this->cascadeDeliveryOrders($invoice->id, $cascadeReason);
            $cancelledSalesReturns = $this->cascadeSalesReturns($invoice->id, $cascadeReason);

            // Void all active payments
            $activePayments = $invoice->payments()
                ->where('is_voided', false)
                ->get();

            foreach ($activePayments as $payment) {
                $this->paymentService->void($payment, $cascadeReason);
            }

            // Unapply all DP applications for this invoice
            $dpApplications = DownPaymentApplication::where('applicable_type', Invoice::class)
                ->where('applicable_id', $invoice->id)
                ->get();

            foreach ($dpApplications as $application) {
                // Reverse journal entry
                if ($application->journal_entry_id && $application->journalEntry) {
                    $this->journalService->reverseEntry($application->journalEntry);
                }

                $dp = DownPayment::query()->lockForUpdate()->findOrFail($application->down_payment_id);
                $dp->applied_amount = max(0, (int) $dp->applied_amount - (int) $application->amount);
                $dp->updateStatus();
                $dp->save();

                $application->delete();
            }

            // Refresh to get latest state after cascade
            $invoice->refresh();

            // Reset paid amount (all payments voided and DPs unapplied)
            $invoice->paid_amount = 0;

            // Mark NSFP as cancelled (number is preserved for DJP audit trail)
            if ($invoice->nsfp_number) {
                $invoice->is_nsfp_cancelled = true;
            }

            $invoice->save();

            // Reverse COGS journal entries (on_invoice strategy creates with source_type=Invoice)
            // Excludes the invoice's own AR/Revenue JE (handled separately below)
            $cogsJournalEntries = JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->where('is_reversed', false)
                ->when($invoice->journal_entry_id, fn ($q) => $q->where('id', '!=', $invoice->journal_entry_id))
                ->get();

            foreach ($cogsJournalEntries as $cogsJe) {
                $this->journalService->reverseEntry(
                    $cogsJe,
                    "Pembatalan HPP faktur: {$invoice->invoice_number}"
                );
            }

            // Reverse invoice's own AR/Revenue journal entry
            if ($invoice->journal_entry_id && $invoice->journalEntry) {
                $this->journalService->reverseEntry($invoice->journalEntry);
            }

            // Transition status (state machine dispatches InvoiceVoided event)
            $invoice->transitionTo(DocumentStatus::Cancelled, $this->getUserId());

            AuditLog::log(AuditLog::ACTION_VOIDED, $invoice, null, [
                'status' => DocumentStatus::Cancelled->value,
                'reason' => $reason,
                'voided_payments' => $activePayments->count(),
                'cancelled_delivery_orders' => $cancelledDeliveryOrders,
                'cancelled_sales_returns' => $cancelledSalesReturns,
                'reversed_cogs_entries' => $cogsJournalEntries->count(),
            ]);

            /** @var Invoice */
            return $this->loadRelations($invoice);
        }, ['invoice_id' => $invoice->id, 'reason' => $reason]);
    }

    /**
     * Collect ids first — Builder::each() chunks by offset, so mutating status
     * while iterating skips rows once the result set shrinks.
     */
    private function cascadeDeliveryOrders(int $invoiceId, string $reason): int
    {
        $shippedIds = DeliveryOrder::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', DocumentStatus::Shipped)
            ->orderBy('id')
            ->pluck('id');

        foreach ($shippedIds as $id) {
            $do = DeliveryOrder::query()->lockForUpdate()->findOrFail($id);
            $this->deliveryOrderService->reverseShipment($do, $reason);
        }

        $openIds = DeliveryOrder::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Confirmed])
            ->orderBy('id')
            ->pluck('id');

        foreach ($openIds as $id) {
            $do = DeliveryOrder::query()->lockForUpdate()->findOrFail($id);
            $do->transitionTo(DocumentStatus::Cancelled, $this->getUserId(), [
                'cancellation_reason' => $reason,
            ]);
        }

        return $shippedIds->count() + $openIds->count();
    }

    private function cascadeSalesReturns(int $invoiceId, string $reason): int
    {
        $approvedIds = SalesReturn::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', DocumentStatus::Approved)
            ->orderBy('id')
            ->pluck('id');

        foreach ($approvedIds as $id) {
            $sr = SalesReturn::query()->lockForUpdate()->findOrFail($id);
            $this->salesReturnService->cancel($sr, $reason);
        }

        $openIds = SalesReturn::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Submitted])
            ->orderBy('id')
            ->pluck('id');

        foreach ($openIds as $id) {
            $sr = SalesReturn::query()->lockForUpdate()->findOrFail($id);
            $sr->transitionTo(DocumentStatus::Cancelled, $this->getUserId(), [
                'cancellation_reason' => $reason,
            ]);
        }

        return $approvedIds->count() + $openIds->count();
    }

    protected function loadRelations(Model $document): Model
    {
        return $document->fresh(['items', 'contact', 'journalEntry.lines.account']);
    }
}
