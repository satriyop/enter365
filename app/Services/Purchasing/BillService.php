<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Purchasing\Bills\Events\BillOverdue;
use App\Domain\Purchasing\Bills\Events\BillPartiallyPaid;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BillService extends AbstractDocumentService implements BillServiceInterface
{
    private JournalServiceInterface $journalService;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        JournalServiceInterface $journalService,
        DocumentNumberGeneratorInterface $numberGenerator
    ) {
        parent::__construct($eventDispatcher, $logger, numberGenerator: $numberGenerator);

        $this->journalService = $journalService;
    }

    protected function getModelClass(): string
    {
        return Bill::class;
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    protected function generateDocumentNumber(?Model $context = null): string
    {
        $prefix = 'BILL-'.now()->format('Ym').'-';

        return $this->numberGenerator->generate($prefix, 'bills', 'bill_number');
    }

    protected function getDocumentNumberField(): string
    {
        return 'bill_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'BILL-'.now()->format('Ym').'-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return ['table' => 'bills', 'column' => 'bill_number'];
    }

    protected function getInitialStatus(): DocumentStatus
    {
        return DocumentStatus::Draft;
    }

    protected function getDefaultData(): array
    {
        return [
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'tax_rate' => config('accounting.tax.default_rate', 11.00),
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'base_currency_total' => 0,
            'paid_amount' => 0,
        ];
    }

    protected function getEagerLoadRelations(): array
    {
        return ['items', 'contact'];
    }

    /**
     * Create a new bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bill
    {
        return $this->createDocument($data);
    }

    /**
     * Update an existing bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model $document, array $data): Bill
    {
        return $this->updateDocument($document, $data);
    }

    /**
     * Delete a bill.
     */
    public function delete(Model $document): bool
    {
        return $this->deleteDocument($document);
    }

    /**
     * Create items with calculated amount.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function createItems(Model $document, array $items): void
    {
        foreach ($items as $item) {
            $amount = (int) round($item['quantity'] * $item['unit_price']);

            BillItem::create([
                'bill_id' => $document->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'line_total' => $amount,
                'expense_account_id' => $item['expense_account_id'] ?? null,
            ]);
        }
    }

    /**
     * Validate that bill can be edited.
     *
     * @throws InvalidArgumentException
     */
    protected function validateEditable(Model $document): void
    {
        /** @var Bill $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw new InvalidArgumentException('Hanya tagihan draft yang bisa diubah.');
        }
    }

    /**
     * Validate that bill can be deleted.
     *
     * @throws InvalidArgumentException
     */
    protected function validateDeletable(Model $document): void
    {
        /** @var Bill $document */
        if ($document->status !== DocumentStatus::Draft) {
            throw new InvalidArgumentException('Hanya tagihan draft yang bisa dihapus.');
        }

        if ($document->payments()->exists()) {
            throw new InvalidArgumentException('Tidak bisa menghapus tagihan yang sudah memiliki pembayaran.');
        }
    }

    /**
     * Post a bill (create journal entry and change status).
     */
    public function post(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canPost()) {
            throw new InvalidArgumentException('Tagihan sudah diposting.');
        }

        $this->journalService->postBill($bill);

        $bill->transitionTo(DocumentStatus::Received, $this->getUserId());

        return $bill->fresh(['contact', 'items', 'journalEntry.lines.account']);
    }

    /**
     * Mark bill as fully paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPaid(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsPaid()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_paid',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Paid, $this->getUserId());

        event(BillFullyPaid::fromBill($bill, $this->getUserId() ?? 0));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Mark bill as partially paid.
     *
     * @throws StateTransitionException
     */
    public function markAsPartial(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsPartial()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_partial',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Partial, $this->getUserId());

        event(BillPartiallyPaid::fromBill($bill, $this->getUserId() ?? 0));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Mark bill as overdue.
     *
     * @throws StateTransitionException
     */
    public function markAsOverdue(Bill $bill): Bill
    {
        if (! $bill->stateMachine()->canMarkAsOverdue()) {
            throw StateTransitionException::actionNotAvailable(
                'mark_as_overdue',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Overdue, $this->getUserId());

        event(BillOverdue::fromBill($bill));

        return $bill->fresh(['contact', 'items']);
    }

    /**
     * Update bill payment status based on current paid amount.
     *
     * Automatically determines the correct status:
     * - Paid: if paid_amount >= total_amount
     * - Partial: if paid_amount > 0 and < total_amount
     * - Overdue: if past due date and not fully paid
     */
    public function updatePaymentStatus(Bill $bill): Bill
    {
        $bill->refresh();

        // Skip if cancelled
        if ($bill->status === DocumentStatus::Cancelled) {
            return $bill;
        }

        // Skip if still draft
        if ($bill->status === DocumentStatus::Draft) {
            return $bill;
        }

        // Determine target status based on payment
        if ($bill->paid_amount >= $bill->total_amount) {
            if ($bill->status === DocumentStatus::Paid) {
                return $bill;
            }

            return $this->markAsPaid($bill);
        }

        if ($bill->paid_amount > 0) {
            if ($bill->status === DocumentStatus::Partial) {
                return $bill;
            }

            return $this->markAsPartial($bill);
        }

        // No payment - check if overdue
        if ($bill->due_date->isPast() && ! in_array($bill->status, [DocumentStatus::Overdue, DocumentStatus::Paid])) {
            return $this->markAsOverdue($bill);
        }

        return $bill;
    }

    /**
     * Void/cancel a posted bill.
     *
     * @throws StateTransitionException
     */
    public function void(Bill $bill, string $reason): Bill
    {
        if (! $bill->stateMachine()->canCancel()) {
            throw StateTransitionException::actionNotAvailable(
                'void',
                $bill->status->label()
            );
        }

        $bill->transitionTo(DocumentStatus::Cancelled, $this->getUserId());

        return $bill->fresh(['contact', 'items']);
    }
}
