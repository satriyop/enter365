<?php

declare(strict_types=1);

namespace App\Domain\Solar\Proposals;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Solar\Proposals\Events\SolarProposalStatusChanged;
use App\Enums\DocumentStatus;
use App\Models\Solar\SolarProposal;

/**
 * State machine for Solar Proposal workflow.
 *
 * Workflow: Draft → Sent → Accepted
 *                    ↓
 *               Rejected
 *
 * Expired can transition to Expired status from Sent.
 */
class SolarProposalStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private SolarProposal $proposal;

    public function __construct(
        DocumentStatus $initialStatus,
        SolarProposal $proposal,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->proposal = $proposal;
    }

    public static function fromSolarProposal(
        SolarProposal $proposal,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($proposal->status, $proposal, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Sent->value,
            ],
            DocumentStatus::Sent->value => [
                DocumentStatus::Accepted->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Expired->value,
            ],
            DocumentStatus::Accepted->value => [], // Terminal
            DocumentStatus::Rejected->value => [], // Terminal
            DocumentStatus::Expired->value => [], // Terminal
        ];
    }

    protected function getContextData(): array
    {
        return [
            'proposal_id' => $this->proposal->id,
            'proposal_number' => $this->proposal->proposal_number,
            'contact_id' => $this->proposal->contact_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->proposal->status = $status;
        $this->proposal->save();
    }

    protected function getDocumentType(): string
    {
        return 'Solar Proposal';
    }

    protected function getDocumentId(): int
    {
        return $this->proposal->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return SolarProposalStatusChanged::class;
    }

    // Convenience methods

    public function canSend(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->proposal->variant_group_id !== null
            && $this->proposal->financial_analysis !== null;
    }

    public function canAccept(): bool
    {
        return $this->currentStatus === DocumentStatus::Sent
            && ! $this->proposal->isExpired();
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Sent
            && ! $this->proposal->isExpired();
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Sent) {
            if ($this->proposal->variant_group_id === null) {
                throw new \InvalidArgumentException('Variant group harus dipilih sebelum mengirim proposal.');
            }
            if ($this->proposal->financial_analysis === null) {
                throw new \InvalidArgumentException('Kalkulasi harus diselesaikan sebelum mengirim proposal.');
            }
        }

        if (($to === DocumentStatus::Accepted || $to === DocumentStatus::Rejected) && $this->proposal->isExpired()) {
            throw new \InvalidArgumentException('Proposal sudah kedaluwarsa.');
        }
    }

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->updateTimestamps($to);
    }

    private function updateTimestamps(DocumentStatus $status): void
    {
        $updates = match ($status) {
            DocumentStatus::Sent => [
                'sent_at' => now(),
                'public_token' => \Illuminate\Support\Str::uuid()->toString(),
                'public_token_expires_at' => $this->proposal->valid_until,
            ],
            DocumentStatus::Accepted => ['accepted_at' => now()],
            DocumentStatus::Rejected => [
                'rejected_at' => now(),
                'rejection_reason' => $this->context['rejection_reason'] ?? null,
            ],
            default => [],
        };

        if (! empty($updates)) {
            $this->proposal->update($updates);
        }
    }
}
