<?php

declare(strict_types=1);

namespace App\Domain\Sales\Quotations;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;

class QuotationStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private Quotation $quotation;

    public function __construct(
        DocumentStatus $initialStatus,
        Quotation $quotation,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->quotation = $quotation;
    }

    public static function fromQuotation(
        Quotation $quotation,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($quotation->status, $quotation, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Submitted->value,
                DocumentStatus::Cancelled->value,
                DocumentStatus::Expired->value,
            ],
            DocumentStatus::Submitted->value => [
                DocumentStatus::Approved->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Cancelled->value,
                DocumentStatus::Expired->value,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Converted->value,
                DocumentStatus::Cancelled->value,
                DocumentStatus::Expired->value,
            ],
            DocumentStatus::Rejected->value => [
                DocumentStatus::Draft->value,
            ],
            DocumentStatus::Expired->value => [
                DocumentStatus::Draft->value,
            ],
            DocumentStatus::Cancelled->value => [
                DocumentStatus::Draft->value, // Allow revision of cancelled quotations
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'quotation_id' => $this->quotation->id,
            'quotation_number' => $this->quotation->quotation_number,
            'contact_id' => $this->quotation->contact_id,
            'total_amount' => $this->quotation->total_amount,
            'currency' => $this->quotation->currency,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->quotation->status = $status;
        $this->quotation->save();
    }

    protected function getDocumentType(): string
    {
        return 'Penawaran';
    }

    protected function getDocumentId(): int
    {
        return $this->quotation->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Sales\Quotations\Events\QuotationStatusChanged::class;
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->quotation->recordStatusChange(
            $from->value,
            $to->value,
            $this->getContextUserId(),
            $this->transitionContext
        );
    }

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->quotation->items()->exists();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted
            && ! $this->quotation->valid_until->isPast();
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canConvert(): bool
    {
        // Must be Approved
        if ($this->currentStatus !== DocumentStatus::Approved) {
            return false;
        }

        // Must not already be converted
        if ($this->quotation->converted_to_invoice_id !== null) {
            return false;
        }

        // Multi-option quotations require variant selection
        if ($this->quotation->isMultiOption() && ! $this->quotation->hasSelectedVariant()) {
            return false;
        }

        return true;
    }

    /**
     * Get reason why conversion is blocked.
     *
     * Returns null if conversion is allowed.
     */
    public function getConversionBlockReason(): ?string
    {
        if ($this->currentStatus !== DocumentStatus::Approved) {
            return 'Penawaran harus disetujui sebelum dikonversi ke faktur.';
        }

        if ($this->quotation->converted_to_invoice_id !== null) {
            return 'Penawaran sudah dikonversi ke faktur.';
        }

        if ($this->quotation->isMultiOption() && ! $this->quotation->hasSelectedVariant()) {
            return 'Pilih opsi varian terlebih dahulu sebelum mengkonversi ke faktur.';
        }

        return null;
    }

    public function canRevise(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Expired,
            DocumentStatus::Cancelled,
        ], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ], true);
    }

    public function canMarkAsWon(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ], true);
    }

    public function canMarkAsLost(): bool
    {
        return $this->canMarkAsWon();
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->canEdit();
    }

    public function isExpired(): bool
    {
        return $this->currentStatus === DocumentStatus::Expired
            || $this->quotation->valid_until->isPast();
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Submitted && ! $this->quotation->items()->exists()) {
            throw new \InvalidArgumentException('Penawaran tidak dapat diajukan karena tidak memiliki item.');
        }

        if (in_array($to, [DocumentStatus::Approved, DocumentStatus::Rejected], true)
            && $from === DocumentStatus::Submitted
            && $this->quotation->valid_until->isPast()) {
            throw new \InvalidArgumentException('Penawaran sudah kedaluwarsa.');
        }
    }

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->updateTimestamps($from, $to);
    }

    private function updateTimestamps(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $updates = [];

        switch ($to) {
            case DocumentStatus::Submitted:
                $updates['submitted_at'] = now();
                $updates['submitted_by'] = $userId;
                break;
            case DocumentStatus::Approved:
                $updates['approved_at'] = now();
                $updates['approved_by'] = $userId;
                break;
            case DocumentStatus::Rejected:
                $updates['rejected_at'] = now();
                $updates['rejected_by'] = $userId;
                $updates['rejection_reason'] = $this->context['rejection_reason'] ?? '';
                break;
            case DocumentStatus::Cancelled:
                $updates['cancelled_at'] = now();
                $updates['cancelled_by'] = $userId;
                $updates['cancellation_reason'] = $this->context['cancellation_reason'] ?? null;
                break;
        }

        if (! empty($updates)) {
            $this->quotation->update($updates);
        }
    }

    protected function beforeSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationSubmitted(
            $this->quotation->id,
            $this->quotation->quotation_number,
            $this->quotation->contact_id,
            $this->quotation->total_amount,
            $this->quotation->currency,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationApproved(
            $this->quotation->id,
            $this->quotation->quotation_number,
            $this->quotation->contact_id,
            $this->quotation->total_amount,
            $this->quotation->currency,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        $reason = $this->context['rejection_reason'] ?? '';
        $this->eventDispatcher->dispatch(new Events\QuotationRejected(
            $this->quotation->id,
            $this->quotation->quotation_number,
            $this->quotation->contact_id,
            $this->quotation->total_amount,
            $this->quotation->currency,
            $this->getContextUserId(),
            now(),
            $reason
        ));
    }

    protected function afterRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeConverted(DocumentStatus $from, DocumentStatus $to): void
    {
        $invoiceId = $this->quotation->converted_to_invoice_id ?? 0;
        $this->eventDispatcher->dispatch(new Events\QuotationConverted(
            $this->quotation->id,
            $this->quotation->quotation_number,
            $this->quotation->contact_id,
            $this->quotation->total_amount,
            $this->quotation->currency,
            $invoiceId,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterConverted(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeExpired(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationExpired(
            $this->quotation->id,
            $this->quotation->quotation_number,
            $this->quotation->contact_id,
            $this->quotation->total_amount,
            $this->quotation->currency,
            $this->quotation->valid_until,
            now()
        ));
    }

    protected function afterExpired(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $reason = $this->context['cancellation_reason'] ?? null;
        $this->eventDispatcher->dispatch(Events\QuotationCancelled::fromQuotation(
            $this->quotation,
            $from,
            $this->getContextUserId(),
            $reason
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\QuotationStatusChanged(
            $this->quotation->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }
}
