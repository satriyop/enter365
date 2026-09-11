<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingAnalysisService
{
    /**
     * Monthly VAT filing workspace (Odoo Accounting › Tax Returns).
     *
     * @return array{report_name: string, year: int, rows: list<array<string, mixed>>, totals: array{output_tax: int, input_tax: int, net_tax: int}}
     */
    public function taxReturns(int $year): array
    {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31 23:59:59', $year);

        $outputByMonth = $this->taxByMonth('invoices', 'invoice_date', $this->postedInvoiceStatuses(), $start, $end);
        $inputByMonth = $this->taxByMonth('bills', 'bill_date', $this->postedBillStatuses(), $start, $end);

        $rows = [];
        $totalOutput = 0;
        $totalInput = 0;

        for ($month = 1; $month <= 12; $month++) {
            $key = sprintf('%04d-%02d', $year, $month);
            $output = $outputByMonth[$key] ?? ['count' => 0, 'base' => 0, 'tax' => 0];
            $input = $inputByMonth[$key] ?? ['count' => 0, 'base' => 0, 'tax' => 0];
            $net = $output['tax'] - $input['tax'];
            $hasActivity = $output['count'] > 0 || $input['count'] > 0;

            $rows[] = [
                'period' => $key,
                'month' => $month,
                'name' => 'Tax Return '.$key,
                'output_count' => $output['count'],
                'output_base' => $output['base'],
                'output_tax' => $output['tax'],
                'input_count' => $input['count'],
                'input_base' => $input['base'],
                'input_tax' => $input['tax'],
                'net_tax' => $net,
                'status' => $hasActivity ? ($net === 0 ? 'balanced' : 'to_file') : 'nil',
            ];

            $totalOutput += $output['tax'];
            $totalInput += $input['tax'];
        }

        return [
            'report_name' => 'Tax Returns',
            'year' => $year,
            'rows' => $rows,
            'totals' => [
                'output_tax' => $totalOutput,
                'input_tax' => $totalInput,
                'net_tax' => $totalOutput - $totalInput,
            ],
        ];
    }

    /**
     * Open FX AR/AP vs closing rate (Odoo Review › Unrealized Currencies).
     *
     * @param  array<string, float|int|string>  $closingRates
     * @return array{report_name: string, as_of_date: string, closing_rates: array<string, float>, rows: list<array<string, mixed>>, totals: array{count: int, total_gain: int, total_loss: int, net: int}}
     */
    public function unrealizedCurrencies(string $asOfDate, array $closingRates = []): array
    {
        $baseCurrency = Currency::base();
        $base = strtoupper((string) ($baseCurrency instanceof Currency ? $baseCurrency->code : 'IDR'));
        $resolved = [];
        foreach ($closingRates as $code => $rate) {
            $code = strtoupper((string) $code);
            if ($code === '' || $code === $base) {
                continue;
            }
            $resolved[$code] = (float) $rate;
        }

        $items = [];
        $totalGain = 0;
        $totalLoss = 0;

        foreach ($this->openForeignInvoices($asOfDate, $base) as $inv) {
            $row = $this->unrealizedRow(
                'invoice',
                (string) $inv->invoice_number,
                (string) $inv->currency,
                (int) $inv->total_amount - (int) $inv->paid_amount,
                (float) $inv->exchange_rate,
                $asOfDate,
                $resolved,
            );
            $items[] = $row;
            if ($row['unrealized_fx'] > 0) {
                $totalGain += $row['unrealized_fx'];
            } else {
                $totalLoss += abs($row['unrealized_fx']);
            }
        }

        foreach ($this->openForeignBills($asOfDate, $base) as $bill) {
            $outstanding = (int) $bill->total_amount - (int) $bill->paid_amount;
            $bookedRate = (float) $bill->exchange_rate;
            $closingRate = $this->closingRate((string) $bill->currency, $bookedRate, $asOfDate, $resolved);
            $bookedBase = (int) round($outstanding * $bookedRate);
            $revaluedBase = (int) round($outstanding * $closingRate);
            $raw = $revaluedBase - $bookedBase;
            $unrealized = -$raw;

            $row = [
                'type' => 'bill',
                'reference' => (string) $bill->bill_number,
                'currency' => strtoupper((string) $bill->currency),
                'outstanding' => $outstanding,
                'booked_rate' => $bookedRate,
                'closing_rate' => $closingRate,
                'booked_base' => $bookedBase,
                'revalued_base' => $revaluedBase,
                'unrealized_fx' => $unrealized,
            ];
            $items[] = $row;
            if ($unrealized > 0) {
                $totalGain += $unrealized;
            } else {
                $totalLoss += abs($unrealized);
            }
        }

        return [
            'report_name' => 'Unrealized Currencies',
            'as_of_date' => $asOfDate,
            'closing_rates' => $resolved,
            'rows' => $items,
            'totals' => [
                'count' => count($items),
                'total_gain' => $totalGain,
                'total_loss' => $totalLoss,
                'net' => $totalGain - $totalLoss,
            ],
        ];
    }

    /**
     * Posted invoice analysis (Odoo Reporting › Invoice Analysis).
     *
     * @return array{report_name: string, group_by: string, from: string, to: string, rows: list<array<string, mixed>>, totals: array{count: int, subtotal: int, tax_amount: int, total_amount: int, paid_amount: int, outstanding: int}}
     */
    public function invoiceAnalysis(string $from, string $to, string $groupBy = 'month'): array
    {
        $groupBy = in_array($groupBy, ['month', 'partner', 'status'], true) ? $groupBy : 'month';

        $invoices = DB::table('invoices as i')
            ->leftJoin('contacts as c', 'c.id', '=', 'i.contact_id')
            ->whereNull('i.deleted_at')
            ->whereIn('i.status', $this->postedInvoiceStatuses())
            ->where('i.invoice_date', '>=', $from)
            ->where('i.invoice_date', '<=', $to.' 23:59:59')
            ->select([
                'i.id',
                'i.invoice_date',
                'i.status',
                'i.contact_id',
                'c.name as partner_name',
                'i.subtotal',
                'i.tax_amount',
                'i.total_amount',
                'i.paid_amount',
            ])
            ->get();

        $groups = [];
        foreach ($invoices as $invoice) {
            $key = match ($groupBy) {
                'partner' => (string) ($invoice->contact_id ?? 0),
                'status' => (string) $invoice->status,
                default => substr((string) $invoice->invoice_date, 0, 7),
            };
            if (! isset($groups[$key])) {
                $partnerName = is_string($invoice->partner_name) && $invoice->partner_name !== ''
                    ? $invoice->partner_name
                    : 'Unknown';
                $label = $key;
                if ($groupBy === 'partner') {
                    $label = $partnerName;
                } elseif ($groupBy === 'status') {
                    $label = (string) $invoice->status;
                }
                $groups[$key] = [
                    'group' => $label,
                    'count' => 0,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'outstanding' => 0,
                ];
            }

            $total = (int) $invoice->total_amount;
            $paid = (int) $invoice->paid_amount;
            $groups[$key]['count']++;
            $groups[$key]['subtotal'] += (int) $invoice->subtotal;
            $groups[$key]['tax_amount'] += (int) $invoice->tax_amount;
            $groups[$key]['total_amount'] += $total;
            $groups[$key]['paid_amount'] += $paid;
            $groups[$key]['outstanding'] += max(0, $total - $paid);
        }

        ksort($groups);
        $rows = array_values($groups);

        return [
            'report_name' => 'Invoice Analysis',
            'group_by' => $groupBy,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'count' => (int) array_sum(array_column($rows, 'count')),
                'subtotal' => (int) array_sum(array_column($rows, 'subtotal')),
                'tax_amount' => (int) array_sum(array_column($rows, 'tax_amount')),
                'total_amount' => (int) array_sum(array_column($rows, 'total_amount')),
                'paid_amount' => (int) array_sum(array_column($rows, 'paid_amount')),
                'outstanding' => (int) array_sum(array_column($rows, 'outstanding')),
            ],
        ];
    }

    /**
     * Analytic profitability from posted journal distributions (Odoo Reporting › Analytic Report).
     *
     * @return array{report_name: string, from: string, to: string, rows: list<array<string, mixed>>, totals: array{debit: int, credit: int, amount: int}}
     */
    public function analyticReport(string $from, string $to): array
    {
        $lines = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->leftJoin('accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->whereNotNull('jel.analytic_distribution')
            ->where('je.entry_date', '>=', $from)
            ->where('je.entry_date', '<=', $to.' 23:59:59')
            ->select([
                'jel.analytic_distribution',
                'jel.debit',
                'jel.credit',
                'a.type as account_type',
            ])
            ->get();

        $byAccount = [];
        foreach ($lines as $line) {
            $distribution = $line->analytic_distribution;
            if (is_string($distribution)) {
                $distribution = json_decode($distribution, true);
            }
            if (! is_array($distribution) || $distribution === []) {
                continue;
            }

            $debit = (int) $line->debit;
            $credit = (int) $line->credit;
            $type = (string) $line->account_type;

            foreach ($distribution as $analyticId => $percentage) {
                $analyticId = (int) $analyticId;
                $share = ((float) $percentage) / 100;
                if (! isset($byAccount[$analyticId])) {
                    $byAccount[$analyticId] = [
                        'analytic_account_id' => $analyticId,
                        'debit' => 0,
                        'credit' => 0,
                        'income' => 0,
                        'expense' => 0,
                        'amount' => 0,
                    ];
                }

                $shareDebit = (int) round($debit * $share);
                $shareCredit = (int) round($credit * $share);
                $byAccount[$analyticId]['debit'] += $shareDebit;
                $byAccount[$analyticId]['credit'] += $shareCredit;
                $byAccount[$analyticId]['amount'] += $shareDebit - $shareCredit;

                if ($type === Account::TYPE_REVENUE) {
                    $byAccount[$analyticId]['income'] += $shareCredit - $shareDebit;
                }
                if ($type === Account::TYPE_EXPENSE) {
                    $byAccount[$analyticId]['expense'] += $shareDebit - $shareCredit;
                }
            }
        }

        $names = DB::table('analytic_accounts')
            ->whereIn('id', array_keys($byAccount))
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $rows = [];
        foreach ($byAccount as $id => $row) {
            $analytic = $names->get($id);
            $rows[] = [
                'analytic_account_id' => $id,
                'code' => $analytic->code ?? null,
                'name' => $analytic->name ?? null,
                'income' => $row['income'],
                'expense' => $row['expense'],
                'amount' => $row['amount'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'balance' => $row['income'] - $row['expense'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $a['code'], (string) $b['code']));

        return [
            'report_name' => 'Analytic Report',
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => [
                'debit' => (int) array_sum(array_column($rows, 'debit')),
                'credit' => (int) array_sum(array_column($rows, 'credit')),
                'amount' => (int) array_sum(array_column($rows, 'amount')),
                'income' => (int) array_sum(array_column($rows, 'income')),
                'expense' => (int) array_sum(array_column($rows, 'expense')),
                'balance' => (int) array_sum(array_column($rows, 'balance')),
            ],
        ];
    }

    /**
     * Period KPI pack (Odoo Reporting › Executive Summary).
     *
     * @return array{report_name: string, from: string, to: string, kpis: array<string, int>, rows: list<array{label: string, amount: int}>}
     */
    public function executiveSummary(string $from, string $to): array
    {
        $sales = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', $this->postedInvoiceStatuses())
            ->where('invoice_date', '>=', $from)
            ->where('invoice_date', '<=', $to.' 23:59:59')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(tax_amount), 0) as tax')
            ->first();

        $purchases = DB::table('bills')
            ->whereNull('deleted_at')
            ->whereIn('status', $this->postedBillStatuses())
            ->where('bill_date', '>=', $from)
            ->where('bill_date', '<=', $to.' 23:59:59')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(tax_amount), 0) as tax')
            ->first();

        $ar = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', [DocumentStatus::Sent->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
            ->value('outstanding');

        $ap = DB::table('bills')
            ->whereNull('deleted_at')
            ->whereIn('status', [DocumentStatus::Received->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
            ->value('outstanding');

        $cash = (int) DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->where('je.entry_date', '<=', $to.' 23:59:59')
            ->where('a.type', Account::TYPE_ASSET)
            ->where('a.code', 'like', '1-10%')
            ->selectRaw('COALESCE(SUM(jel.debit - jel.credit), 0) as balance')
            ->value('balance');

        $salesTotal = (int) ($sales->total ?? 0);
        $purchaseTotal = (int) ($purchases->total ?? 0);

        $kpis = [
            'sales_total' => $salesTotal,
            'sales_count' => (int) ($sales->count ?? 0),
            'sales_tax' => (int) ($sales->tax ?? 0),
            'purchase_total' => $purchaseTotal,
            'purchase_count' => (int) ($purchases->count ?? 0),
            'purchase_tax' => (int) ($purchases->tax ?? 0),
            'net_operating' => $salesTotal - $purchaseTotal,
            'receivable_outstanding' => (int) $ar,
            'payable_outstanding' => (int) $ap,
            'cash_balance' => $cash,
        ];

        $rows = [
            ['label' => 'Sales', 'amount' => $kpis['sales_total']],
            ['label' => 'Purchases', 'amount' => $kpis['purchase_total']],
            ['label' => 'Net operating', 'amount' => $kpis['net_operating']],
            ['label' => 'Receivables', 'amount' => $kpis['receivable_outstanding']],
            ['label' => 'Payables', 'amount' => $kpis['payable_outstanding']],
            ['label' => 'Cash', 'amount' => $kpis['cash_balance']],
        ];

        return [
            'report_name' => 'Executive Summary',
            'from' => $from,
            'to' => $to,
            'kpis' => $kpis,
            'rows' => $rows,
        ];
    }

    /**
     * Company budgets vs posted actuals (Odoo Reporting › Budget Report).
     *
     * @return array{report_name: string, as_of_date: string, rows: list<array<string, mixed>>, totals: array{budgeted_revenue: int, actual_revenue: int, budgeted_expense: int, actual_expense: int}}
     */
    public function budgetReport(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ?: now()->toDateString();

        $budgets = DB::table('budgets as b')
            ->leftJoin('fiscal_periods as fp', 'fp.id', '=', 'b.fiscal_period_id')
            ->whereNull('b.deleted_at')
            ->when($asOfDate, function ($query) use ($asOf): void {
                $query->where('fp.start_date', '<=', $asOf)
                    ->where('fp.end_date', '>=', $asOf);
            })
            ->select([
                'b.id',
                'b.name',
                'b.type',
                'b.status',
                'b.total_revenue',
                'b.total_expense',
                'b.net_budget',
                'b.fiscal_period_id',
                'fp.name as period_name',
                'fp.start_date',
                'fp.end_date',
            ])
            ->orderBy('b.id')
            ->get();

        $budgetIds = $budgets->pluck('id')->all();
        $lines = $budgetIds === []
            ? collect()
            : DB::table('budget_lines as bl')
                ->join('accounts as a', 'a.id', '=', 'bl.account_id')
                ->whereIn('bl.budget_id', $budgetIds)
                ->select(['bl.budget_id', 'bl.account_id', 'bl.annual_amount', 'a.type as account_type'])
                ->get()
                ->groupBy('budget_id');

        $actuals = $this->budgetActualsByAccount($budgets);

        $rows = [];
        foreach ($budgets as $budget) {
            $budgetLines = $lines->get($budget->id, collect());
            $budgetedRevenue = 0;
            $budgetedExpense = 0;
            $actualRevenue = 0;
            $actualExpense = 0;

            foreach ($budgetLines as $line) {
                $actual = (int) ($actuals[(int) $budget->fiscal_period_id][(int) $line->account_id] ?? 0);
                if ($line->account_type === Account::TYPE_REVENUE) {
                    $budgetedRevenue += (int) $line->annual_amount;
                    $actualRevenue += $actual;
                } elseif ($line->account_type === Account::TYPE_EXPENSE) {
                    $budgetedExpense += (int) $line->annual_amount;
                    $actualExpense += $actual;
                }
            }

            $rows[] = [
                'id' => (int) $budget->id,
                'name' => (string) $budget->name,
                'type' => (string) $budget->type,
                'status' => (string) $budget->status,
                'period_name' => $budget->period_name,
                'budgeted_revenue' => $budgetedRevenue ?: (int) $budget->total_revenue,
                'actual_revenue' => $actualRevenue,
                'revenue_variance' => ($budgetedRevenue ?: (int) $budget->total_revenue) - $actualRevenue,
                'budgeted_expense' => $budgetedExpense ?: (int) $budget->total_expense,
                'actual_expense' => $actualExpense,
                'expense_variance' => ($budgetedExpense ?: (int) $budget->total_expense) - $actualExpense,
                'net_budget' => (int) $budget->net_budget,
                'net_actual' => $actualRevenue - $actualExpense,
            ];
        }

        return [
            'report_name' => 'Budget Report',
            'as_of_date' => $asOf,
            'rows' => $rows,
            'totals' => [
                'budgeted_revenue' => (int) array_sum(array_column($rows, 'budgeted_revenue')),
                'actual_revenue' => (int) array_sum(array_column($rows, 'actual_revenue')),
                'budgeted_expense' => (int) array_sum(array_column($rows, 'budgeted_expense')),
                'actual_expense' => (int) array_sum(array_column($rows, 'actual_expense')),
            ],
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, array{count: int, base: int, tax: int}>
     */
    private function taxByMonth(string $table, string $dateColumn, array $statuses, string $start, string $end): array
    {
        $rows = DB::table($table)
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses)
            ->where($dateColumn, '>=', $start)
            ->where($dateColumn, '<=', $end)
            ->where('tax_amount', '>', 0)
            ->select([$dateColumn, 'subtotal', 'tax_amount'])
            ->get();

        $byMonth = [];
        foreach ($rows as $row) {
            $key = substr((string) $row->{$dateColumn}, 0, 7);
            if (! isset($byMonth[$key])) {
                $byMonth[$key] = ['count' => 0, 'base' => 0, 'tax' => 0];
            }
            $byMonth[$key]['count']++;
            $byMonth[$key]['base'] += (int) $row->subtotal;
            $byMonth[$key]['tax'] += (int) $row->tax_amount;
        }

        return $byMonth;
    }

    /**
     * @return list<string>
     */
    private function postedInvoiceStatuses(): array
    {
        return [
            DocumentStatus::Sent->value,
            DocumentStatus::Partial->value,
            DocumentStatus::Paid->value,
            DocumentStatus::Overdue->value,
        ];
    }

    /**
     * @return list<string>
     */
    private function postedBillStatuses(): array
    {
        return [
            DocumentStatus::Received->value,
            DocumentStatus::Partial->value,
            DocumentStatus::Paid->value,
            DocumentStatus::Overdue->value,
        ];
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function openForeignInvoices(string $asOfDate, string $base): Collection
    {
        return DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', [DocumentStatus::Sent->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->where('invoice_date', '<=', $asOfDate)
            ->where('currency', '!=', $base)
            ->whereRaw('total_amount - paid_amount > 0')
            ->select(['id', 'invoice_number', 'currency', 'exchange_rate', 'total_amount', 'paid_amount'])
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function openForeignBills(string $asOfDate, string $base): Collection
    {
        return DB::table('bills')
            ->whereNull('deleted_at')
            ->whereIn('status', [DocumentStatus::Received->value, DocumentStatus::Partial->value, DocumentStatus::Overdue->value])
            ->where('bill_date', '<=', $asOfDate)
            ->where('currency', '!=', $base)
            ->whereRaw('total_amount - paid_amount > 0')
            ->select(['id', 'bill_number', 'currency', 'exchange_rate', 'total_amount', 'paid_amount'])
            ->get();
    }

    /**
     * @param  array<string, float>  $resolved
     * @return array{type: string, reference: string, currency: string, outstanding: int, booked_rate: float, closing_rate: float, booked_base: int, revalued_base: int, unrealized_fx: int}
     */
    private function unrealizedRow(
        string $type,
        string $reference,
        string $currency,
        int $outstanding,
        float $bookedRate,
        string $asOfDate,
        array &$resolved,
    ): array {
        $currency = strtoupper($currency);
        $closingRate = $this->closingRate($currency, $bookedRate, $asOfDate, $resolved);
        $bookedBase = (int) round($outstanding * $bookedRate);
        $revaluedBase = (int) round($outstanding * $closingRate);

        return [
            'type' => $type,
            'reference' => $reference,
            'currency' => $currency,
            'outstanding' => $outstanding,
            'booked_rate' => $bookedRate,
            'closing_rate' => $closingRate,
            'booked_base' => $bookedBase,
            'revalued_base' => $revaluedBase,
            'unrealized_fx' => $revaluedBase - $bookedBase,
        ];
    }

    /**
     * @param  array<string, float>  $resolved
     */
    private function closingRate(string $currency, float $bookedRate, string $asOfDate, array &$resolved): float
    {
        if (! isset($resolved[$currency])) {
            $stored = ExchangeRate::getRate($currency, 'IDR', new \DateTimeImmutable($asOfDate));
            $resolved[$currency] = $stored !== null ? (float) $stored : $bookedRate;
        }

        return $resolved[$currency];
    }

    /**
     * @param  Collection<int, \stdClass>  $budgets
     * @return array<int, array<int, int>>
     */
    private function budgetActualsByAccount(Collection $budgets): array
    {
        $periodIds = $budgets->pluck('fiscal_period_id')->filter()->unique()->values()->all();
        if ($periodIds === []) {
            return [];
        }

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'jel.account_id')
            ->where('je.is_posted', true)
            ->whereNull('je.deleted_at')
            ->whereIn('je.fiscal_period_id', $periodIds)
            ->groupBy('je.fiscal_period_id', 'jel.account_id', 'a.type')
            ->select([
                'je.fiscal_period_id',
                'jel.account_id',
                'a.type',
                DB::raw('SUM(jel.debit) as debit'),
                DB::raw('SUM(jel.credit) as credit'),
            ])
            ->get();

        $actuals = [];
        foreach ($rows as $row) {
            $periodId = (int) $row->fiscal_period_id;
            $accountId = (int) $row->account_id;
            $debit = (int) $row->debit;
            $credit = (int) $row->credit;
            $actuals[$periodId][$accountId] = $row->type === Account::TYPE_EXPENSE
                ? $debit - $credit
                : $credit - $debit;
        }

        return $actuals;
    }
}
