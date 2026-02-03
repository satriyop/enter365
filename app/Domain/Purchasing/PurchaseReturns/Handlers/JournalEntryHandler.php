<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns\Handlers;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Accounting\JournalServiceInterface;
use App\Domain\Purchasing\PurchaseReturns\Contracts\ApprovalHandlerInterface;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\PurchaseReturn;

/**
 * Handles journal entry creation when a purchase return is approved.
 *
 * Creates accounting entries:
 * - Debit: Accounts Payable (reduces what we owe)
 * - Credit: Purchase Returns (contra expense)
 * - Credit: PPN Masukan (if applicable)
 *
 * @throws \App\Exceptions\Domain\MissingAccountException When required accounts don't exist
 */
class JournalEntryHandler implements ApprovalHandlerInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private AccountLookupServiceInterface $accountLookup
    ) {}

    public function handle(PurchaseReturn $purchaseReturn): void
    {
        // Determine required account codes upfront
        $requiredCodes = ['2-1100', '5-1004']; // Accounts Payable, Purchase Returns
        if ($purchaseReturn->tax_amount > 0) {
            $requiredCodes[] = '1-1300'; // PPN Masukan
        }

        // Fail-fast: validate ALL required accounts exist before creating any lines
        $accounts = $this->accountLookup->findByCodesOrFail(
            $requiredCodes,
            "purchase return journal entry #{$purchaseReturn->return_number}"
        );

        // Override payable account if bill specifies one
        $payableAccount = $accounts->get('2-1100');
        if ($purchaseReturn->bill && $purchaseReturn->bill->payable_account_id) {
            $payableAccount = $this->accountLookup->findByIdOrFail(
                $purchaseReturn->bill->payable_account_id,
                'bill payable account override'
            );
        }

        // Build journal lines
        $lines = [];

        // Debit: Accounts Payable (reduce what we owe)
        $lines[] = [
            'account_id' => $payableAccount->id,
            'description' => 'Pengurangan utang: '.$purchaseReturn->return_number,
            'debit' => $purchaseReturn->total_amount,
            'credit' => 0,
        ];

        // Credit: Purchase Returns (contra expense)
        $lines[] = [
            'account_id' => $accounts->get('5-1004')->id,
            'description' => 'Retur pembelian: '.$purchaseReturn->return_number,
            'debit' => 0,
            'credit' => $purchaseReturn->subtotal,
        ];

        // Credit: PPN Masukan (if applicable)
        if ($purchaseReturn->tax_amount > 0) {
            $lines[] = [
                'account_id' => $accounts->get('1-1300')->id,
                'description' => 'Reversal PPN Masukan: '.$purchaseReturn->return_number,
                'debit' => 0,
                'credit' => $purchaseReturn->tax_amount,
            ];
        }

        $entry = $this->journalService->createEntry([
            'entry_date' => $purchaseReturn->return_date->toDateString(),
            'description' => 'Retur pembelian: '.$purchaseReturn->return_number,
            'reference' => $purchaseReturn->return_number,
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => $purchaseReturn->id,
            'lines' => $lines,
        ], autoPost: true);

        $purchaseReturn->journal_entry_id = $entry->id;
        $purchaseReturn->save();
    }

    public function priority(): int
    {
        // Journal entries run after inventory (20 > 10)
        return 20;
    }

    public function shouldHandle(PurchaseReturn $purchaseReturn): bool
    {
        // Always create journal entries for approved returns
        return true;
    }
}
