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
     *             invoice_date: string|null,
     *             due_date: string|null,
     *             matching: string|null,
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

        $raw = $this->postedPartnerLines()
            ->when($startDate, fn ($q) => $q->where('je.entry_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('je.entry_date', '<=', $endDate.' 23:59:59'))
            ->when($contactId, fn ($q) => $q->where('jel.partner_id', $contactId))
            ->when($accountId, fn ($q) => $q->where('jel.account_id', $accountId))
            ->when($journalId, fn ($q) => $q->where('je.journal_id', $journalId))
            ->orderBy('c.name')
            ->orderBy('je.entry_date')
            ->orderBy('je.id')
            ->orderBy('jel.id')
            ->get();

        $matching = $this->matchingBySource($raw);
        $lines = $raw->groupBy('partner_id');

        $partners = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $partnerId => $entries) {
            $first = $entries->first();
            $running = (int) ($openings[(int) $partnerId] ?? 0);
            $periodDebit = 0;
            $periodCredit = 0;

            $transformed = $entries->map(function (\stdClass $entry) use (&$running, &$periodDebit, &$periodCredit, $matching): array {
                $debit = (int) $entry->debit;
                $credit = (int) $entry->credit;
                $running += $debit - $credit;
                $periodDebit += $debit;
                $periodCredit += $credit;
                $sourceKey = $entry->source_type && $entry->source_id
                    ? $entry->source_type.':'.(int) $entry->source_id
                    : null;

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
                    'invoice_date' => $this->dateOrNull($entry->invoice_date ?? null),
                    'due_date' => $this->dateOrNull($entry->due_date ?? null),
                    'matching' => $sourceKey !== null ? ($matching[$sourceKey] ?? null) : null,
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
            ->leftJoin('invoices as inv', function ($join): void {
                $join->on('inv.id', '=', 'je.source_id')
                    ->where('je.source_type', '=', 'invoice')
                    ->whereNull('inv.deleted_at');
            })
            ->leftJoin('bills as b', function ($join): void {
                $join->on('b.id', '=', 'je.source_id')
                    ->where('je.source_type', '=', 'bill')
                    ->whereNull('b.deleted_at');
            })
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
                'je.source_type',
                'je.source_id',
                'jel.description as line_description',
                'jel.debit',
                'jel.credit',
                'c.name as partner_name',
                'c.type as partner_type',
                'a.code as account_code',
                'a.name as account_name',
                'j.name as journal_name',
            ])
            ->selectRaw('COALESCE(inv.invoice_date, b.bill_date) as invoice_date')
            ->selectRaw('COALESCE(inv.due_date, b.due_date) as due_date');
    }

    /**
     * @param  Collection<int, \stdClass>  $entries
     * @return array<string, string>
     */
    private function matchingBySource(Collection $entries): array
    {
        $map = [];

        $invoiceIds = $entries->where('source_type', 'invoice')->pluck('source_id')->filter()->unique()->values()->all();
        $billIds = $entries->where('source_type', 'bill')->pluck('source_id')->filter()->unique()->values()->all();
        $paymentIds = $entries->where('source_type', 'payment')->pluck('source_id')->filter()->unique()->values()->all();

        if ($invoiceIds !== []) {
            $rows = DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->where('pa.allocatable_type', 'invoice')
                ->whereIn('pa.allocatable_id', $invoiceIds)
                ->whereNull('p.deleted_at')
                ->where('p.is_voided', false)
                ->orderBy('p.payment_number')
                ->get(['pa.allocatable_id', 'p.payment_number']);

            foreach ($rows->groupBy('allocatable_id') as $id => $group) {
                $map['invoice:'.(int) $id] = $group->pluck('payment_number')->unique()->implode(', ');
            }
        }

        if ($billIds !== []) {
            $rows = DB::table('payment_allocations as pa')
                ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                ->where('pa.allocatable_type', 'bill')
                ->whereIn('pa.allocatable_id', $billIds)
                ->whereNull('p.deleted_at')
                ->where('p.is_voided', false)
                ->orderBy('p.payment_number')
                ->get(['pa.allocatable_id', 'p.payment_number']);

            foreach ($rows->groupBy('allocatable_id') as $id => $group) {
                $map['bill:'.(int) $id] = $group->pluck('payment_number')->unique()->implode(', ');
            }
        }

        if ($paymentIds !== []) {
            $allocatedPaymentIds = DB::table('payment_allocations')
                ->whereIn('payment_id', $paymentIds)
                ->pluck('payment_id')
                ->unique()
                ->all();

            if ($allocatedPaymentIds !== []) {
                $numbers = DB::table('payments')
                    ->whereIn('id', $allocatedPaymentIds)
                    ->whereNull('deleted_at')
                    ->where('is_voided', false)
                    ->pluck('payment_number', 'id');

                foreach ($numbers as $id => $number) {
                    $map['payment:'.(int) $id] = (string) $number;
                }
            }
        }

        return $map;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
