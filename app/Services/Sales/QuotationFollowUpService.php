<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\QuotationFollowUpServiceInterface;
use App\Domain\Sales\Quotations\Enums\QuotationPriority;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Services\Base\BaseService;
use Carbon\Carbon;
use DateTime;

/**
 * Service for quotation follow-up management.
 *
 * Handles scheduling follow-ups, recording contact activities,
 * and calculating auto follow-up dates.
 */
class QuotationFollowUpService extends BaseService implements QuotationFollowUpServiceInterface
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
        $quotation->next_follow_up_at = Carbon::parse($date);
        $quotation->save();

        return $quotation->fresh();
    }

    /**
     * Schedule follow-up and optionally record a notes activity.
     *
     * @param  array{next_follow_up_at: string|\DateTimeInterface, notes?: string|null}  $data
     */
    public function scheduleFollowUpWithNotes(Quotation $quotation, array $data, ?int $userId = null): Quotation
    {
        return $this->executeInTransaction('schedule_follow_up_with_notes', function () use ($quotation, $data, $userId) {
            $this->scheduleFollowUpAt($quotation, $data['next_follow_up_at']);

            if (! empty($data['notes'])) {
                $quotation->activities()->create([
                    'user_id' => $userId ?? $this->getUserId(),
                    'type' => QuotationActivity::TYPE_FOLLOW_UP_SCHEDULED,
                    'description' => $data['notes'],
                    'activity_at' => now(),
                    'next_follow_up_at' => $data['next_follow_up_at'],
                ]);
            }

            return $quotation->fresh(['contact', 'assignedTo']);
        }, ['quotation_id' => $quotation->id]);
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
     * Create a quotation activity and update contact tracking / next follow-up.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeActivity(Quotation $quotation, array $data, ?int $userId = null): QuotationActivity
    {
        return $this->executeInTransaction('store_activity', function () use ($quotation, $data, $userId) {
            $nextFollowUpAt = $data['next_follow_up_at'] ?? null;

            $activity = $quotation->activities()->create([
                ...$data,
                'user_id' => $userId ?? $this->getUserId(),
            ]);

            $this->recordContact($quotation);

            if ($nextFollowUpAt !== null) {
                $this->scheduleFollowUpAt($quotation, $nextFollowUpAt);
            }

            return $activity->load('user');
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Calculate auto follow-up date based on quotation stage.
     */
    public function calculateAutoFollowUpDate(Quotation $quotation): ?Carbon
    {
        // If already has outcome, no follow-up needed
        if ($quotation->outcome !== null) {
            return null;
        }

        // Follow-up schedule based on status and time elapsed
        return match ($quotation->status) {
            DocumentStatus::Submitted => now()->addDays(3),
            DocumentStatus::Approved => now()->addDays(7),
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
