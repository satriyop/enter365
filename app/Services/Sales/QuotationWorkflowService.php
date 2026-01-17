<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for quotation outcome management.
 *
 * Handles marking quotations as won or lost with proper validation
 * and activity tracking.
 */
class QuotationWorkflowService
{
    /**
     * Mark quotation as won.
     *
     * @param  array{won_reason?: string, outcome_notes?: string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function markAsWon(Quotation $quotation, array $data = []): Quotation
    {
        if ($quotation->status !== DocumentStatus::Approved) {
            throw new InvalidArgumentException('Hanya penawaran yang disetujui yang dapat ditandai sebagai menang.');
        }

        if ($quotation->outcome !== null) {
            throw new InvalidArgumentException('Penawaran sudah memiliki hasil.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $quotation->outcome = QuotationOutcome::Won->value;
            $quotation->won_reason = $data['won_reason'] ?? null;
            $quotation->outcome_notes = $data['outcome_notes'] ?? null;
            $quotation->outcome_at = now();
            $quotation->next_follow_up_at = null; // Clear follow-up when won
            $quotation->save();

            return $quotation->fresh();
        });
    }

    /**
     * Mark quotation as lost.
     *
     * @param  array{lost_reason?: string, lost_to_competitor?: string, outcome_notes?: string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function markAsLost(Quotation $quotation, array $data = []): Quotation
    {
        if (! in_array($quotation->status, [DocumentStatus::Approved, DocumentStatus::Submitted])) {
            throw new InvalidArgumentException('Hanya penawaran yang diajukan atau disetujui yang dapat ditandai sebagai kalah.');
        }

        if ($quotation->outcome !== null) {
            throw new InvalidArgumentException('Penawaran sudah memiliki hasil.');
        }

        return DB::transaction(function () use ($quotation, $data) {
            $quotation->outcome = QuotationOutcome::Lost->value;
            $quotation->lost_reason = $data['lost_reason'] ?? null;
            $quotation->lost_to_competitor = $data['lost_to_competitor'] ?? null;
            $quotation->outcome_notes = $data['outcome_notes'] ?? null;
            $quotation->outcome_at = now();
            $quotation->next_follow_up_at = null; // Clear follow-up when lost
            $quotation->save();

            return $quotation->fresh();
        });
    }

    /**
     * Check if quotation can be marked as won.
     */
    public function canMarkAsWon(Quotation $quotation): bool
    {
        return $quotation->status === DocumentStatus::Approved
            && $quotation->outcome === null;
    }

    /**
     * Check if quotation can be marked as lost.
     */
    public function canMarkAsLost(Quotation $quotation): bool
    {
        return in_array($quotation->status, [DocumentStatus::Approved, DocumentStatus::Submitted])
            && $quotation->outcome === null;
    }
}
