---
adr: "0048"
title: "Reporting System"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [reporting, analytics]
related_adrs: [0011, 0006]
related_modules: [accounting]
impact: medium
---

# ADR-0048: Reporting System

## AI Agent Quick Reference

**Use this ADR when:**
- Building financial reports
- Implementing report exports
- Creating dashboard widgets
- Understanding report structure

**Key takeaway:** Reports use dedicated service classes with PDF/Excel export and caching.

---

## Decision

Implement reporting with dedicated Report service classes, multiple export formats, and result caching.

---

## Context

Reporting needs:
1. Financial statements (SAK EMKM)
2. Operational reports
3. Dashboard widgets
4. Export to PDF/Excel

---

## Implementation

### Report Service Pattern

```php
// app/Services/Reports/IncomeStatementReport.php
class IncomeStatementReport
{
    public function __construct(
        protected Carbon $startDate,
        protected Carbon $endDate
    ) {}

    public function generate(): array
    {
        return Cache::remember(
            $this->cacheKey(),
            now()->addMinutes(15),
            fn () => $this->buildReport()
        );
    }

    protected function buildReport(): array
    {
        $income = $this->getIncomeAccounts();
        $expenses = $this->getExpenseAccounts();

        return [
            'title' => 'Laporan Laba Rugi',
            'period' => [
                'start' => $this->startDate->format('d M Y'),
                'end' => $this->endDate->format('d M Y'),
            ],
            'income' => $income,
            'total_income' => $income->sum('balance'),
            'expenses' => $expenses,
            'total_expenses' => $expenses->sum('balance'),
            'net_income' => $income->sum('balance') - $expenses->sum('balance'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function getIncomeAccounts(): Collection
    {
        return Account::where('type', 'income')
            ->with(['journalLines' => function ($q) {
                $q->whereBetween('journal_entries.date', [$this->startDate, $this->endDate]);
            }])
            ->get()
            ->map(fn ($a) => [
                'code' => $a->code,
                'name' => $a->name,
                'balance' => $a->journalLines->sum('credit') - $a->journalLines->sum('debit'),
            ]);
    }

    protected function cacheKey(): string
    {
        return "report:income_statement:{$this->startDate->format('Ymd')}:{$this->endDate->format('Ymd')}";
    }
}
```

### Standard Financial Reports

| Report | Indonesian | Purpose |
|--------|------------|---------|
| Income Statement | Laporan Laba Rugi | Profitability |
| Balance Sheet | Laporan Posisi Keuangan | Financial position |
| Cash Flow | Laporan Arus Kas | Cash movement |
| General Ledger | Buku Besar | Account details |
| Trial Balance | Neraca Saldo | Balance verification |
| Aging Report | Laporan Umur Piutang/Hutang | Receivables/payables |

### Report Controller

```php
class ReportController extends Controller
{
    public function incomeStatement(Request $request)
    {
        $report = new IncomeStatementReport(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date)
        );

        $data = $report->generate();

        return match ($request->format) {
            'pdf' => $this->exportPdf('reports.income-statement', $data),
            'excel' => $this->exportExcel(new IncomeStatementExport($data)),
            default => response()->json($data),
        };
    }
}
```

### PDF Export

```php
protected function exportPdf(string $view, array $data): Response
{
    $pdf = Pdf::loadView($view, $data)
        ->setPaper('a4', 'portrait');

    return $pdf->download("{$data['title']}.pdf");
}
```

### Excel Export

```php
// app/Exports/IncomeStatementExport.php
class IncomeStatementExport implements FromView, WithTitle
{
    public function __construct(
        protected array $data
    ) {}

    public function view(): View
    {
        return view('exports.income-statement', $this->data);
    }

    public function title(): string
    {
        return 'Laporan Laba Rugi';
    }
}
```

### Dashboard Widgets

```php
// Quick stats for dashboard
class DashboardService
{
    public function getWidgets(): array
    {
        return Cache::remember('dashboard:widgets', 300, fn () => [
            'revenue_today' => $this->getTodayRevenue(),
            'invoices_overdue' => $this->getOverdueInvoices(),
            'low_stock_items' => $this->getLowStockCount(),
            'pending_approvals' => $this->getPendingApprovals(),
        ]);
    }
}
```

### Report Parameters

```php
// Common report parameters
$table->date('start_date');
$table->date('end_date');
$table->foreignId('warehouse_id')->nullable();  // Filter by warehouse
$table->foreignId('contact_id')->nullable();    // Filter by customer/supplier
$table->string('group_by')->nullable();         // day, week, month
$table->boolean('include_details');             // Summary vs detailed
```

### Scheduled Reports

```php
// app/Console/Commands/SendScheduledReports.php
class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled';

    public function handle(): void
    {
        // Monthly aging report to finance team
        if (now()->day === 1) {
            $this->sendAgingReport();
        }

        // Weekly sales summary
        if (now()->dayOfWeek === Carbon::MONDAY) {
            $this->sendWeeklySalesSummary();
        }
    }
}
```

---

## References

- [ADR-0006: SAK EMKM Compliance](./0006-sak-emkm-compliance.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

