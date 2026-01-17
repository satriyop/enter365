<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;

class QuotationStatistics
{
    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function get(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Quotation::query();

        if ($startDate) {
            $query->where('quotation_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('quotation_date', '<=', $endDate);
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->where('status', DocumentStatus::Draft)->count();
        $submitted = (clone $query)->where('status', DocumentStatus::Submitted)->count();
        $approved = (clone $query)->where('status', DocumentStatus::Approved)->count();
        $rejected = (clone $query)->where('status', DocumentStatus::Rejected)->count();
        $expired = (clone $query)->where('status', DocumentStatus::Expired)->count();
        $converted = (clone $query)->where('status', DocumentStatus::Converted)->count();

        $totalValue = (clone $query)->sum('total');
        $approvedValue = (clone $query)->where('status', DocumentStatus::Approved)->sum('total');
        $convertedValue = (clone $query)->where('status', DocumentStatus::Converted)->sum('total');

        $approvalRate = $total > 0 ? round((($approved + $converted) / $total) * 100, 2) : 0;
        $conversionRate = ($approved + $converted) > 0 ? round(($converted / ($approved + $converted)) * 100, 2) : 0;

        return [
            'total' => $total,
            'by_status' => [
                'draft' => $draft,
                'submitted' => $submitted,
                'approved' => $approved,
                'rejected' => $rejected,
                'expired' => $expired,
                'converted' => $converted,
            ],
            'total_value' => $totalValue,
            'approved_value' => $approvedValue,
            'converted_value' => $convertedValue,
            'approval_rate' => $approvalRate,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Get win/loss statistics.
     *
     * @return array{won: int, lost: int, pending: int, win_rate: float}
     */
    public function getWinLoss(): array
    {
        $won = Quotation::won()->count();
        $lost = Quotation::lost()->count();
        $pending = Quotation::pending()->count();
        $decided = $won + $lost;

        $winRate = $decided > 0 ? round(($won / $decided) * 100, 2) : 0;

        return [
            'won' => $won,
            'lost' => $lost,
            'pending' => $pending,
            'win_rate' => $winRate,
        ];
    }

    /**
     * Get follow-up summary.
     *
     * @return array{needs_followup: int, overdue: int, high_priority: int}
     */
    public function getFollowUpSummary(): array
    {
        return [
            'needs_followup' => Quotation::needsFollowUp()->count(),
            'overdue' => Quotation::overdueFollowUp()->count(),
            'high_priority' => Quotation::highPriority()->count(),
        ];
    }
}
