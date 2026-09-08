<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports\Financial;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerLedgerReportService
{
    /**
     * Buku Besar Partner — posted journal lines grouped by contact.
     *
     * @return array{
     *     report_name: string,
     *     start_date: string|null,
     *     end_date: string|null,
     *     partners: list<array{
     *         id: int,
     *         name: string,
     *         type: string|null,
     *         opening_balance: int,
     *         debit: int,
     *         credit: int,
     *         closing_balance: int,
     *         entries: list<array{
     *             id: int,
     *             journal_entry_id: int,
     *             date: string,
     *             entry_number: string,
     *             journal: string|null,
     *             account_code: string,
     *             account_name: string,
     *             description: string,
     *             reference: string|null,
     *             debit: int,
     *             credit: int,
     *             balance: int
     *         }>
     *     }>,
     *     total_debit: int,
     *     total_credit: int
     * }
     */
    public function getPartnerLedger(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $contactId = null,
        ?int $accountId = null,
        ?int $journalId = null,
    ): array {
        $openings = $this->openingBalances($startDate, $contactId, $accountId, $journalId);

        $lines = $this->postedPartnerLines()
            ->when($startDate, fn ($q) => $q->where('je.entry_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('je.entry_date', '<=', $endDate.' 23:59:59'))
            ->when($contactId, fn ($q) => $q->where('jel.partner_id', $contactId))
            ->when($accountId, fn ($q) => $q->where('jel.account_id', $accountId))
            ->when($journalId, fn ($q) => $q->where('je.journal_id', $journalId))
            ->orderBy('c.name')
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jel.id')
            ->get()
            ->groupBy('partner_id');

        $partners = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $partnerId => $entries) {
            $first = $entries->first();
            $running = (int) ($openings[(int) $partnerId] ?? 0);
            $periodDebit = 0;
            $periodCredit = 0;

            $transformed = $entries->map(function (\stdClass $entry) use (&$running, &$periodDebit, &$periodCredit): array {
                $debit = (int) $entry->debit;
                $credit = (int) $entry->credit;
                $running += $debit - $credit;
                $periodDebit += $debit;
                $periodCredit += $credit;

                return [
                    'id' => (int) $entry->id,
                    'journal_entry_id' => (int) $entry->journal_entry_id,
                    'date' => (string) $entry->date,
                    'entry_number' => (string) $entry->entry_number,
                    'journal' => $entry->journal_name ? (string) $entry->journal_name : null,
                    'account_code' => (string) $entry->account_code,
                    'account_name' => (string) $entry->account_name,
                    'description' => (string) ($entry->line_description ?: $entry->entry_description),
                    'reference' => $entry->reference ? (string) $entry->reference : null,
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $running,
                ];
            })->values()->all();

            $opening = (int) ($openings[(int) $partnerId] ?? 0);
            $partners[] = [
                'id' => (int) $partnerId,
                'name' => (string) $first->partner_name,
                'type' => $first->partner_type ? (string) $first->partner_type : null,
                'opening_balance' => $opening,
                'debit' => $periodDebit,
                'credit' => $periodCredit,
                'closing_balance' => $opening + $periodDebit - $periodCredit,
                'entries' => $transformed,
            ];

            $totalDebit += $periodDebit;
            $totalCredit += $periodCredit;
        }

        return [
            'report_name' => 'Buku Besar Partner',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'partners' => $partners,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function openingBalances(
        ?string $startDate,
        ?int $contactId,
        ?int $accountId,
        ?int $journalId,
    ): Collection {
        if ($startDate === null) {
            return collect();
        }

        return $this->postedPartnerLines()
            ->where('je.entry_date', '<', $startDate)
            ->when($contactId, fn ($q) => $q->where('jel.partner_id', $contactId))
            ->when($accountId, fn ($q) => $q->where('jel.account_id', $accountId))
            ->when($journalId, fn ($q) => $q->where('je.journal_id', $journalId))
            ->select('jel.partner_id')
            ->selectRaw('SUM(jel.debit - jel.credit) as opening_balance')
            ->groupBy('jel.partner_id')
            ->pluck('opening_balance', 'partner_id')
            ->map(fn ($value) => (int) $value);
    }

    private function postedPartnerLines(): Builder
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('contacts as c', 'c.id', '=', 'jel.partner_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->leftJoin('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNotNull('jel.partner_id')
            ->select([
                'jel.id',
                'jel.partner_id',
                'jel.journal_entry_id',
                'je.entry_date as date',
                'je.entry_number',
                'je.description as entry_description',
                'je.reference',
                'jel.description as line_description',
                'jel.debit',
                'jel.credit',
                'c.name as partner_name',
                'c.type as partner_type',
                'a.code as account_code',
                'a.name as account_name',
                'j.name as journal_name',
            ]);
    }
}
