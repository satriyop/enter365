<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Core\StatusHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait to add status history tracking to models.
 *
 * Add this trait to any Eloquent model that needs status change audit trail:
 *
 * ```php
 * class Invoice extends Model
 * {
 *     use HasStatusHistory;
 * }
 *
 * // Record status change:
 * $invoice->recordStatusChange('draft', 'sent', auth()->id(), ['note' => 'Sent via email']);
 *
 * // Get timeline:
 * $timeline = $invoice->getStatusTimeline();
 *
 * // Get all history:
 * $history = $invoice->statusHistories;
 * ```
 */
trait HasStatusHistory
{
    /**
     * Get all status history records for this model.
     *
     * @return MorphMany<StatusHistory, $this>
     */
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'statusable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Record a status change in history.
     *
     * @param  array<string, mixed>  $context  Optional context data
     */
    public function recordStatusChange(
        string $fromStatus,
        string $toStatus,
        ?int $userId = null,
        array $context = []
    ): StatusHistory {
        return $this->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $userId ?? auth()->id(),
            'context' => $context ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Get formatted status timeline for display.
     *
     * @return array<array{from: string, to: string, description: string, user: string|null, at: string}>
     */
    public function getStatusTimeline(): array
    {
        return $this->statusHistories()
            ->with('user:id,name')
            ->get()
            ->map(fn (StatusHistory $history) => [
                'from' => $history->from_status->label(),
                'to' => $history->to_status->label(),
                'description' => $history->getDescription(),
                'user' => $history->user?->name,
                'at' => $history->getFormattedTime(),
            ])
            ->toArray();
    }

    /**
     * Get the most recent status change.
     */
    public function getLatestStatusChange(): ?StatusHistory
    {
        return $this->statusHistories()->first();
    }

    /**
     * Check if a specific status transition has occurred.
     */
    public function hasTransitionedTo(string $status): bool
    {
        return $this->statusHistories()
            ->where('to_status', $status)
            ->exists();
    }

    /**
     * Get count of status changes.
     */
    public function getStatusChangeCount(): int
    {
        return $this->statusHistories()->count();
    }
}
