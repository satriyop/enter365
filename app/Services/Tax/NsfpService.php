<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Tax\NsfpServiceInterface;
use App\Domain\Tax\Events\NsfpAllocated;
use App\Domain\Tax\Events\NsfpLowStock;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Sales\Invoice;
use App\Models\Tax\NsfpRange;

class NsfpService implements NsfpServiceInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('accounting.nsfp.enabled', false);
    }

    /**
     * Allocate the next sequential NSFP number to an invoice.
     *
     * Uses pessimistic locking (lockForUpdate) to guarantee gap-free
     * sequential numbering. Must be called within the invoice post()
     * DB transaction — if post() rolls back, the NSFP allocation rolls back too.
     *
     * @throws BusinessRuleException When no active range exists or range is exhausted
     */
    public function allocate(Invoice $invoice): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        if ($invoice->nsfp_number) {
            return $invoice->nsfp_number;
        }

        // Resolve year from invoice date (supports backdated invoices)
        $yearCode = $invoice->invoice_date->format('y');

        $range = NsfpRange::query()
            ->active()
            ->forYear($yearCode)
            ->lockForUpdate()
            ->orderBy('range_start')
            ->first();

        if (! $range) {
            throw BusinessRuleException::operationNotAllowed(
                'alokasi NSFP',
                "Tidak ada range NSFP aktif untuk tahun 20{$yearCode}. Silakan tambahkan range NSFP dari e-Nofa."
            );
        }

        if ($range->isExhausted()) {
            throw BusinessRuleException::operationNotAllowed(
                'alokasi NSFP',
                "Range NSFP {$range->formatNumber($range->range_start)} - {$range->formatNumber($range->range_end)} sudah habis. Silakan tambahkan range baru."
            );
        }

        $number = $range->next_number;
        $nsfpNumber = $range->formatNumber($number);

        // Increment range counters
        $range->next_number = $number + 1;
        $range->used_count = $range->used_count + 1;
        $range->save();

        // Update invoice with NSFP data
        $invoice->nsfp_number = $nsfpNumber;
        $invoice->nsfp_range_id = $range->id;
        $invoice->nsfp_assigned_at = now();
        $invoice->save();

        $this->eventDispatcher->dispatch(
            NsfpAllocated::fromInvoice($invoice, $nsfpNumber)
        );

        // Check low stock threshold
        if ($range->isLowStock()) {
            $this->eventDispatcher->dispatch(
                new NsfpLowStock(
                    rangeId: $range->id,
                    remaining: $range->getRemainingCount(),
                    threshold: (int) config('accounting.nsfp.low_stock_threshold', 50)
                )
            );
        }

        return $nsfpNumber;
    }

    /**
     * Create a new NSFP range from e-Nofa import.
     *
     * @param  array{
     *     transaction_code: string,
     *     branch_code: string,
     *     year_code: string,
     *     range_start: int,
     *     range_end: int,
     *     description?: string|null
     * }  $data
     */
    public function createRange(array $data, ?int $createdBy = null): NsfpRange
    {
        $range = NsfpRange::create([
            'transaction_code' => $data['transaction_code'],
            'branch_code' => $data['branch_code'],
            'year_code' => $data['year_code'],
            'range_start' => $data['range_start'],
            'range_end' => $data['range_end'],
            'next_number' => $data['range_start'],
            'used_count' => 0,
            'is_active' => true,
            'description' => $data['description'] ?? null,
            'created_by' => $createdBy,
        ]);

        return $range->load('createdBy');
    }

    /**
     * Update mutable fields on an NSFP range.
     *
     * @param  array{description?: string|null, is_active?: bool}  $data
     */
    public function updateRange(NsfpRange $range, array $data): NsfpRange
    {
        $range->update(array_intersect_key($data, array_flip(['description', 'is_active'])));

        return $range->fresh() ?? $range;
    }

    /**
     * Deactivate an NSFP range.
     */
    public function deactivateRange(NsfpRange $range): NsfpRange
    {
        $range->update(['is_active' => false]);

        return $range->fresh() ?? $range;
    }
}
