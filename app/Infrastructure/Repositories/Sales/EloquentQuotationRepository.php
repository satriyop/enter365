<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Sales\Quotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of Quotation repository.
 *
 * @extends EloquentRepository<Quotation>
 */
class EloquentQuotationRepository extends EloquentRepository implements QuotationRepositoryInterface
{
    protected string $modelClass = Quotation::class;

    protected array $with = ['contact', 'items'];

    public function findByNumber(string $quotationNumber): ?Quotation
    {
        return $this->findOneBy(['quotation_number' => $quotationNumber]);
    }

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->newQuery()
            ->where('status', $status)
            ->orderBy('quotation_date', 'desc')
            ->get();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->newQuery()
            ->where('contact_id', $contactId)
            ->orderBy('quotation_date', 'desc')
            ->get();
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->newQuery()
            ->whereBetween('quotation_date', [
                $range->start->toDateString(),
                $range->end->toDateString(),
            ])
            ->orderBy('quotation_date', 'desc')
            ->get();
    }

    public function findExpiringSoon(int $days = 7): Collection
    {
        return $this->newQuery()
            ->whereIn('status', [
                DocumentStatus::Draft,
                DocumentStatus::Submitted,
                DocumentStatus::Approved,
            ])
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>', now())
            ->orderBy('valid_until', 'asc')
            ->get();
    }

    public function findPendingApproval(): Collection
    {
        return $this->newQuery()
            ->where('status', DocumentStatus::Submitted)
            ->orderBy('submitted_at', 'asc')
            ->get();
    }

    public function findNeedingFollowUp(): Collection
    {
        return $this->newQuery()
            ->needsFollowUp()
            ->orderBy('next_follow_up_at', 'asc')
            ->get();
    }

    public function findAssignedTo(int $userId): Collection
    {
        return $this->newQuery()
            ->where('assigned_to', $userId)
            ->orderBy('quotation_date', 'desc')
            ->get();
    }

    public function findActive(): Collection
    {
        return $this->newQuery()
            ->whereIn('status', [
                DocumentStatus::Draft,
                DocumentStatus::Submitted,
                DocumentStatus::Approved,
            ])
            ->orderBy('quotation_date', 'desc')
            ->get();
    }

    public function findByOutcome(?string $outcome): Collection
    {
        $query = $this->newQuery();

        if ($outcome === null) {
            $query->whereNull('outcome');
        } else {
            $query->where('outcome', $outcome);
        }

        return $query->orderBy('outcome_at', 'desc')->get();
    }

    public function findWithRelations(int $id): ?Quotation
    {
        return $this->newQuery()
            ->with([
                'contact',
                'items.product',
                'creator',
                'assignedTo',
                'originalQuotation',
                'revisions',
            ])
            ->find($id);
    }

    /**
     * Get total value of quotations grouped by status.
     *
     * Uses DB::table for performance on aggregation queries.
     *
     * @return array{status: DocumentStatus, count: int, total_value: int}[]
     */
    public function getValueByStatus(): array
    {
        $results = DB::table('quotations')
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total), 0) as total_value')
            ->groupBy('status')
            ->get();

        return $results->map(fn ($row) => [
            'status' => DocumentStatus::from($row->status),
            'count' => (int) $row->count,
            'total_value' => (int) $row->total_value,
        ])->all();
    }

    /**
     * Get win/loss rate statistics.
     *
     * Uses DB::table for performance on aggregation queries.
     *
     * @return array{total: int, won: int, lost: int, pending: int, win_rate: float}
     */
    public function getWinRateStats(?DateRange $range = null): array
    {
        $query = DB::table('quotations')
            ->whereIn('status', [
                DocumentStatus::Approved->value,
                DocumentStatus::Converted->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Expired->value,
                DocumentStatus::Cancelled->value,
            ]);

        if ($range !== null) {
            $query->whereBetween('quotation_date', [
                $range->start->toDateString(),
                $range->end->toDateString(),
            ]);
        }

        $results = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN outcome = 'won' THEN 1 ELSE 0 END) as won")
            ->selectRaw("SUM(CASE WHEN outcome = 'lost' THEN 1 ELSE 0 END) as lost")
            ->selectRaw('SUM(CASE WHEN outcome IS NULL THEN 1 ELSE 0 END) as pending')
            ->first();

        $total = (int) ($results->total ?? 0);
        $won = (int) ($results->won ?? 0);
        $lost = (int) ($results->lost ?? 0);
        $pending = (int) ($results->pending ?? 0);

        // Win rate = won / (won + lost), excluding pending
        $decided = $won + $lost;
        $winRate = $decided > 0 ? round(($won / $decided) * 100, 2) : 0.0;

        return [
            'total' => $total,
            'won' => $won,
            'lost' => $lost,
            'pending' => $pending,
            'win_rate' => $winRate,
        ];
    }
}
