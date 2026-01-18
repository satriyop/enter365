<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Returns;

use App\Contracts\Accounting\Strategies\ReturnAccountingStrategy;
use App\Contracts\Services\Accounting\JournalServiceInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Sales\SalesReturn;

/**
 * Full journal entry strategy for returns.
 *
 * Creates complete reversal journal entries for both sales and purchase returns,
 * including AR/AP and tax reversals. This is the industry-standard approach.
 */
class FullReturnJournalStrategy implements ReturnAccountingStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    /**
     * Create journal entry for sales return.
     *
     * Dr Sales Returns (4-2001) - Contra revenue
     * Dr PPN Keluaran (2-1200) - Reverse output tax
     * Cr Accounts Receivable (1-1100) - Reduce what customer owes
     */
    public function onSalesReturnApprove(SalesReturn $salesReturn): ?JournalEntry
    {
        $salesReturn->loadMissing(['invoice', 'items.product']);

        $lines = [];
        $subtotal = $salesReturn->subtotal;
        $taxAmount = $salesReturn->tax_amount;
        $total = $salesReturn->total_amount;

        // Debit: Sales Returns (contra revenue)
        $salesReturnsAccount = config('accounting.default_accounts.sales_returns', '4-2001');
        $lines[] = [
            'account_code' => $salesReturnsAccount,
            'debit' => $subtotal,
            'credit' => 0,
            'description' => "Retur penjualan: {$salesReturn->return_number}",
        ];

        // Debit: PPN Keluaran (reverse output tax) if tax exists
        if ($taxAmount > 0) {
            $taxPayableAccount = config('accounting.default_accounts.tax_payable', '2-1200');
            $lines[] = [
                'account_code' => $taxPayableAccount,
                'debit' => $taxAmount,
                'credit' => 0,
                'description' => "Reversal PPN Keluaran: {$salesReturn->return_number}",
            ];
        }

        // Credit: Accounts Receivable
        $receivableAccount = $salesReturn->invoice?->receivable_account_id
            ? $salesReturn->invoice->receivableAccount->code
            : config('accounting.default_accounts.accounts_receivable', '1-1100');

        $lines[] = [
            'account_code' => $receivableAccount,
            'debit' => 0,
            'credit' => $total,
            'description' => "Pengurangan piutang: {$salesReturn->return_number}",
        ];

        return $this->journalService->createEntry([
            'entry_date' => $salesReturn->return_date->toDateString(),
            'description' => "Retur penjualan: {$salesReturn->return_number}",
            'reference' => $salesReturn->return_number,
            'source_type' => SalesReturn::class,
            'source_id' => $salesReturn->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    /**
     * Create journal entry for purchase return.
     *
     * Dr Accounts Payable (2-1100) - Reduce what we owe
     * Cr Purchase Returns (5-2001) - Contra expense
     * Cr PPN Masukan (1-1300) - Reverse input tax
     */
    public function onPurchaseReturnApprove(PurchaseReturn $purchaseReturn): ?JournalEntry
    {
        $purchaseReturn->loadMissing(['bill', 'items.product']);

        $lines = [];
        $subtotal = $purchaseReturn->subtotal;
        $taxAmount = $purchaseReturn->tax_amount;
        $total = $purchaseReturn->total_amount;

        // Debit: Accounts Payable (reduce what we owe)
        $payableAccount = $purchaseReturn->bill?->payable_account_id
            ? $purchaseReturn->bill->payableAccount->code
            : config('accounting.default_accounts.accounts_payable', '2-1100');

        $lines[] = [
            'account_code' => $payableAccount,
            'debit' => $total,
            'credit' => 0,
            'description' => "Pengurangan utang: {$purchaseReturn->return_number}",
        ];

        // Credit: Purchase Returns (contra expense)
        $purchaseReturnsAccount = config('accounting.default_accounts.purchase_returns', '5-2001');
        $lines[] = [
            'account_code' => $purchaseReturnsAccount,
            'debit' => 0,
            'credit' => $subtotal,
            'description' => "Retur pembelian: {$purchaseReturn->return_number}",
        ];

        // Credit: PPN Masukan (reverse input tax) if tax exists
        if ($taxAmount > 0) {
            $taxReceivableAccount = config('accounting.default_accounts.tax_receivable', '1-1300');
            $lines[] = [
                'account_code' => $taxReceivableAccount,
                'debit' => 0,
                'credit' => $taxAmount,
                'description' => "Reversal PPN Masukan: {$purchaseReturn->return_number}",
            ];
        }

        return $this->journalService->createEntry([
            'entry_date' => $purchaseReturn->return_date->toDateString(),
            'description' => "Retur pembelian: {$purchaseReturn->return_number}",
            'reference' => $purchaseReturn->return_number,
            'source_type' => PurchaseReturn::class,
            'source_id' => $purchaseReturn->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function getIdentifier(): string
    {
        return 'full_journal';
    }
}
