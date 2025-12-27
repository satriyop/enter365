<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Project;
use App\Models\Accounting\ProjectCost;
use App\Models\Accounting\SubcontractorInvoice;
use App\Models\Accounting\SubcontractorWorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubcontractorService
{
    /**
     * Create a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubcontractorWorkOrder
    {
        return DB::transaction(function () use ($data) {
            $project = isset($data['project_id']) ? Project::find($data['project_id']) : null;

            $scWo = new SubcontractorWorkOrder($data);
            $scWo->sc_wo_number = SubcontractorWorkOrder::generateScWoNumber($project);
            $scWo->status = SubcontractorWorkOrder::STATUS_DRAFT;
            $scWo->retention_percent = $data['retention_percent'] ?? SubcontractorWorkOrder::DEFAULT_RETENTION_PERCENT;
            $scWo->created_by = $data['created_by'] ?? auth()->id();
            $scWo->save();

            // Calculate financials
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        });
    }

    /**
     * Update a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SubcontractorWorkOrder $scWo, array $data): SubcontractorWorkOrder
    {
        if (! $scWo->canBeEdited()) {
            throw new InvalidArgumentException('SC WO hanya dapat diedit dalam status draft atau ditugaskan.');
        }

        return DB::transaction(function () use ($scWo, $data) {
            $scWo->fill($data);
            $scWo->recalculateFinancials();
            $scWo->save();

            return $scWo->fresh(['subcontractor', 'project', 'workOrder']);
        });
    }

    /**
     * Delete a subcontractor work order.
     */
    public function delete(SubcontractorWorkOrder $scWo): bool
    {
        if ($scWo->status !== SubcontractorWorkOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya SC WO draft yang dapat dihapus.');
        }

        return DB::transaction(function () use ($scWo) {
            $scWo->invoices()->delete();

            return $scWo->delete();
        });
    }

    /**
     * Assign work order to subcontractor.
     */
    public function assign(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->canBeAssigned()) {
            throw new InvalidArgumentException('SC WO hanya dapat ditugaskan dalam status draft.');
        }

        $scWo->status = SubcontractorWorkOrder::STATUS_ASSIGNED;
        $scWo->assigned_by = $userId ?? auth()->id();
        $scWo->assigned_at = now();
        $scWo->save();

        return $scWo->fresh();
    }

    /**
     * Start work order.
     */
    public function start(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        if (! $scWo->canBeStarted()) {
            throw new InvalidArgumentException('SC WO hanya dapat dimulai setelah ditugaskan.');
        }

        $scWo->status = SubcontractorWorkOrder::STATUS_IN_PROGRESS;
        $scWo->started_by = $userId ?? auth()->id();
        $scWo->started_at = now();
        $scWo->actual_start_date = now();
        $scWo->save();

        return $scWo->fresh();
    }

    /**
     * Update progress.
     */
    public function updateProgress(SubcontractorWorkOrder $scWo, int $percentage): SubcontractorWorkOrder
    {
        if (! $scWo->canUpdateProgress()) {
            throw new InvalidArgumentException('Progres hanya dapat diperbarui saat SC WO dalam proses.');
        }

        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Persentase harus antara 0 dan 100.');
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
        if (! $scWo->canBeCompleted()) {
            throw new InvalidArgumentException('SC WO hanya dapat diselesaikan saat dalam proses.');
        }

        return DB::transaction(function () use ($scWo, $actualAmount, $userId) {
            $scWo->status = SubcontractorWorkOrder::STATUS_COMPLETED;
            $scWo->completed_by = $userId ?? auth()->id();
            $scWo->completed_at = now();
            $scWo->actual_end_date = now();
            $scWo->completion_percentage = 100;

            if ($actualAmount !== null) {
                $scWo->actual_amount = $actualAmount;
            } else {
                $scWo->actual_amount = $scWo->agreed_amount;
            }

            $scWo->recalculateFinancials();
            $scWo->save();

            // Create project cost entry
            $this->createProjectCost($scWo);

            return $scWo->fresh();
        });
    }

    /**
     * Cancel work order.
     */
    public function cancel(
        SubcontractorWorkOrder $scWo,
        ?string $reason = null,
        ?int $userId = null
    ): SubcontractorWorkOrder {
        if (! $scWo->canBeCancelled()) {
            throw new InvalidArgumentException('SC WO tidak dapat dibatalkan.');
        }

        $scWo->status = SubcontractorWorkOrder::STATUS_CANCELLED;
        $scWo->cancelled_by = $userId ?? auth()->id();
        $scWo->cancelled_at = now();
        $scWo->cancellation_reason = $reason;
        $scWo->save();

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
            throw new InvalidArgumentException('Invoice hanya dapat dibuat saat SC WO dalam proses atau selesai.');
        }

        $grossAmount = $data['gross_amount'];
        $remaining = $scWo->getRemainingInvoiceableAmount();

        if ($grossAmount > $remaining) {
            throw new InvalidArgumentException(
                'Jumlah invoice (Rp '.number_format($grossAmount).') melebihi sisa yang dapat ditagihkan (Rp '.number_format($remaining).').'
            );
        }

        return DB::transaction(function () use ($scWo, $data) {
            // Calculate retention from gross amount
            $grossAmount = $data['gross_amount'];
            $retentionHeld = (int) round($grossAmount * ((float) $scWo->retention_percent / 100));
            $otherDeductions = $data['other_deductions'] ?? 0;
            $netAmount = $grossAmount - $retentionHeld - $otherDeductions;

            $invoice = SubcontractorInvoice::create([
                'invoice_number' => SubcontractorInvoice::generateInvoiceNumber(),
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
                'submitted_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update SC WO financials
            $scWo->recalculateFinancials();
            $scWo->save();

            return $invoice->fresh(['subcontractorWorkOrder', 'subcontractor']);
        });
    }

    /**
     * Update invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(SubcontractorInvoice $invoice, array $data): SubcontractorInvoice
    {
        if (! $invoice->isPending()) {
            throw new InvalidArgumentException('Hanya invoice pending yang dapat diubah.');
        }

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->fill($data);
            $invoice->recalculate();
            $invoice->save();

            // Update SC WO financials
            $invoice->subcontractorWorkOrder->recalculateFinancials();
            $invoice->subcontractorWorkOrder->save();

            return $invoice->fresh(['subcontractorWorkOrder', 'subcontractor']);
        });
    }

    /**
     * Approve invoice.
     */
    public function approveInvoice(SubcontractorInvoice $invoice, ?int $userId = null): SubcontractorInvoice
    {
        if (! $invoice->canBeApproved()) {
            throw new InvalidArgumentException('Invoice hanya dapat disetujui dalam status pending.');
        }

        $invoice->status = SubcontractorInvoice::STATUS_APPROVED;
        $invoice->approved_by = $userId ?? auth()->id();
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
            throw new InvalidArgumentException('Invoice hanya dapat ditolak dalam status pending.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $invoice->status = SubcontractorInvoice::STATUS_REJECTED;
        $invoice->rejected_by = $userId ?? auth()->id();
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
            throw new InvalidArgumentException('Invoice harus disetujui dan belum dikonversi ke bill.');
        }

        return DB::transaction(function () use ($invoice) {
            $scWo = $invoice->subcontractorWorkOrder;

            // Create bill
            $bill = Bill::create([
                'bill_number' => Bill::generateBillNumber(),
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
                'status' => Bill::STATUS_DRAFT,
                'created_by' => auth()->id(),
            ]);

            // Create bill item
            BillItem::create([
                'bill_id' => $bill->id,
                'description' => "{$scWo->name} - {$invoice->description}",
                'quantity' => 1,
                'unit' => 'jasa',
                'unit_price' => $invoice->net_amount,
                'amount' => $invoice->net_amount,
            ]);

            // Update invoice
            $invoice->bill_id = $bill->id;
            $invoice->converted_to_bill_at = now();
            $invoice->save();

            return $bill->fresh(['items', 'contact']);
        });
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
        $totalActual = (clone $woQuery)->where('status', SubcontractorWorkOrder::STATUS_COMPLETED)->sum('actual_amount');

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
                        SubcontractorWorkOrder::STATUS_DRAFT,
                        SubcontractorWorkOrder::STATUS_ASSIGNED,
                        SubcontractorWorkOrder::STATUS_IN_PROGRESS,
                    ]);
                },
                'subcontractorWorkOrders as completed_work_orders_count' => function ($q) {
                    $q->where('status', SubcontractorWorkOrder::STATUS_COMPLETED);
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
            'created_by' => auth()->id(),
        ]);

        // Recalculate project financials
        $project = $scWo->project;
        if ($project && method_exists($project, 'calculateFinancials')) {
            $project->calculateFinancials();
            $project->save();
        }
    }
}
