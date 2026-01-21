<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Contracts\Repositories\SpecificationInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\EntityNotFoundException;
use App\Models\Sales\Quotation;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * In-memory implementation of QuotationRepositoryInterface for unit testing.
 *
 * This repository stores quotations in memory, allowing fast unit tests
 * without database access. Perfect for testing service logic in isolation.
 *
 * Usage:
 * ```php
 * $repository = new InMemoryQuotationRepository();
 * $quotation = $repository->create(['contact_id' => 1, ...]);
 * $this->app->instance(QuotationRepositoryInterface::class, $repository);
 * ```
 *
 * @implements QuotationRepositoryInterface
 */
class InMemoryQuotationRepository implements QuotationRepositoryInterface
{
    /**
     * @var Collection<int, Quotation>
     */
    private Collection $quotations;

    private int $nextId = 1;

    public function __construct()
    {
        $this->quotations = collect();
    }

    /**
     * Reset the repository state.
     */
    public function reset(): void
    {
        $this->quotations = collect();
        $this->nextId = 1;
    }

    /**
     * Seed the repository with existing quotations.
     *
     * @param  array<Quotation>  $quotations
     */
    public function seed(array $quotations): void
    {
        foreach ($quotations as $quotation) {
            if ($quotation->id) {
                $this->nextId = max($this->nextId, $quotation->id + 1);
            }
            $this->quotations->put($quotation->id ?? $this->nextId++, $quotation);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Base RepositoryInterface Methods
    // ─────────────────────────────────────────────────────────────

    public function find(int $id): ?Quotation
    {
        return $this->quotations->get($id);
    }

    public function findOrFail(int $id): Quotation
    {
        $quotation = $this->find($id);

        if ($quotation === null) {
            throw new EntityNotFoundException('Quotation', $id);
        }

        return $quotation;
    }

    public function all(): Collection
    {
        return $this->quotations->values();
    }

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $items = $this->quotations->values();
        $page = 1;

        return new Paginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page
        );
    }

    public function create(array $data): Quotation
    {
        $quotation = new Quotation($data);
        $quotation->id = $this->nextId++;

        // Set timestamps
        $quotation->created_at = Carbon::now();
        $quotation->updated_at = Carbon::now();

        // Set default status if not provided
        if (! isset($quotation->status)) {
            $quotation->status = DocumentStatus::Draft;
        }

        $this->quotations->put($quotation->id, $quotation);

        return $quotation;
    }

    public function update(object|int $entity, array $data): Quotation
    {
        $quotation = $entity instanceof Quotation
            ? $entity
            : $this->findOrFail($entity);

        $quotation->fill($data);
        $quotation->updated_at = Carbon::now();

        return $quotation;
    }

    public function delete(object|int $entity): bool
    {
        $id = $entity instanceof Quotation ? $entity->id : $entity;

        if (! $this->quotations->has($id)) {
            return false;
        }

        $this->quotations->forget($id);

        return true;
    }

