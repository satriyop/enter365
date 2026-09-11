<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\AuditLog;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountingReviewService
{
    /**
     * Line-level journal item browser (Odoo Review › Journal Items).
     *
     * @param  array<string, mixed>  $filters
     */
    public function journalItems(array $filters): LengthAwarePaginator
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('journals as j', 'j.id', '=', 'je.journal_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'jel.account_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'jel.partner_id')
            ->whereNull('je.deleted_at')
            ->select([
                'jel.id',
                'jel.journal_entry_id',
                'jel.account_id',
                'jel.partner_id',
                'jel.description',
                'jel.debit',
                'jel.credit',
                'jel.reconciled_amount',
                'je.entry_number',
                'je.entry_date',
                'je.is_posted',
                'je.journal_id',
                'j.name as journal_name',
                'j.type as journal_type',
                'a.code as account_code',
                'a.name as account_name',
                'c.name as partner_name',
            ])
            ->selectRaw('(jel.debit + jel.credit - jel.reconciled_amount) as residual')
            ->orderByDesc('je.entry_date')
            ->orderByDesc('jel.id');

        if (! empty($filters['from'])) {
            $query->where('je.entry_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('je.entry_date', '<=', $filters['to']);
        }
        if (! empty($filters['journal_id'])) {
            $query->where('je.journal_id', (int) $filters['journal_id']);
        }
        if (! empty($filters['account_id'])) {
            $query->where('jel.account_id', (int) $filters['account_id']);
        }
        if (! empty($filters['partner_id'])) {
            $query->where('jel.partner_id', (int) $filters['partner_id']);
        }
        if (array_key_exists('is_posted', $filters) && $filters['is_posted'] !== null && $filters['is_posted'] !== '') {
            $query->where('je.is_posted', filter_var($filters['is_posted'], FILTER_VALIDATE_BOOLEAN));
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('je.entry_number', 'like', $like)
                    ->orWhere('jel.description', 'like', $like)
                    ->orWhere('a.code', 'like', $like)
                    ->orWhere('a.name', 'like', $like);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    /**
     * Journal register grouped by journal (Odoo Review › Journal Audit).
     *
     * @param  array<string, mixed>  $filters
     * @return array{report_name: string, from: string|null, to: string|null, journals: list<array<string, mixed>>, totals: array{entry_count: int, debit: int, credit: int}}
     */
    public function journalAudit(array $filters): array
    {
        $query = DB::table('journal_entries as je')
            ->leftJoin('journals as j', 'j.id', '=', 'je.journal_id')
            ->join('journal_entry_lines as jel', 'jel.journal_entry_id', '=', 'je.id')
            ->whereNull('je.deleted_at')
            ->where('je.is_posted', true);

        if (! empty($filters['from'])) {
            $query->where('je.entry_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('je.entry_date', '<=', $filters['to']);
        }
        if (! empty($filters['journal_id'])) {
            $query->where('je.journal_id', (int) $filters['journal_id']);
        }

        $journals = $query
            ->groupBy('je.journal_id', 'j.name', 'j.type')
            ->orderBy('j.name')
            ->select([
                'je.journal_id',
                'j.name as journal_name',
                'j.type as journal_type',
            ])
            ->selectRaw('COUNT(DISTINCT je.id) as entry_count')
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as debit')
            ->selectRaw('COALESCE(SUM(jel.credit), 0) as credit')
            ->get()
            ->map(fn ($row): array => [
                'journal_id' => $row->journal_id ? (int) $row->journal_id : null,
                'journal_name' => $row->journal_name,
                'journal_type' => $row->journal_type,
                'entry_count' => (int) $row->entry_count,
                'debit' => (int) $row->debit,
                'credit' => (int) $row->credit,
            ])
            ->values()
            ->all();

        return [
            'report_name' => 'Journal Audit',
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'journals' => $journals,
            'totals' => [
                'entry_count' => (int) collect($journals)->sum('entry_count'),
                'debit' => (int) collect($journals)->sum('debit'),
                'credit' => (int) collect($journals)->sum('credit'),
            ],
        ];
    }

    /**
     * Open drafts the accountant is still working (Odoo Review › Working Files).
     *
     * @return array{report_name: string, unposted_journal_entries: list<array<string, mixed>>, draft_invoices: list<array<string, mixed>>, draft_bills: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function workingFiles(): array
    {
        $entries = DB::table('journal_entries as je')
            ->leftJoin('journals as j', 'j.id', '=', 'je.journal_id')
            ->whereNull('je.deleted_at')
            ->where('je.is_posted', false)
            ->orderByDesc('je.entry_date')
            ->orderByDesc('je.id')
            ->limit(200)
            ->get([
                'je.id',
                'je.entry_number as number',
                'je.entry_date as date',
                'je.description',
                'j.name as journal_name',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'date' => $row->date,
                'description' => $row->description,
                'journal_name' => $row->journal_name,
            ])
            ->all();

        $invoices = DB::table('invoices as i')
            ->leftJoin('contacts as c', 'c.id', '=', 'i.contact_id')
            ->whereNull('i.deleted_at')
            ->where('i.status', DocumentStatus::Draft->value)
            ->orderByDesc('i.invoice_date')
            ->orderByDesc('i.id')
            ->limit(200)
            ->get([
                'i.id',
                'i.invoice_number as number',
                'i.invoice_date as date',
                'i.total_amount as amount',
                'c.name as partner',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'date' => $row->date,
                'amount' => (int) $row->amount,
                'partner' => $row->partner,
            ])
            ->all();

        $bills = DB::table('bills as b')
            ->leftJoin('contacts as c', 'c.id', '=', 'b.contact_id')
            ->whereNull('b.deleted_at')
            ->where('b.status', DocumentStatus::Draft->value)
            ->orderByDesc('b.bill_date')
            ->orderByDesc('b.id')
            ->limit(200)
            ->get([
                'b.id',
                'b.bill_number as number',
                'b.bill_date as date',
                'b.total_amount as amount',
                'c.name as partner',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
                'date' => $row->date,
                'amount' => (int) $row->amount,
                'partner' => $row->partner,
            ])
            ->all();

        return [
            'report_name' => 'Working Files',
            'unposted_journal_entries' => $entries,
            'draft_invoices' => $invoices,
            'draft_bills' => $bills,
            'totals' => [
                'unposted_journal_entries' => count($entries),
                'draft_invoices' => count($invoices),
                'draft_bills' => count($bills),
            ],
        ];
    }

    /**
     * Accounting audit trail from AuditLog (Odoo Review › Audit Trail).
     *
     * @param  array<string, mixed>  $filters
     */
    public function auditTrail(array $filters): LengthAwarePaginator
    {
        $types = [
            JournalEntry::class,
            Invoice::class,
            Bill::class,
            Payment::class,
            Account::class,
            FiscalPeriod::class,
        ];

        $query = AuditLog::query()
            ->whereIn('auditable_type', $types)
            ->orderByDesc('id');

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to'].' 23:59:59');
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.$search.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('auditable_label', 'like', $like)
                    ->orWhere('user_name', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }
}
