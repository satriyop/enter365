<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;

/**
 * Interface for Quotation follow-up management.
 */
interface QuotationFollowUpServiceInterface
{
    /**
     * Schedule a follow-up for a quotation.
     */
    public function scheduleFollowUp(Quotation $quotation, int $daysFromNow = 3): Quotation;

    /**
     * Schedule a follow-up at a specific date.
     */
    public function scheduleFollowUpAt(Quotation $quotation, \DateTime|string $date): Quotation;

    /**
     * Schedule follow-up and optionally record a notes activity.
     *
     * @param  array{next_follow_up_at: string|\DateTimeInterface, notes?: string|null}  $data
     */
    public function scheduleFollowUpWithNotes(Quotation $quotation, array $data, ?int $userId = null): Quotation;

    /**
     * Clear the follow-up schedule.
     */
    public function clearFollowUp(Quotation $quotation): Quotation;

    /**
     * Record a contact interaction.
     */
    public function recordContact(Quotation $quotation): Quotation;

    /**
     * Create a quotation activity and update contact tracking.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeActivity(Quotation $quotation, array $data, ?int $userId = null): QuotationActivity;

    /**
     * Assign a quotation to a sales person.
     */
    public function assignTo(Quotation $quotation, int $userId): Quotation;

    /**
     * Update quotation priority.
     */
    public function updatePriority(Quotation $quotation, string $priority): Quotation;

    /**
     * Check if a quotation needs follow-up.
     */
    public function needsFollowUp(Quotation $quotation): bool;
}
