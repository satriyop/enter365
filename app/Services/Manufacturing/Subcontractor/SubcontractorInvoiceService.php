<?php

declare(strict_types=1);

namespace App\Services\Manufacturing\Subcontractor;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Shared\SubcontractorInvoice;
use App\Services\Base\BaseService;

/**
 * Handles subcontractor invoice lifecycle and bill conversion.
 *
 * Extracted from SubcontractorService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Manufacturing\SubcontractorService The coordinator service
 */
class SubcontractorInvoiceService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create invoice for subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(SubcontractorWorkOrder $scWo, array $data): SubcontractorInvoice
    {
        if (! $scWo->canCreateInvoice()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'SC WO',
                'membuat invoice',
                $scWo->status->value,
                'dalam proses atau selesai'
            );
        }

        $grossAmount = $data['gross_amount'];
        $remaining = $scWo->getRemainingInvoiceableAmount();

        if ($grossAmount > $remaining) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                "Jumlah invoice untuk {$scWo->name}",
                $grossAmount,
                $remaining,
                'exceeds'
            );
        }

        return $this->executeInTransaction('create_invoice', function () use ($scWo, $data) {
            // Calculate retention from gross amount
            $grossAmount = $data['gross_amount'];
            $retentionHeld = (int) round($grossAmount * ((float) $scWo->retention_percent / 100));
            $otherDeductions = $data['other_deductions'] ?? 0;
            $netAmount = $grossAmount - $retentionHeld - $otherDeductions;

            $invoice = SubcontractorInvoice::create([
                'subcontractor_work_order_id' => $scWo->id,
                'subcontractor_id' => $scWo->subcontractor_id,
                'invoice_date' => $data['invoice_date'] ?? now(),
                'due_date' => $data['due_date'] ?? now()->addDays(30),
                'gross_amount' => $grossAmount,
                'retention_held' => $retentionHeld,
                'other_deductions' => $otherDeductions,
                'net_amount' => $netAmount,
                'description' => $data['description'] ?? null,
                'status' => SubcontractorInvoice::STATUS_PENDING,
                'submitted_by' => $this->getUserId(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update SC WO financials
            $scWo->recalculateFinancials();
            $scWo->save();

            return $invoice->fresh(['subcontractorWorkOrder', 'subcontractor']);
        }, ['sc_wo_id' => $scWo->id, 'gross_amount' => $data['gross_amount']]);
    }

    /**
     * Update invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(SubcontractorInvoice $invoice, array $data): SubcontractorInvoice
    {
        if (! $invoice->isPending()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($invoice, 'Hanya invoice pending yang dapat diubah.');
        }

        return $this->executeInTransaction('update_invoice', function () use ($invoice, $data) {
            $invoice->fill($data);
            $invoice->recalculate();
            $invoice->save();

            // Update SC WO financials
            $invoice->subcontractorWorkOrder->recalculateFinancials();
            $invoice->subcontractorWorkOrder->save();

            return $invoice->fresh(['subcontractorWorkOrder', 'subcontractor']);
        }, ['invoice_id' => $invoice->id]);
    }

    /**
     * Approve invoice.
     */
    public function approveInvoice(SubcontractorInvoice $invoice, ?int $userId = null): SubcontractorInvoice
    {
        if (! $invoice->canBeApproved()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Invoice Subkontraktor',
                'disetujui',
                $invoice->status->label(),
                'pending'
            );
        }

        $invoice->status = DocumentStatus::Approved;
        $invoice->approved_by = $userId ?? $this->getUserId();
        $invoice->approved_at = now();
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * Reject invoice.
     */
    public function rejectInvoice(
        SubcontractorInvoice $invoice,
        string $reason,
        ?int $userId = null
    ): SubcontractorInvoice {
        if (! $invoice->canBeRejected()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Invoice Subkontraktor',
                'ditolak',
                $invoice->status->label(),
                'pending'
            );
        }

        if (empty($reason)) {
            throw \App\Exceptions\Domain\BusinessRuleException::missingRequiredData('Penolakan Invoice', 'alasan');
        }

        $invoice->status = DocumentStatus::Rejected;
        $invoice->rejected_by = $userId ?? $this->getUserId();
        $invoice->rejected_at = now();
        $invoice->rejection_reason = $reason;
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * Convert invoice to bill.
     */
    public function convertToBill(SubcontractorInvoice $invoice): Bill
    {
        if (! $invoice->canBeConvertedToBill()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Invoice Subkontraktor',
                'dikonversi ke bill',
                $invoice->status->label(),
                'disetujui'
            );
        }

        return $this->executeInTransaction('convert_to_bill', function () use ($invoice) {
            $scWo = $invoice->subcontractorWorkOrder;

            // Create bill
            $bill = Bill::create([
                'contact_id' => $invoice->subcontractor_id,
                'bill_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'description' => $invoice->description ?? "Invoice Subkontraktor: {$scWo->name}",
                'reference' => $invoice->invoice_number,
                'subtotal' => $invoice->gross_amount,
                'tax_amount' => 0,
                'tax_rate' => 0,
                'discount_amount' => $invoice->retention_held + $invoice->other_deductions,
                'total_amount' => $invoice->net_amount,
                'currency' => 'IDR',
                'exchange_rate' => 1,
                'base_currency_total' => $invoice->net_amount,
                'paid_amount' => 0,
                'status' => DocumentStatus::Draft,
                'created_by' => $this->getUserId(),
            ]);

            // Create bill item
            BillItem::create([
                'bill_id' => $bill->id,
                'description' => "{$scWo->name} - {$invoice->description}",
                'quantity' => 1,
                'unit' => 'jasa',
                'unit_price' => $invoice->net_amount,
                'line_total' => $invoice->net_amount,
            ]);

            // Update invoice
            $invoice->bill_id = $bill->id;
            $invoice->converted_to_bill_at = now();
            $invoice->save();

            return $bill->fresh(['items', 'contact']);
        }, ['invoice_id' => $invoice->id]);
    }
}
