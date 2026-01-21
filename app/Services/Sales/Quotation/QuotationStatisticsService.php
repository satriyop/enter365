<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Domain\Sales\Quotations\QuotationStatistics;

/**
 * Handles statistics and analytics for quotations.
 *
 * Extracted from QuotationService as part of the Coordinator Pattern refactoring.
 * This service wraps the QuotationStatistics domain class.
 *
 * @see \App\Services\Sales\QuotationService The coordinator service
 * @see \App\Domain\Sales\Quotations\QuotationStatistics The domain statistics class
 */
class QuotationStatisticsService
{
    public function __construct(
        private QuotationStatistics $statistics,
    ) {}

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function get(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->statistics->get($startDate, $endDate);
    }

    /**
     * Get win/loss statistics.
     *
     * @return array{won: int, lost: int, pending: int, win_rate: float}
     */
    public function getWinLoss(): array
    {
        return $this->statistics->getWinLoss();
    }

    /**
     * Get follow-up summary.
     *
     * @return array{needs_followup: int, overdue: int, high_priority: int}
     */
    public function getFollowUpSummary(): array
    {
        return $this->statistics->getFollowUpSummary();
    }

    /**
     * Get dashboard data combining multiple statistics.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(?string $startDate = null, ?string $endDate = null): array
    {
        return [
            'overview' => $this->statistics->get($startDate, $endDate),
            'win_loss' => $this->statistics->getWinLoss(),
            'follow_up' => $this->statistics->getFollowUpSummary(),
        ];
    }
}
