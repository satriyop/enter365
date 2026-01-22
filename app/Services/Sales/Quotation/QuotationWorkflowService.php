<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\Sales\QuotationRepositoryInterface;
use App\Domain\Sales\Quotations\QuotationDomainFactory;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Services\Base\BaseService;
use InvalidArgumentException;

/**
 * Handles workflow state transitions for quotations.
 *
 * Extracted from QuotationService as part of the Coordinator Pattern refactoring.
 * This service focuses on submit, approve, reject, cancel, and lifecycle operations.
 *
 * @see \App\Services\Sales\QuotationService The coordinator service
 */
class QuotationWorkflowService extends BaseService
{
    private const EXPIRABLE_STATUSES = [
        DocumentStatus::Draft,
        DocumentStatus::Submitted,
    ];

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private QuotationRepositoryInterface $repository,
        private QuotationDomainFactory $domainFactory,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Submit quotation for approval.
     */
    public function submit(Quotation $quotation, ?int $userId = null): Quotation
    {
        return $this->executeInTransaction('submit', function () use ($quotation, $userId) {
            $stateMachine = $this->domainFactory->stateMachine($quotation);

            if (! $stateMachine->canSubmit()) {
                throw new InvalidArgumentException(
                    'Penawaran tidak dapat diajukan. Pastikan status draft dan memiliki item.'
                );
            }

            $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $userId ?? $this->getUserId()]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Approve a quotation.
     */
    public function approve(Quotation $quotation, ?int $userId = null): Quotation
    {
        return $this->executeInTransaction('approve', function () use ($quotation, $userId) {
            $stateMachine = $this->domainFactory->stateMachine($quotation);

            if (! $stateMachine->canApprove()) {
                throw new InvalidArgumentException(
                    'Penawaran tidak dapat disetujui. Pastikan sudah diajukan dan belum kedaluwarsa.'
                );
            }

            $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $userId ?? $this->getUserId()]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id]);
    }

    /**
     * Reject a quotation.
     */
    public function reject(Quotation $quotation, string $reason, ?int $userId = null): Quotation
    {
        return $this->executeInTransaction('reject', function () use ($quotation, $reason, $userId) {
            $stateMachine = $this->domainFactory->stateMachine($quotation);

            if (! $stateMachine->canReject()) {
                throw new InvalidArgumentException(
                    'Penawaran tidak dapat ditolak. Pastikan sudah diajukan.'
                );
            }

            if (empty($reason)) {
                throw new InvalidArgumentException('Alasan penolakan harus diisi.');
            }

            $stateMachine->transitionTo(DocumentStatus::Rejected, [
                'user_id' => $userId ?? $this->getUserId(),
                'rejection_reason' => $reason,
            ]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id, 'reason' => $reason]);
    }

    /**
     * Cancel a quotation.
     */
    public function cancel(Quotation $quotation, ?string $reason = null, ?int $userId = null): Quotation
    {
        return $this->executeInTransaction('cancel', function () use ($quotation, $reason, $userId) {
            $stateMachine = $this->domainFactory->stateMachine($quotation);

            if (! $stateMachine->canCancel()) {
                throw new InvalidArgumentException(
                    'Penawaran tidak dapat dibatalkan. Hanya penawaran dengan status Draft, Submitted, atau Approved yang dapat dibatalkan.'
                );
            }

            // Clear any scheduled follow-ups
            $quotation->next_follow_up_at = null;
            $quotation->save();

            $stateMachine->transitionTo(DocumentStatus::Cancelled, [
                'user_id' => $userId ?? $this->getUserId(),
                'cancellation_reason' => $reason,
            ]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id, 'reason' => $reason]);
    }

    /**
     * Mark quotation as sent to customer.
     */
    public function markAsSent(
        Quotation $quotation,
        ?string $email = null,
        ?string $via = 'email'
    ): Quotation {
        if ($quotation->status !== DocumentStatus::Approved) {
            throw new InvalidArgumentException(
                'Hanya penawaran yang sudah disetujui yang dapat ditandai sebagai terkirim.'
            );
        }

        if ($quotation->isSent()) {
            throw new InvalidArgumentException(
                'Penawaran sudah ditandai sebagai terkirim pada '.$quotation->sent_at->format('d/m/Y H:i').'.'
            );
        }

        return $this->executeInTransaction('mark_as_sent', function () use ($quotation, $email, $via) {
            $this->repository->update($quotation, [
                'sent_at' => now(),
                'sent_by' => $this->getUserId(),
                'sent_to_email' => $email ?? $quotation->contact?->email,
                'sent_via' => $via,
            ]);

            return $this->loadRelations($quotation);
        }, ['quotation_id' => $quotation->id, 'via' => $via]);
    }

    /**
     * Mark expired quotations.
     *
     * @return int Number of quotations marked as expired
     */
    public function markExpired(): int
    {
        $count = 0;

        Quotation::query()
            ->where('valid_until', '<', now()->startOfDay())
            ->where(function ($query) {
                $query->whereIn('status', self::EXPIRABLE_STATUSES)
                    ->orWhere(function ($q) {
                        $q->where('status', DocumentStatus::Approved)
                            ->whereNull('sent_at');
                    });
            })
            ->chunkById(100, function ($quotations) use (&$count) {
                foreach ($quotations as $quotation) {
                    try {
                        $stateMachine = $this->domainFactory->stateMachine($quotation);
                        if ($stateMachine->canTransitionTo(DocumentStatus::Expired)) {
                            $stateMachine->transitionTo(DocumentStatus::Expired);
                            $count++;
                        }
                    } catch (\Exception $e) {
                        report($e);
                    }
                }
            });

        return $count;
    }

    private function loadRelations(Quotation $quotation): Quotation
    {
        return $quotation->load(['items', 'contact']);
    }
}
