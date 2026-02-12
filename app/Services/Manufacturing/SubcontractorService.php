<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\SubcontractorServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Shared\SubcontractorInvoice;
use App\Services\Base\BaseService;

class SubcontractorService extends BaseService implements SubcontractorServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubcontractorWorkOrder
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;

            $scWo = new SubcontractorWorkOrder($data);
            $scWo->sc_wo_number = SubcontractorWorkOrder::generateScWoNumber($project);
            $scWo->status = DocumentStatus::Draft;
            $scWo->retention_percent = $data['retention_percent'] ?? SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
            $scWo->created_by = $data['created_by'] ?? $this->getUserId();
            $scWo->save();

            // Calculate financials
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        }, ['subcontractor_id' => $data['subcontractor_id'] ?? null, 'project_id' => $data['project_id'] ?? null]);
    }

    /**
     * Update a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SubcontractorWorkOrder $scWo, array $data): SubcontractorWorkOrder
    {
        if (! $scWo->canBeEdited()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($scWo, 'SC WO hanya dapat diedit dalam status draft atau ditugaskan.');
        }

        return $this->executeInTransaction('update', function () use ($scWo, $data) {
            $scWo->fill($data);
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Delete a subcontractor work order.
     */
    public function delete(SubcontractorWorkOrder $scWo): bool
    {
        if (! $scWo->isDeletable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($scWo, 'Hanya SC WO draft yang dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($scWo) {
            $scWo->invoices()->delete();

            return $scWo->delete();
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Assign work order to subcontractor.
     */
    public function assign(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->stateMachine()->canAssign()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation('SC WO', 'ditugaskan', $scWo->status->value, 'draft');
        }

        $scWo->transitionTo(DocumentStatus::Assigned, $userId);

        return $scWo->fresh();
    }

    /**
     * Start work order.
     */
    public function start(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->stateMachine()->canStart()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation('SC WO', 'dimulai', $scWo->status->value, 'ditugaskan');
        }

        $scWo->transitionTo(DocumentStatus::InProgress, $userId);

        return $scWo->fresh();
    }

    /**
     * Update progress.
     */
    public function updateProgress(SubcontractorWorkOrder $scWo, int $percentage): SubcontractorWorkOrder
    {
        if (! $scWo->canUpdateProgress()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'SC WO',
                'update progres',
                $scWo->status->value,
                'dalam proses'
            );
        }

        if ($percentage < 0 || $percentage > 100) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Persentase progres',
                $percentage,
                100,
                'invalid'
            );
        }

        $scWo->completion_percentage = $percentage;
        $scWo->save();

        return $scWo->fresh();
    }

    /**
     * Complete work order.
     */
    public function complete(
        SubcontractorWorkOrder $scWo,
        ?int $actualAmount = null,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $scWo->stateMachine()->canComplete()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'SC WO',
                'diselesaikan',
                $scWo->status->value,
                'dalam proses'
            );
        }

        return $this->executeInTransaction('complete', function () use ($scWo, $actualAmount, $userId) {
            $scWo->transitionTo(DocumentStatus::Completed, $userId);

            $scWo->refresh();

            if ($actualAmount !== null) {
                $scWo->actual_amount = $actualAmount;
            } else {
                $scWo->actual_amount = $scWo->agreed_amount;
            }

            $scWo->recalculateFinancials();
            $scWo->save();

            $this->createProjectCost($scWo);

            return $scWo->fresh();
        }, ['sc_wo_id' => $scWo->id]);
    }

    /**
     * Cancel work order.
     */
    public function cancel(
        SubcontractorWorkOrder $scWo,
        ?string $reason = null,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $scWo->stateMachine()->canCancel()) {
            throw \App\Exceptions\Domain\StateTransitionException::actionNotAvailable('dibatalkan', $scWo->status->value);
        }

        $scWo->transitionTo(DocumentStatus::Cancelled, $userId, [
            'cancellation_reason' => $reason,
        ]);

        return $scWo->fresh();
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

    /**
     * Get subcontractor statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?int $subcontractorId = null): array
    {
        $woQuery = SubcontractorWorkOrder::query();
        $invQuery = SubcontractorInvoice::query();

        if ($subcontractorId) {
            $woQuery->where('subcontractor_id', $subcontractorId);
            $invQuery->where('subcontractor_id', $subcontractorId);
        }

        // WO statistics
        $woByStatus = [];
        foreach (SubcontractorWorkOrder::getStatuses() as $status => $label) {
            $woByStatus[$status] = (clone $woQuery)->where('status', $status)->count();
        }

        $totalAgreed = (clone $woQuery)->sum('agreed_amount');
        $totalActual = (clone $woQuery)->where('status', DocumentStatus::Completed)->sum('actual_amount');

        // Invoice statistics
        $invByStatus = [];
        foreach (SubcontractorInvoice::getStatuses() as $status => $label) {
            $invByStatus[$status] = (clone $invQuery)->where('status', $status)->count();
        }

        $totalInvoiced = (clone $invQuery)->sum('gross_amount');
        $pendingApproval = (clone $invQuery)->where('status', SubcontractorInvoice::STATUS_PENDING)->sum('net_amount');

        return [
            'work_orders' => [
                'total' => $woQuery->count(),
                'by_status' => $woByStatus,
                'total_agreed_amount' => (int) $totalAgreed,
                'total_actual_amount' => (int) $totalActual,
            ],
            'invoices' => [
                'total' => $invQuery->count(),
                'by_status' => $invByStatus,
                'total_invoiced' => (int) $totalInvoiced,
                'pending_approval' => (int) $pendingApproval,
            ],
        ];
    }

    /**
     * Get subcontractors list with statistics.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Contact>
     */
    public function getSubcontractors(): \Illuminate\Database\Eloquent\Collection
    {
        return Contact::query()
            ->where('is_subcontractor', true)
            ->where('is_active', true)
            ->withCount([
                'subcontractorWorkOrders as active_work_orders_count' => function ($q) {
                    $q->whereIn('status', [
                        DocumentStatus::Draft,
                        DocumentStatus::Assigned,
                        DocumentStatus::InProgress,
                    ]);
                },
                'subcontractorWorkOrders as completed_work_orders_count' => function ($q) {
                    $q->where('status', DocumentStatus::Completed);
                },
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Create project cost entry for completed subcontractor work.
     */
    private function createProjectCost(SubcontractorWorkOrder $scWo): void
    {
        if (! $scWo->project_id) {
            return;
        }

        // Check if ProjectCost model exists
        if (! class_exists(ProjectCost::class)) {
            return;
        }

        $amount = $scWo->actual_amount > 0 ? $scWo->actual_amount : $scWo->agreed_amount;

        ProjectCost::create([
            'project_id' => $scWo->project_id,
            'type' => 'subcontractor',
            'description' => "Subkontrak: {$scWo->name}",
            'reference_type' => SubcontractorWorkOrder::class,
            'reference_id' => $scWo->id,
            'amount' => $amount,
            'cost_date' => now(),
            'created_by' => $this->getUserId(),
        ]);

        // Recalculate project financials
        $project = $scWo->project;
        if ($project && method_exists($project, 'calculateFinancials')) {
            $project->calculateFinancials();
            $project->save();
        }
    }
}
