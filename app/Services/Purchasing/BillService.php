<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Shared\DocumentNumberGeneratorInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Services\Base\AbstractDocumentService;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class BillService extends AbstractDocumentService implements BillServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private DocumentNumberGeneratorInterface $numberGenerator
    ) {}

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

    protected function getInitialStatus(): string
    {
        return DocumentStatus::Draft->value;
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

        $bill->transitionTo(DocumentStatus::Received, auth()->id());

        return $bill->fresh(['contact', 'items', 'journalEntry.lines.account']);
    }
}
