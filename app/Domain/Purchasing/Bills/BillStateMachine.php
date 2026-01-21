<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;

class BillStateMachine extends AbstractStateMachine
{
    private Bill $bill;

    public function __construct(Bill $bill, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->bill = $bill;
        parent::__construct($bill->status, $eventDispatcher);
    }

    public static function fromBill(
        Bill $bill,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($bill, $eventDispatcher);
    }

    protected function registerGuards(): void
    {
        // Guard: Can only receive if has items
        $this->addGuard(
            DocumentStatus::Draft->value,
            DocumentStatus::Received->value,
            fn () => [
                'passes' => $this->bill->items()->exists(),
                'message' => 'Tagihan tidak memiliki item.',
            ]
        );

        // Guard: Can only mark paid if fully paid
        $this->addGuard(
            DocumentStatus::Received->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->bill->paid_amount >= $this->bill->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->bill->paid_amount >= $this->bill->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Overdue->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->bill->paid_amount >= $this->bill->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        // Guard: Can only mark overdue if past due date
        $this->addGuard(
            DocumentStatus::Received->value,
            DocumentStatus::Overdue->value,
            fn () => [
                'passes' => $this->bill->due_date->isPast(),
                'message' => 'Tagihan belum melewati tanggal jatuh tempo.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Overdue->value,
            fn () => [
                'passes' => $this->bill->due_date->isPast(),
                'message' => 'Tagihan belum melewati tanggal jatuh tempo.',
            ]
        );

        // Guard: Can only mark partial if has partial payment
        $this->addGuard(
            DocumentStatus::Received->value,
            DocumentStatus::Partial->value,
            fn () => [
                'passes' => $this->bill->paid_amount > 0 && $this->bill->paid_amount < $this->bill->total_amount,
                'message' => 'Status partial membutuhkan pembayaran sebagian.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Overdue->value,
            DocumentStatus::Partial->value,
            fn () => [
                'passes' => $this->bill->paid_amount > 0 && $this->bill->paid_amount < $this->bill->total_amount,
                'message' => 'Status partial membutuhkan pembayaran sebagian.',
            ]
        );
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->bill->recordStatusChange(
            $from->value,
            $to->value,
            $this->getContextUserId(),
            $this->transitionContext
        );
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Received->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Received->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Partial->value => [
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Paid->value => [
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Overdue->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Cancelled->value => [],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'bill_id' => $this->bill->id,
            'bill_number' => $this->bill->bill_number,
            'total_amount' => $this->bill->total_amount,
            'currency' => $this->bill->currency,
            'vendor_id' => $this->bill->contact_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->bill->status = $status;
        $this->bill->save();
    }

    protected function getDocumentType(): string
    {
        return 'Tagihan';
    }

    protected function getDocumentId(): int
    {
        return $this->bill->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Purchasing\Bills\Events\BillStatusChanged::class;
    }

    // Business rule helpers (delegate to canTransitionTo with guards)

    public function canPost(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Received);
    }

    public function canMarkAsPartial(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Partial);
    }

    public function canMarkAsPaid(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Paid);
    }

    public function canMarkAsOverdue(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Overdue);
    }

    public function canCancel(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Cancelled);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && ! $this->bill->payments()->exists();
    }

    /**
     * Get the bill model.
     */
    public function getBill(): Bill
    {
        return $this->bill;
    }

    // Event dispatching hooks (fires domain events after status changes)

    protected function afterReceived(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(Events\BillReceived::fromBill($this->bill, $this->getContextUserId()));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(Events\BillVoided::fromBill($this->bill, $this->getContextUserId()));
    }
}