    public function findBy(array $criteria): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $this->matchesCriteria($q, $criteria))
            ->values();
    }

    public function findOneBy(array $criteria): ?Quotation
    {
        return $this->findBy($criteria)->first();
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return $this->quotations->count();
        }

        return $this->findBy($criteria)->count();
    }

    public function exists(array $criteria): bool
    {
        return $this->findOneBy($criteria) !== null;
    }

    public function match(SpecificationInterface $specification): Collection
    {
        // Specifications use Eloquent Builder, which doesn't work in-memory.
        // For unit tests, use findBy() with explicit criteria instead.
        throw new RuntimeException(
            'Specifications are not supported in InMemoryQuotationRepository. '.
            'Use findBy() with explicit criteria for unit testing.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // QuotationRepositoryInterface Specific Methods
    // ─────────────────────────────────────────────────────────────

    public function findByNumber(string $quotationNumber): ?Quotation
    {
        return $this->quotations
            ->first(fn (Quotation $q) => $q->quotation_number === $quotationNumber);
    }

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->status === $status)
            ->values();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->contact_id === $contactId)
            ->sortByDesc('created_at')
            ->values();
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->quotations
            ->filter(function (Quotation $q) use ($range) {
                $date = $q->quotation_date ?? $q->created_at;

                if (! $date) {
                    return false;
                }

                // Convert to string if it's a Carbon instance
                $dateString = $date instanceof \DateTimeInterface
                    ? $date->format('Y-m-d')
                    : (string) $date;

                return $range->contains($dateString);
            })
            ->values();
    }

    public function findExpiringSoon(int $days = 7): Collection
    {
        $now = Carbon::now();
        $threshold = $now->copy()->addDays($days);

        $activeStatuses = [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ];

        return $this->quotations
            ->filter(function (Quotation $q) use ($now, $threshold, $activeStatuses) {
                if (! in_array($q->status, $activeStatuses, true)) {
                    return false;
                }

                if (! $q->valid_until) {
                    return false;
                }

                $validUntil = Carbon::parse($q->valid_until);

                return $validUntil->gt($now) && $validUntil->lte($threshold);
            })
            ->sortBy('valid_until')
            ->values();
    }

    public function findPendingApproval(): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->status === DocumentStatus::Submitted)
            ->sortBy('submitted_at')
            ->values();
    }

    public function findNeedingFollowUp(): Collection
    {
        $now = Carbon::now();

        return $this->quotations
            ->filter(function (Quotation $q) use ($now) {
                if (! $q->next_follow_up_at) {
                    return false;
                }

                return Carbon::parse($q->next_follow_up_at)->lte($now);
            })
            ->values();
    }

    public function findAssignedTo(int $userId): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->assigned_to === $userId)
            ->values();
    }

    public function findActive(): Collection
    {
        $activeStatuses = [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ];

        return $this->quotations
            ->filter(fn (Quotation $q) => in_array($q->status, $activeStatuses, true))
            ->values();
    }

    public function findByOutcome(?string $outcome): Collection
    {
        return $this->quotations
            ->filter(fn (Quotation $q) => $q->outcome === $outcome)
            ->values();
    }

    public function findWithRelations(int $id): ?Quotation
    {
        // In-memory doesn't support eager loading, just return the quotation
        return $this->find($id);
    }

    public function getValueByStatus(): array
    {
        return $this->quotations
            ->groupBy(fn (Quotation $q) => $q->status->value)
            ->map(fn (Collection $group, string $status) => [
                'status' => DocumentStatus::from($status),
                'count' => $group->count(),
                'total_value' => $group->sum('total'),
            ])
            ->values()
            ->toArray();
    }

    public function getWinRateStats(?DateRange $range = null): array
    {
        $quotations = $range
            ? $this->findByDateRange($range)
            : $this->quotations;

        $approved = $quotations->filter(
            fn (Quotation $q) => $q->status === DocumentStatus::Approved
                || $q->outcome !== null
        );

        $won = $approved->filter(fn (Quotation $q) => $q->outcome === 'won')->count();
        $lost = $approved->filter(fn (Quotation $q) => $q->outcome === 'lost')->count();
        $pending = $approved->filter(fn (Quotation $q) => $q->outcome === null)->count();
        $total = $won + $lost + $pending;

        return [
            'total' => $total,
            'won' => $won,
            'lost' => $lost,
            'pending' => $pending,
            'win_rate' => $total > 0 ? round(($won / $total) * 100, 2) : 0.0,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if a quotation matches the given criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    private function matchesCriteria(Quotation $quotation, array $criteria): bool
    {
        foreach ($criteria as $field => $value) {
            $actual = $quotation->{$field};

            if ($actual !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the internal collection for assertions in tests.
     *
     * @return Collection<int, Quotation>
     */
    public function getCollection(): Collection
    {
        return $this->quotations;
    }
}
