<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns\Handlers;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Domain\Sales\SalesReturns\Contracts\ApprovalHandlerInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Sales\SalesReturn;

/**
 * Handles journal entry creation when a sales return is approved.
 *
 * Creates accounting entries:
 * - Debit: Sales Returns (reduces revenue)
 * - Debit: PPN Keluaran (if applicable)
 * - Credit: Accounts Receivable (reduces receivable)
 *
 * @throws \App\Exceptions\Domain\MissingAccountException When required accounts don't exist
 */
class JournalEntryHandler implements ApprovalHandlerInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private AccountLookupServiceInterface $accountLookup
    ) {}

    public function handle(SalesReturn $salesReturn): void
    {
        // Determine required account codes upfront
        $requiredCodes = ['4-2001', '1-1100']; // Sales Returns, Accounts Receivable
        if ($salesReturn->tax_amount > 0) {
            $requiredCodes[] = '2-1200'; // PPN Keluaran
        }

        // Fail-fast: validate ALL required accounts exist before creating any lines
        $accounts = $this->accountLookup->findByCodesOrFail(
            $requiredCodes,
            "sales return journal entry #{$salesReturn->return_number}"
        );

        // Override receivable account if invoice specifies one
        $receivableAccount = $accounts->get('1-1100');
        if ($salesReturn->invoice && $salesReturn->invoice->receivable_account_id) {
            $receivableAccount = $this->accountLookup->findByIdOrFail(
                $salesReturn->invoice->receivable_account_id,
                'invoice receivable account override'
            );
        }

        // Build journal lines
        $lines = [];

        // Debit: Sales Returns
        $lines[] = [
            'account_id' => $accounts->get('4-2001')->id,
            'description' => 'Retur penjualan: '.$salesReturn->return_number,
            'debit' => $salesReturn->subtotal,
            'credit' => 0,
        ];

        // Debit: PPN Keluaran (if applicable)
        if ($salesReturn->tax_amount > 0) {
            $lines[] = [
                'account_id' => $accounts->get('2-1200')->id,
                'description' => 'PPN Retur: '.$salesReturn->return_number,
                'debit' => $salesReturn->tax_amount,
                'credit' => 0,
            ];
        }

        // Credit: Accounts Receivable
        $lines[] = [
            'account_id' => $receivableAccount->id,
            'description' => 'Pengurangan piutang: '.$salesReturn->return_number,
            'debit' => 0,
            'credit' => $salesReturn->total_amount,
        ];

        $entry = $this->journalService->createEntry([
            'entry_date' => $salesReturn->return_date instanceof \Carbon\Carbon ? $salesReturn->return_date->toDateString() : (string) $salesReturn->return_date,
            'description' => 'Retur penjualan: '.$salesReturn->return_number,
            'reference' => $salesReturn->return_number,
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => $salesReturn->id,
            'lines' => $lines,
        ], autoPost: true);

        $salesReturn->journal_entry_id = $entry->id;
        $salesReturn->save();
    }

    public function priority(): int
    {
        // Journal entries run after inventory (20 > 10)
        return 20;
    }

    public function shouldHandle(SalesReturn $salesReturn): bool
    {
        // Always create journal entries for approved returns
        return true;
    }
}
