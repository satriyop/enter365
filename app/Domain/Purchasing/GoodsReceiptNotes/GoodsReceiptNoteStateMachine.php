<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\GoodsReceiptNotes;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Purchasing\GoodsReceiptNotes\Events\GoodsReceiptNoteStatusChanged;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\GoodsReceiptNote;

/**
 * State machine for Goods Receipt Note workflow.
 *
 * Workflow: Draft → Receiving → Completed
 *           ↓         ↓
 *       Cancelled  Cancelled
 */
class GoodsReceiptNoteStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private GoodsReceiptNote $grn;

    public function __construct(
        DocumentStatus $initialStatus,
        GoodsReceiptNote $grn,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->grn = $grn;
    }

    public static function fromGoodsReceiptNote(
        GoodsReceiptNote $grn,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($grn->status, $grn, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Receiving->value,
                DocumentStatus::Completed->value, // Direct complete from draft is allowed
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Receiving->value => [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Completed->value => [], // Terminal
            DocumentStatus::Cancelled->value => [], // Terminal
        ];
    }

    protected function getContextData(): array
    {
        return [
            'grn_id' => $this->grn->id,
            'grn_number' => $this->grn->grn_number,
            'purchase_order_id' => $this->grn->purchase_order_id,
            'warehouse_id' => $this->grn->warehouse_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->grn->status = $status;
        $this->grn->save();
    }

    protected function getDocumentType(): string
    {
        return 'Goods Receipt Note';
    }

    protected function getDocumentId(): int
    {
        return $this->grn->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return GoodsReceiptNoteStatusChanged::class;
    }

    // Convenience methods

    public function canStartReceiving(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->grn->items()->exists();
    }

    public function canComplete(): bool
    {
        return in_array($this->currentStatus, [DocumentStatus::Draft, DocumentStatus::Receiving], true)
            && $this->grn->items()->where('quantity_received', '>', 0)->exists();
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Receiving,
        ], true);
    }

    public function canEdit(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Receiving,
        ], true);
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Receiving && ! $this->grn->items()->exists()) {
            throw new \InvalidArgumentException('GRN tidak memiliki item untuk diterima.');
        }

        if ($to === DocumentStatus::Completed && ! $this->grn->items()->where('quantity_received', '>', 0)->exists()) {
            throw new \InvalidArgumentException('GRN tidak dapat diselesaikan. Pastikan ada item yang telah diterima.');
        }
    }

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->updateTimestamps($to);
    }

    private function updateTimestamps(DocumentStatus $status): void
    {
        $userId = $this->getContextUserId();

        $updates = match ($status) {
            DocumentStatus::Receiving => ['received_by' => $userId],
            DocumentStatus::Completed => ['checked_by' => $userId, 'completed_at' => now()],
            DocumentStatus::Cancelled => ['cancelled_at' => now()],
            default => [],
        };

        if (! empty($updates)) {
            $this->grn->update($updates);
        }
    }
}
