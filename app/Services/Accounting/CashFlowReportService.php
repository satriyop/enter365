<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Payment;
use Illuminate\Support\Collection;

class CashFlowReportService
{
    /**
     * Generate cash flow statement (indirect method).
     *
     * @return array{
     *     period: array{start: string, end: string},
     *     operating: array{items: Collection, subtotal: int},
     *     investing: array{items: Collection, subtotal: int},
     *     financing: array{items: Collection, subtotal: int},
     *     net_cash_flow: int,
     *     beginning_cash: int,
     *     ending_cash: int
     * }
     */
    public function generateCashFlow(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // Get beginning cash balance
        $beginningCash = $this->getCashBalance($startDate->copy()->subDay());

        // Operating activities
        $operating = $this->getOperatingActivities($startDate, $endDate);

        // Investing activities
        $investing = $this->getInvestingActivities($startDate, $endDate);

        // Financing activities
        $financing = $this->getFinancingActivities($startDate, $endDate);

        $netCashFlow = $operating['subtotal'] + $investing['subtotal'] + $financing['subtotal'];
        $endingCash = $beginningCash + $netCashFlow;

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'net_cash_flow' => $netCashFlow,
            'beginning_cash' => $beginningCash,
            'ending_cash' => $endingCash,
        ];
    }

    /**
     * Get cash balance as of a specific date.
     */
    public function getCashBalance(\DateTimeInterface $asOfDate): int
    {
        $cashAccounts = Account::query()
            ->whereIn('code', ['1-1001', '1-1002', '1-1003', '1-1004', '1-1005'])
            ->pluck('id');

        $balance = JournalEntryLine::query()
            ->whereIn('account_id', $cashAccounts)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->value('balance');

        return (int) ($balance ?? 0);
    }

    /**
     * Get operating activities cash flow.
     *
     * @return array{items: Collection, subtotal: int}
     */
    protected function getOperatingActivities(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $items = collect();

        // Cash received from customers
        $customerReceipts = Payment::query()
            ->where('type', Payment::TYPE_RECEIVE)
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');
        $items->push([
            'description' => 'Penerimaan dari pelanggan',
            'amount' => (int) $customerReceipts,
        ]);

        // Cash paid to suppliers
        $supplierPayments = Payment::query()
            ->where('type', Payment::TYPE_SEND)
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');
        $items->push([
            'description' => 'Pembayaran ke pemasok',
            'amount' => -(int) $supplierPayments,
        ]);

        // Other operating cash flows from journal entries
        $operatingJournals = $this->getJournalCashFlows($startDate, $endDate, 'operating');
        foreach ($operatingJournals as $journal) {
            $items->push($journal);
        }

        $subtotal = $items->sum('amount');

        return [
            'items' => $items,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Get investing activities cash flow.
     *
     * @return array{items: Collection, subtotal: int}
     */
    protected function getInvestingActivities(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $items = collect();

        // Get journal entries for fixed assets
        $investingJournals = $this->getJournalCashFlows($startDate, $endDate, 'investing');
        foreach ($investingJournals as $journal) {
            $items->push($journal);
        }

        if ($items->isEmpty()) {
            $items->push([
                'description' => 'Tidak ada aktivitas investasi',
                'amount' => 0,
            ]);
        }

        $subtotal = $items->sum('amount');

        return [
            'items' => $items,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Get financing activities cash flow.
     *
     * @return array{items: Collection, subtotal: int}
     */
    protected function getFinancingActivities(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $items = collect();

        // Get journal entries for equity and long-term liabilities
        $financingJournals = $this->getJournalCashFlows($startDate, $endDate, 'financing');
        foreach ($financingJournals as $journal) {
            $items->push($journal);
        }

        if ($items->isEmpty()) {
            $items->push([
                'description' => 'Tidak ada aktivitas pendanaan',
                'amount' => 0,
            ]);
        }

        $subtotal = $items->sum('amount');

        return [
            'items' => $items,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Get journal entries that affect cash by category.
     *
     * @return Collection<int, array{description: string, amount: int}>
     */
    protected function getJournalCashFlows(\DateTimeInterface $startDate, \DateTimeInterface $endDate, string $category): Collection
    {
        $cashAccounts = Account::query()
            ->whereIn('code', ['1-1001', '1-1002', '1-1003', '1-1004', '1-1005'])
            ->pluck('id');

        // Define account prefixes for each category
        $accountPrefixes = match ($category) {
            'operating' => ['5-'], // Expenses (other than through payments)
            'investing' => ['1-2', '1-3'], // Fixed assets, investments
            'financing' => ['2-2', '3-'], // Long-term liabilities, equity
            default => [],
        };

        if (empty($accountPrefixes)) {
            return collect();
        }

        // Find journal entries that affect both cash and the specified accounts
        $entries = JournalEntry::query()
            ->where('is_posted', true)
            ->where('source_type', JournalEntry::SOURCE_MANUAL) // Only manual entries
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->whereHas('lines', function ($q) use ($cashAccounts) {
                $q->whereIn('account_id', $cashAccounts);
            })
            ->with(['lines.account'])
            ->get();

        $flows = collect();

        foreach ($entries as $entry) {
            $hasCash = false;
            $hasCategory = false;
            $cashAmount = 0;

            foreach ($entry->lines as $line) {
                if ($cashAccounts->contains($line->account_id)) {
                    $hasCash = true;
                    $cashAmount = $line->debit - $line->credit;
                }

                foreach ($accountPrefixes as $prefix) {
                    if (str_starts_with($line->account->code, $prefix)) {
                        $hasCategory = true;
                        break;
                    }
                }
            }

            if ($hasCash && $hasCategory && $cashAmount != 0) {
                $flows->push([
                    'description' => $entry->description,
                    'amount' => $cashAmount,
                ]);
            }
        }

        return $flows;
    }

    /**
     * Get daily cash movement for a period.
     *
     * @return Collection<int, array{date: string, receipts: int, payments: int, net: int, balance: int}>
     */
    public function getDailyCashMovement(\DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection
    {
        $beginningBalance = $this->getCashBalance($startDate->copy()->subDay());
        $runningBalance = $beginningBalance;

        $movements = collect();
        $current = \Carbon\Carbon::parse($startDate)->copy();
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            $receipts = Payment::query()
                ->where('type', Payment::TYPE_RECEIVE)
                ->where('is_voided', false)
                ->whereDate('payment_date', $current)
                ->sum('amount');

            $payments = Payment::query()
                ->where('type', Payment::TYPE_SEND)
                ->where('is_voided', false)
                ->whereDate('payment_date', $current)
                ->sum('amount');

            $net = (int) $receipts - (int) $payments;
            $runningBalance += $net;

            $movements->push([
                'date' => $current->format('Y-m-d'),
                'receipts' => (int) $receipts,
                'payments' => (int) $payments,
                'net' => $net,
                'balance' => $runningBalance,
            ]);

            $current->addDay();
        }

        return $movements;
    }
}
