<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Services\Base\BaseService;

/**
 * Service for quotation outcome management.
 *
 * Handles marking quotations as won or lost with proper validation
 * and activity tracking.
 *
 * Note: Renamed from QuotationWorkflowService to avoid collision with
 * Quotation\QuotationWorkflowService which handles state transitions.
 */
class QuotationOutcomeService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Mark quotation as won.
     *
     * @param  array{won_reason?: string, outcome_notes?: string}  $data
     *
     * @throws \App\Exceptions\Domain\StateTransitionException
     * @throws \App\Exceptions\Domain\BusinessRuleException
     */
    public function markAsWon(Quotation $quotation, array $data = []): Quotation
    {
        if ($quotation->status !== DocumentStatus::Approved) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Quotation',
                'ditandai sebagai menang',
                $quotation->status->value,
                'approved'
            );
        }

        if ($quotation->outcome !== null) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menandai quotation sebagai menang',
                'Quotation sudah memiliki hasil'
            );
        }

        return $this->executeInTransaction('mark_as_won', function () use ($quotation, $data) {
            $quotation->outcome = QuotationOutcome::Won;
            $quotation->won_reason = $data['won_reason'] ?? null;
            $quotation->outcome_notes = $data['outcome_notes'] ?? null;
            $quotation->outcome_at = now();
            $quotation->next_follow_up_at = null; // Clear follow-up when won
            $quotation->save();

            $quotation->activities()->create([
                'user_id' => $this->getUserId(),
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Menang',
                'description' => $data['outcome_notes'] ?? 'Penawaran ditandai sebagai menang.',
                'activity_at' => now(),
            ]);

            return $quotation->fresh();
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Mark quotation as lost.
     *
     * @param  array{lost_reason?: string, lost_to_competitor?: string, outcome_notes?: string}  $data
     *
     * @throws \App\Exceptions\Domain\StateTransitionException
     * @throws \App\Exceptions\Domain\BusinessRuleException
     */
    public function markAsLost(Quotation $quotation, array $data = []): Quotation
    {
        if (! in_array($quotation->status, [DocumentStatus::Approved, DocumentStatus::Submitted])) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Quotation',
                'ditandai sebagai kalah',
                $quotation->status->value,
                'submitted atau approved'
            );
        }

        if ($quotation->outcome !== null) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menandai quotation sebagai kalah',
                'Quotation sudah memiliki hasil'
            );
        }

        return $this->executeInTransaction('mark_as_lost', function () use ($quotation, $data) {
            $quotation->outcome = QuotationOutcome::Lost;
            $quotation->lost_reason = $data['lost_reason'] ?? null;
            $quotation->lost_to_competitor = $data['lost_to_competitor'] ?? null;
            $quotation->outcome_notes = $data['outcome_notes'] ?? null;
            $quotation->outcome_at = now();
            $quotation->next_follow_up_at = null; // Clear follow-up when lost
            $quotation->save();

            $quotation->activities()->create([
                'user_id' => $this->getUserId(),
                'type' => QuotationActivity::TYPE_STATUS_CHANGE,
                'subject' => 'Penawaran Kalah',
                'description' => $data['outcome_notes'] ?? 'Penawaran ditandai sebagai kalah.',
                'activity_at' => now(),
            ]);

            return $quotation->fresh();
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Check if quotation can be marked as won.
     */
    public function canMarkAsWon(Quotation $quotation): bool
    {
        return $quotation->status === DocumentStatus::Approved
            && $quotation->outcome === null;
    }

    /**
     * Check if quotation can be marked as lost.
     */
    public function canMarkAsLost(Quotation $quotation): bool
    {
        return in_array($quotation->status, [DocumentStatus::Approved, DocumentStatus::Submitted])
            && $quotation->outcome === null;
    }
}
