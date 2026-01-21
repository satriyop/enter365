<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\Sales;

use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;

/**
 * Repository interface for Quotation entities.
 *
 * Provides domain-specific query methods for quotation management,
 * including status-based queries, follow-up tracking, and sales pipeline queries.
 *
 * @extends RepositoryInterface<Quotation>
 */
interface QuotationRepositoryInterface extends RepositoryInterface
{
    /**
     * Find quotation by number.
     */
    public function findByNumber(string $quotationNumber): ?Quotation;

    /**
     * Find quotations by status.
     *
     * @return Collection<int, Quotation>
     */
    public function findByStatus(DocumentStatus $status): Collection;

    /**
     * Find quotations for a contact.
     *
     * @return Collection<int, Quotation>
     */
    public function findByContact(int $contactId): Collection;

    /**
     * Find quotations in date range.
     *
     * @return Collection<int, Quotation>
     */
    public function findByDateRange(DateRange $range): Collection;

    /**
     * Find quotations expiring within specified days.
     *
     * Returns quotations that are not yet expired but will expire soon.
     *
     * @return Collection<int, Quotation>
     */
    public function findExpiringSoon(int $days = 7): Collection;

    /**
     * Find quotations pending approval.
     *
     * Returns submitted quotations awaiting review.
     *
     * @return Collection<int, Quotation>
     */
    public function findPendingApproval(): Collection;

    /**
     * Find quotations needing follow-up.
     *
     * Returns quotations with past-due follow-up dates.
     *
     * @return Collection<int, Quotation>
     */
    public function findNeedingFollowUp(): Collection;

    /**
     * Find quotations assigned to a user.
     *
     * @return Collection<int, Quotation>
     */
    public function findAssignedTo(int $userId): Collection;

    /**
     * Find active quotations (can still be acted upon).
     *
     * Returns draft, submitted, and approved quotations.
     *
     * @return Collection<int, Quotation>
     */
    public function findActive(): Collection;

    /**
     * Find quotations by outcome.
     *
     * @param  'won'|'lost'|null  $outcome
     * @return Collection<int, Quotation>
     */
    public function findByOutcome(?string $outcome): Collection;

    /**
     * Find quotation with all relations loaded.
     */
    public function findWithRelations(int $id): ?Quotation;

    /**
     * Get total value of quotations by status.
     *
     * @return array{status: DocumentStatus, count: int, total_value: int}[]
     */
    public function getValueByStatus(): array;

    /**
     * Get win rate statistics.
     *
     * @return array{total: int, won: int, lost: int, pending: int, win_rate: float}
     */
    public function getWinRateStats(?DateRange $range = null): array;
}
