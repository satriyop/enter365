<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use InvalidArgumentException;

class QuotationWorkflow
{
    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canSubmit()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat diajukan. Pastikan status draft dan memiliki item.'
            );
        }

        $quotation->update([
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        if (! $quotation->canApprove()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat disetujui. Pastikan sudah diajukan dan belum kedaluwarsa.'
            );
        }

        $quotation->update([
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $userId ?? auth()->id(),
        ]);

        return $quotation->fresh(['items', 'contact']);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        if (! $quotation->canReject()) {
            throw new InvalidArgumentException(
                'Penawaran tidak dapat ditolak. Pastikan sudah diajukan.'
            );
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Alasan penolakan harus diisi.');
        }

        $quotation->update([
            'status' => DocumentStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $userId ?? auth()->id(),
            'rejection_reason' => $reason,
        ]);

        return $quotation->fresh(['items', 'contact']);
    }
}
