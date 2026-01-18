<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Sales\Invoice;

class InvoiceStateMachine extends AbstractStateMachine
{
    private Invoice $invoice;

    public function __construct(Invoice $invoice, ?EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct($invoice->status, $eventDispatcher);
        $this->invoice = $invoice;
    }

    public static function fromInvoice(
        Invoice $invoice,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($invoice, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Sent->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Sent->value => [
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
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => $this->invoice->total_amount,
            'currency' => $this->invoice->currency,
            'customer_id' => $this->invoice->contact_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->invoice->status = $status;
        $this->invoice->save();
    }

    protected function getDocumentType(): string
    {
        return 'Faktur';
    }

    protected function getDocumentId(): int
    {
        return $this->invoice->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return InvoiceStatusChanged::class;
    }

    public function canPost(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->invoice->items()->exists();
    }

    public function canMarkAsPartial(): bool
    {
        if ($this->currentStatus === DocumentStatus::Partial) {
            return true;
        }

        return in_array($this->currentStatus, [DocumentStatus::Sent, DocumentStatus::Overdue], true)
            && $this->invoice->paid_amount > 0
            && $this->invoice->paid_amount < $this->invoice->total_amount;
    }

    public function canMarkAsPaid(): bool
    {
        return $this->invoice->paid_amount >= $this->invoice->total_amount;
    }

    public function canMarkAsOverdue(): bool
    {
        return in_array($this->currentStatus, [DocumentStatus::Sent, DocumentStatus::Partial], true)
            && $this->invoice->due_date->isPast();
    }

    public function canVoid(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Sent,
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
            && ! $this->invoice->payments()->exists();
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Sent,
            DocumentStatus::Partial,
            DocumentStatus::Paid,
            DocumentStatus::Overdue,
        ], true);
    }

    protected function beforeSent(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->invoice->items()->exists()) {
            throw StateTransitionException::actionNotAvailable('kirim', 'draft', 'Faktur tidak memiliki item.');
        }
    }

    protected function afterSent(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(InvoiceSent::fromInvoice($this->invoice, $this->getContextUserId()));
    }

    protected function afterVoid(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(InvoiceVoided::fromInvoice($this->invoice, $this->getContextUserId()));
    }
}
