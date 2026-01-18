<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;

class PurchaseOrderWorkflow
{
    public function submit(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canSubmit()) {
            throw new \InvalidArgumentException('PO tidak dapat diajukan. Pastikan status draft dan memiliki item.');
        }

        $purchaseOrder->update([
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function approve(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canApprove()) {
            throw new \InvalidArgumentException('PO tidak dapat disetujui. Pastikan sudah diajukan.');
        }

        $purchaseOrder->update([
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function reject(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canReject()) {
            throw new \InvalidArgumentException('PO tidak dapat ditolak. Pastikan sudah diajukan.');
        }

        if (empty($reason)) {
            throw new \InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $purchaseOrder->update([
            'status' => DocumentStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $userId ?? auth()->id(),
            'rejection_reason' => $reason,
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }

    public function cancel(PurchaseOrder $purchaseOrder, string $reason, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canCancel()) {
            throw new \InvalidArgumentException('PO tidak dapat dibatalkan.');
        }

        if (empty($reason)) {
            throw new \InvalidArgumentException('Alasan pembatalan harus diisi.');
        }

        $purchaseOrder->update([
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $userId ?? auth()->id(),
            'cancellation_reason' => $reason,
        ]);

        return $purchaseOrder->fresh(['items', 'contact']);
    }
}
