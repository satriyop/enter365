<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Bills;

use App\Domain\Core\AbstractStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Purchasing\Bill;
use Illuminate\Support\Facades\Event;

class BillStateMachine extends AbstractStateMachine
{
    private Bill $bill;

    public function __construct(Bill $bill)
    {
        parent::__construct($bill->status);
        $this->bill = $bill;
    }

    public static function fromBill(Bill $bill): self
    {
        return new self($bill);
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

    public function canPost(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->bill->items()->exists();
    }

    public function canMarkAsPartial(): bool
    {
        if ($this->currentStatus === DocumentStatus::Partial) {
            return true;
        }

        return in_array($this->currentStatus, [DocumentStatus::Received, DocumentStatus::Overdue], true)
            && $this->bill->paid_amount > 0
            && $this->bill->paid_amount < $this->bill->total_amount;
    }

    public function canMarkAsPaid(): bool
    {
        return $this->bill->paid_amount >= $this->bill->total_amount;
    }

    public function canMarkAsOverdue(): bool
    {
        return in_array($this->currentStatus, [DocumentStatus::Received, DocumentStatus::Partial], true)
            && $this->bill->due_date->isPast();
    }

    public function canVoid(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Received,
            DocumentStatus::Partial,
            DocumentStatus::Paid,
            DocumentStatus::Overdue,
        ], true);
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

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Received,
            DocumentStatus::Partial,
            DocumentStatus::Paid,
            DocumentStatus::Overdue,
        ], true);
    }

    protected function beforeReceived(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->bill->items()->exists()) {
            throw StateTransitionException::actionNotAvailable('terima', 'draft', 'Tagihan tidak memiliki item.');
        }
    }

    protected function afterReceived(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(\App\Domain\Purchasing\Bills\Events\BillReceived::fromBill($this->bill, $this->getContextUserId()));
    }

    protected function afterVoid(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(\App\Domain\Purchasing\Bills\Events\BillVoided::fromBill($this->bill, $this->getContextUserId()));
    }
}
