<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Models\Sales\Quotation;

readonly class OutcomeManager
{
    public function markAsWon(Quotation $quotation, array $data = []): void
    {
        $quotation->outcome = QuotationOutcome::Won->value;
        $quotation->won_reason = $data['won_reason'] ?? null;
        $quotation->outcome_notes = $data['outcome_notes'] ?? null;
        $quotation->outcome_at = now();
        $quotation->next_follow_up_at = null;
        $quotation->save();
    }

    public function markAsLost(Quotation $quotation, array $data = []): void
    {
        $quotation->outcome = QuotationOutcome::Lost->value;
        $quotation->lost_reason = $data['lost_reason'] ?? null;
        $quotation->lost_to_competitor = $data['lost_to_competitor'] ?? null;
        $quotation->outcome_notes = $data['outcome_notes'] ?? null;
        $quotation->outcome_at = now();
        $quotation->next_follow_up_at = null;
        $quotation->save();
    }

    public function getOutcomeLabel(?string $outcome): ?string
    {
        if ($outcome === null) {
            return null;
        }

        return QuotationOutcome::from($outcome)->label();
    }

    public function canMarkAsWon(Quotation $quotation): bool
    {
        return in_array($quotation->status->value, ['submitted', 'approved'], true);
    }

    public function canMarkAsLost(Quotation $quotation): bool
    {
        return $this->canMarkAsWon($quotation);
    }

    public function hasOutcome(Quotation $quotation): bool
    {
        return $quotation->outcome !== null;
    }

    public function wonReasons(): array
    {
        return QuotationOutcome::WON_REASONS;
    }

    public function lostReasons(): array
    {
        return QuotationOutcome::LOST_REASONS;
    }
}
