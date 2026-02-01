<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Services\Base\BaseService;
use DateTime;

/**
 * Service for quotation follow-up management.
 *
 * Handles scheduling follow-ups, recording contact activities,
 * and calculating auto follow-up dates.
 */
class QuotationFollowUpService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Schedule next follow-up for a quotation.
     */
    public function scheduleFollowUp(Quotation $quotation, int $daysFromNow = 3): Quotation
    {
        $quotation->next_follow_up_at = now()->addDays($daysFromNow);
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Schedule follow-up at a specific date.
     */
    public function scheduleFollowUpAt(Quotation $quotation, DateTime|string $date): Quotation
    {
        $quotation->next_follow_up_at = $date;
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Clear scheduled follow-up.
     */
    public function clearFollowUp(Quotation $quotation): Quotation
    {
        $quotation->next_follow_up_at = null;
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Record a contact activity and update tracking.
     */
    public function recordContact(Quotation $quotation): Quotation
    {
        return $this->executeInTransaction('record_contact', function () use ($quotation) {
            $quotation->last_contacted_at = now();
            $quotation->follow_up_count = ($quotation->follow_up_count ?? 0) + 1;
            $quotation->save();

            return $quotation->fresh();
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Calculate auto follow-up date based on quotation stage.
     */
    public function calculateAutoFollowUpDate(Quotation $quotation): ?DateTime
    {
        // If already has outcome, no follow-up needed
        if ($quotation->outcome !== null) {
            return null;
        }

        // Follow-up schedule based on status and time elapsed
        return match ($quotation->status) {
            DocumentStatus::Submitted => now()->addDays(3)->toDateTime(),
            DocumentStatus::Approved => now()->addDays(7)->toDateTime(),
            default => null,
        };
    }

    /**
     * Set auto follow-up if not already scheduled.
     */
    public function setAutoFollowUpIfNeeded(Quotation $quotation): Quotation
    {
        if ($quotation->next_follow_up_at !== null) {
            return $quotation;
        }

        $autoDate = $this->calculateAutoFollowUpDate($quotation);

        if ($autoDate !== null) {
            $quotation->next_follow_up_at = $autoDate;
            $quotation->save();
        }

        return $quotation->fresh();
    }

    /**
     * Assign quotation to a user for follow-up.
     */
    public function assignTo(Quotation $quotation, int $userId): Quotation
    {
        $quotation->assigned_to = $userId;
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Update quotation priority.
     */
    public function updatePriority(Quotation $quotation, string $priority): Quotation
    {
        $quotation->priority = QuotationPriority::from($priority);
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Check if quotation needs follow-up.
     */
    public function needsFollowUp(Quotation $quotation): bool
    {
        if ($quotation->next_follow_up_at === null) {
            return false;
        }

        return $quotation->next_follow_up_at->isPast() && $quotation->outcome === null;
    }

    /**
     * Get days since last contact.
     */
    public function getDaysSinceLastContact(Quotation $quotation): ?int
    {
        if ($quotation->last_contacted_at === null) {
            return null;
        }

        return (int) $quotation->last_contacted_at->diffInDays(now());
    }
}
