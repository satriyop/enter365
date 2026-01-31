<?php

declare(strict_types=1);

namespace App\Domain\Sales\Invoices;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\Sales\Invoices\Events\InvoiceSent;
use App\Domain\Sales\Invoices\Events\InvoiceStatusChanged;
use App\Domain\Sales\Invoices\Events\InvoiceVoided;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;

class InvoiceStateMachine extends AbstractStateMachine
{
    private Invoice $invoice;

    public function __construct(Invoice $invoice, ?EventDispatcherInterface $eventDispatcher = null)
    {
        $this->invoice = $invoice;
        parent::__construct($invoice->status, $eventDispatcher);
    }

    public static function fromInvoice(
        Invoice $invoice,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($invoice, $eventDispatcher);
    }

    protected function registerGuards(): void
    {
        // Guard: Can only post (send) if has items
        $this->addGuard(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            fn () => [
                'passes' => isset($this->invoice->items_count)
                    ? $this->invoice->items_count > 0
                    : $this->invoice->items()->exists(),
                'message' => 'Faktur tidak memiliki item.',
            ]
        );

        // Guard: Can only mark paid if fully paid
        $this->addGuard(
            DocumentStatus::Sent->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->invoice->paid_amount >= $this->invoice->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->invoice->paid_amount >= $this->invoice->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Overdue->value,
            DocumentStatus::Paid->value,
            fn () => [
                'passes' => $this->invoice->paid_amount >= $this->invoice->total_amount,
                'message' => 'Jumlah pembayaran belum mencukupi.',
            ]
        );

        // Guard: Can only mark overdue if past due date
        $this->addGuard(
            DocumentStatus::Sent->value,
            DocumentStatus::Overdue->value,
            fn () => [
                'passes' => $this->invoice->due_date->isPast(),
                'message' => 'Faktur belum melewati tanggal jatuh tempo.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Overdue->value,
            fn () => [
                'passes' => $this->invoice->due_date->isPast(),
                'message' => 'Faktur belum melewati tanggal jatuh tempo.',
            ]
        );

        // Guard: Can only mark partial if has partial payment
        $this->addGuard(
            DocumentStatus::Sent->value,
            DocumentStatus::Partial->value,
            fn () => [
                'passes' => $this->invoice->paid_amount > 0 && $this->invoice->paid_amount < $this->invoice->total_amount,
                'message' => 'Status partial membutuhkan pembayaran sebagian.',
            ]
        );

        $this->addGuard(
            DocumentStatus::Overdue->value,
            DocumentStatus::Partial->value,
            fn () => [
                'passes' => $this->invoice->paid_amount > 0 && $this->invoice->paid_amount < $this->invoice->total_amount,
                'message' => 'Status partial membutuhkan pembayaran sebagian.',
            ]
        );

        // Guard: Can revert from Paid to Partial if payment reversed
        $this->addGuard(
            DocumentStatus::Paid->value,
            DocumentStatus::Partial->value,
            fn () => [
                'passes' => $this->invoice->paid_amount > 0 && $this->invoice->paid_amount < $this->invoice->total_amount,
                'message' => 'Status partial membutuhkan pembayaran sebagian.',
            ]
        );

        // Guard: Can revert from Paid to Sent if all payments reversed
        $this->addGuard(
            DocumentStatus::Paid->value,
            DocumentStatus::Sent->value,
            fn () => [
                'passes' => $this->invoice->paid_amount === 0,
                'message' => 'Faktur masih memiliki pembayaran.',
            ]
        );

        // Guard: Can revert from Partial to Sent if all payments reversed
        $this->addGuard(
            DocumentStatus::Partial->value,
            DocumentStatus::Sent->value,
            fn () => [
                'passes' => $this->invoice->paid_amount === 0,
                'message' => 'Faktur masih memiliki pembayaran.',
            ]
        );
    }

    protected function registerActions(): void
    {
        // Action: Fire InvoiceSent when posted
        $this->addAction(
            DocumentStatus::Draft->value,
            DocumentStatus::Sent->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceSent::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );

        // Action: Fire InvoiceVoided when cancelled
        $this->addAction(
            DocumentStatus::Sent->value,
            DocumentStatus::Cancelled->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceVoided::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );

        $this->addAction(
            DocumentStatus::Partial->value,
            DocumentStatus::Cancelled->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceVoided::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );

        $this->addAction(
            DocumentStatus::Paid->value,
            DocumentStatus::Cancelled->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceVoided::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );

        $this->addAction(
            DocumentStatus::Overdue->value,
            DocumentStatus::Cancelled->value,
            fn () => $this->eventDispatcher->dispatch(
                InvoiceVoided::fromInvoice($this->invoice, $this->getContextUserId())
            )
        );
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->invoice->recordStatusChange(
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
                DocumentStatus::Sent->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Paid->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Sent->value,
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

    // Business rule helpers (delegate to canTransitionTo with guards)

    public function canPost(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Sent);
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
        $hasPayments = isset($this->invoice->payments_count)
            ? $this->invoice->payments_count > 0
            : $this->invoice->payments()->exists();

        return $this->currentStatus === DocumentStatus::Draft
            && ! $hasPayments;
    }

    /**
     * Get the invoice model.
     */
    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }
}
