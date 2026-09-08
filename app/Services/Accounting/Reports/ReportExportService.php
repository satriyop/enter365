<?php

namespace App\Services\Accounting\Reports;

use App\Exports\ReportRowsExport;
use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Services\Accounting\AccountBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportService
{
    public function __construct(
        private FinancialReportService $reportService,
        private AccountBalanceService $balanceService,
        private AgingReportService $agingService,
        private TaxReportService $taxService,
        private CashFlowReportService $cashFlowService
    ) {}

    public function trialBalance(?string $date = null, string $format = 'csv', ?int $journalId = null, bool $postedOnly = true): Response|JsonResponse|BinaryFileResponse
    {
        $date = $date ?? now()->toDateString();

        $data = $this->balanceService->getTrialBalance($date, $journalId, $postedOnly);

        $rows = $data->map(fn ($item) => [
            'code' => $item['code'],
            'name' => $item['name'],
            'type' => $item['type'],
            'debit' => $item['debit_balance'],
            'credit' => $item['credit_balance'],
        ])->toArray();

        return $this->exportReport($rows, 'trial-balance', $format, [
            'code' => 'Kode',
            'name' => 'Nama Akun',
            'type' => 'Tipe',
            'debit' => 'Debit',
            'credit' => 'Kredit',
        ]);
    }

    public function balanceSheet(?string $date = null, string $format = 'csv', ?int $journalId = null, bool $postedOnly = true): Response|JsonResponse|BinaryFileResponse
    {
        $date = $date ?? now()->toDateString();

        $data = $this->reportService->getBalanceSheet($date, false, $journalId, $postedOnly);

        $rows = $this->flattenBalanceSheet($data);

        return $this->exportReport($rows, 'balance-sheet', $format, [
            'category' => 'Kategori',
            'code' => 'Kode',
            'name' => 'Nama Akun',
            'balance' => 'Saldo',
        ]);
    }

    public function incomeStatement(?string $startDate = null, ?string $endDate = null, string $format = 'csv', ?int $journalId = null, bool $postedOnly = true): Response|JsonResponse|BinaryFileResponse
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $data = $this->reportService->getIncomeStatement($startDate, $endDate, false, $journalId, $postedOnly);

        $rows = $this->flattenIncomeStatement($data);

        return $this->exportReport($rows, 'income-statement', $format, [
            'category' => 'Kategori',
            'code' => 'Kode',
            'name' => 'Nama Akun',
            'balance' => 'Jumlah',
        ]);
    }

    public function generalLedger(
        ?int $accountId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        string $format = 'csv',
        ?int $journalId = null,
        ?int $analyticAccountId = null,
        bool $postedOnly = true
    ): Response|JsonResponse|BinaryFileResponse {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        if (! $accountId) {
            abort(422, 'account_id wajib diisi.');
        }

        $account = Account::findOrFail($accountId);
        $ledger = $this->balanceService->getLedger($account, $startDate, $endDate, $journalId, $analyticAccountId, $postedOnly);

        $rows = $ledger->map(fn (array $entry) => [
            'date' => $entry['date'],
            'entry_number' => $entry['entry_number'],
            'description' => $entry['description'],
            'debit' => $entry['debit'],
            'credit' => $entry['credit'],
            'balance' => $entry['balance'],
        ])->toArray();

        return $this->exportReport($rows, 'general-ledger', $format, [
            'date' => 'Tanggal',
            'entry_number' => 'No. Jurnal',
            'description' => 'Deskripsi',
            'debit' => 'Debit',
            'credit' => 'Kredit',
            'balance' => 'Saldo',
        ]);
    }

    public function partnerLedger(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $contactId = null,
        ?int $accountId = null,
        ?int $journalId = null,
        string $format = 'csv',
    ): Response|JsonResponse|BinaryFileResponse {
        $report = $this->reportService->getPartnerLedger(
            $startDate,
            $endDate,
            $contactId,
            $accountId,
            $journalId,
        );

        $rows = [];
        foreach ($report['partners'] as $partner) {
            foreach ($partner['entries'] as $entry) {
                $rows[] = [
                    'partner' => $partner['name'],
                    'date' => $entry['date'],
                    'entry_number' => $entry['entry_number'],
                    'journal' => $entry['journal'] ?? '',
                    'account' => $entry['account_code'].' '.$entry['account_name'],
                    'description' => $entry['description'],
                    'invoice_date' => $entry['invoice_date'] ?? '',
                    'due_date' => $entry['due_date'] ?? '',
                    'matching' => $entry['matching'] ?? '',
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'balance' => $entry['balance'],
                ];
            }
        }

        return $this->exportReport($rows, 'partner-ledger', $format, [
            'partner' => 'Partner',
            'date' => 'Tanggal',
            'entry_number' => 'No. Jurnal',
            'journal' => 'Jurnal',
            'account' => 'Akun',
            'description' => 'Uraian',
            'invoice_date' => 'Tgl Faktur',
            'due_date' => 'Jatuh Tempo',
            'matching' => 'Matching',
            'debit' => 'Debit',
            'credit' => 'Kredit',
            'balance' => 'Saldo',
        ]);
    }

    public function receivableAging(string $format = 'csv'): Response|JsonResponse|BinaryFileResponse
    {
        $data = $this->agingService->getReceivableAging();

        $rows = [];
        foreach ($data['contacts'] as $contact) {
            $rows[] = [
                'contact' => $contact['name'],
                'current' => $contact['current'] ?? 0,
                'days_1_30' => $contact['days_1_30'] ?? 0,
                'days_31_60' => $contact['days_31_60'] ?? 0,
                'days_61_90' => $contact['days_61_90'] ?? 0,
                'over_90' => $contact['over_90'] ?? 0,
                'total' => $contact['total'] ?? 0,
            ];
        }

        return $this->exportReport($rows, 'receivable-aging', $format, [
            'contact' => 'Pelanggan',
            'current' => 'Belum Jatuh Tempo',
            'days_1_30' => '1-30 Hari',
            'days_31_60' => '31-60 Hari',
            'days_61_90' => '61-90 Hari',
            'over_90' => '> 90 Hari',
            'total' => 'Total',
        ]);
    }

    public function payableAging(string $format = 'csv'): Response|JsonResponse|BinaryFileResponse
    {
        $data = $this->agingService->getPayableAging();

        $rows = [];
        foreach ($data['contacts'] as $contact) {
            $rows[] = [
                'contact' => $contact['name'],
                'current' => $contact['current'] ?? 0,
                'days_1_30' => $contact['days_1_30'] ?? 0,
                'days_31_60' => $contact['days_31_60'] ?? 0,
                'days_61_90' => $contact['days_61_90'] ?? 0,
                'over_90' => $contact['over_90'] ?? 0,
                'total' => $contact['total'] ?? 0,
            ];
        }

        return $this->exportReport($rows, 'payable-aging', $format, [
            'contact' => 'Supplier',
            'current' => 'Belum Jatuh Tempo',
            'days_1_30' => '1-30 Hari',
            'days_31_60' => '31-60 Hari',
            'days_61_90' => '61-90 Hari',
            'over_90' => '> 90 Hari',
            'total' => 'Total',
        ]);
    }

    public function invoices(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
        string $format = 'csv'
    ): Response|JsonResponse|BinaryFileResponse {
        $query = Invoice::with('contact');

        if ($startDate) {
            $query->where('invoice_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('invoice_date', '<=', $endDate);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->orderBy('invoice_date')->get();

        $rows = $invoices->map(fn ($inv) => [
            'invoice_number' => $inv->invoice_number,
            'date' => $inv->invoice_date->toDateString(),
            'due_date' => $inv->due_date->toDateString(),
            'contact' => $inv->contact->name,
            'subtotal' => $inv->subtotal,
            'tax' => $inv->tax_amount,
            'total' => $inv->total_amount,
            'paid' => $inv->paid_amount,
            'outstanding' => $inv->total_amount - $inv->paid_amount,
            'status' => $inv->status,
        ])->toArray();

        return $this->exportReport($rows, 'invoices', $format, [
            'invoice_number' => 'No. Faktur',
            'date' => 'Tanggal',
            'due_date' => 'Jatuh Tempo',
            'contact' => 'Pelanggan',
            'subtotal' => 'Subtotal',
            'tax' => 'PPN',
            'total' => 'Total',
            'paid' => 'Dibayar',
            'outstanding' => 'Sisa',
            'status' => 'Status',
        ]);
    }

    public function bills(
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $status = null,
        string $format = 'csv'
    ): Response|JsonResponse|BinaryFileResponse {
        $query = Bill::with('contact');

        if ($startDate) {
            $query->where('bill_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('bill_date', '<=', $endDate);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $bills = $query->orderBy('bill_date')->get();

        $rows = $bills->map(fn ($bill) => [
            'bill_number' => $bill->bill_number,
            'vendor_ref' => $bill->vendor_invoice_number,
            'date' => $bill->bill_date->toDateString(),
            'due_date' => $bill->due_date->toDateString(),
            'contact' => $bill->contact->name,
            'subtotal' => $bill->subtotal,
            'tax' => $bill->tax_amount,
            'total' => $bill->total_amount,
            'paid' => $bill->paid_amount,
            'outstanding' => $bill->total_amount - $bill->paid_amount,
            'status' => $bill->status,
        ])->toArray();

        return $this->exportReport($rows, 'bills', $format, [
            'bill_number' => 'No. Tagihan',
            'vendor_ref' => 'No. Vendor',
            'date' => 'Tanggal',
            'due_date' => 'Jatuh Tempo',
            'contact' => 'Supplier',
            'subtotal' => 'Subtotal',
            'tax' => 'PPN',
            'total' => 'Total',
            'paid' => 'Dibayar',
            'outstanding' => 'Sisa',
            'status' => 'Status',
        ]);
    }

    public function taxReport(
        int|string|null $month = null,
        int|string|null $year = null,
        string $format = 'csv'
    ): Response|JsonResponse|BinaryFileResponse {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $data = $this->taxService->getMonthlyPpn($month, $year);

        $rows = [];

        // Output invoices (Faktur Keluaran)
        foreach ($data['output_tax']['invoices'] as $inv) {
            $rows[] = [
                'type' => 'Faktur Keluaran',
                'number' => $inv['invoice_number'],
                'date' => $inv['date'],
                'contact' => $inv['contact'],
                'npwp' => $inv['npwp'] ?? '-',
                'dpp' => $inv['subtotal'],
                'ppn' => $inv['tax_amount'],
            ];
        }

        // Input bills (Faktur Masukan)
        foreach ($data['input_tax']['bills'] as $bill) {
            $rows[] = [
                'type' => 'Faktur Masukan',
                'number' => $bill['bill_number'],
                'date' => $bill['date'],
                'contact' => $bill['contact'],
                'npwp' => $bill['npwp'] ?? '-',
                'dpp' => $bill['subtotal'],
                'ppn' => $bill['tax_amount'],
            ];
        }

        return $this->exportReport($rows, "tax-report-{$year}-{$month}", $format, [
            'type' => 'Jenis',
            'number' => 'Nomor',
            'date' => 'Tanggal',
            'contact' => 'Lawan Transaksi',
            'npwp' => 'NPWP',
            'dpp' => 'DPP',
            'ppn' => 'PPN',
        ]);
    }

    public function cashFlow(?string $startDate = null, ?string $endDate = null, string $format = 'csv', ?int $journalId = null): Response|JsonResponse|BinaryFileResponse
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $data = $this->cashFlowService->generateCashFlow($startDate, $endDate, $journalId);

        $rows = [];
        $sections = [
            'operating_activities' => 'Operasi',
            'investing_activities' => 'Investasi',
            'financing_activities' => 'Pendanaan',
        ];

        foreach ($sections as $key => $category) {
            foreach ($data[$key]['items'] ?? [] as $item) {
                $item = (array) $item;
                $rows[] = [
                    'category' => $category,
                    'description' => $item['description'] ?? '',
                    'amount' => $item['amount'] ?? 0,
                ];
            }

            $rows[] = [
                'category' => $category,
                'description' => 'Total',
                'amount' => $data[$key]['total'] ?? 0,
            ];
        }

        $rows[] = [
            'category' => 'Ringkasan',
            'description' => 'Saldo Awal',
            'amount' => $data['opening_balance'] ?? 0,
        ];
        $rows[] = [
            'category' => 'Ringkasan',
            'description' => 'Perubahan Kas Bersih',
            'amount' => $data['net_cash_change'] ?? 0,
        ];
        $rows[] = [
            'category' => 'Ringkasan',
            'description' => 'Saldo Akhir',
            'amount' => $data['closing_balance'] ?? 0,
        ];

        return $this->exportReport($rows, 'cash-flow', $format, [
            'category' => 'Kategori',
            'description' => 'Uraian',
            'amount' => 'Jumlah',
        ]);
    }

    public function changesInEquity(?string $startDate = null, ?string $endDate = null, string $format = 'csv'): Response|JsonResponse|BinaryFileResponse
    {
        $data = $this->reportService->getStatementOfChangesInEquityForApi($startDate, $endDate);

        $rows = [];

        foreach ($data['opening_equity'] ?? [] as $item) {
            $item = (array) $item;
            $rows[] = [
                'section' => 'Saldo Awal',
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'amount' => $item['opening_balance'] ?? 0,
            ];
        }

        $changes = $data['changes'] ?? [];
        foreach ([
            'capital_additions' => 'Penambahan Modal',
            'capital_withdrawals' => 'Penarikan Modal',
            'net_income' => 'Laba/Rugi Bersih',
            'dividends' => 'Dividen',
            'adjustments' => 'Penyesuaian',
            'total_changes' => 'Total Perubahan',
        ] as $key => $label) {
            $rows[] = [
                'section' => 'Perubahan',
                'code' => '',
                'name' => $label,
                'amount' => $changes[$key] ?? 0,
            ];
        }

        foreach ($data['closing_equity'] ?? [] as $item) {
            $item = (array) $item;
            $rows[] = [
                'section' => 'Saldo Akhir',
                'code' => $item['code'] ?? '',
                'name' => $item['name'] ?? '',
                'amount' => $item['closing_balance'] ?? 0,
            ];
        }

        return $this->exportReport($rows, 'changes-in-equity', $format, [
            'section' => 'Bagian',
            'code' => 'Kode',
            'name' => 'Uraian',
            'amount' => 'Jumlah',
        ]);
    }

    public function dailyCashMovement(?string $startDate = null, ?string $endDate = null, string $format = 'csv'): Response|JsonResponse|BinaryFileResponse
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $movements = $this->cashFlowService->getDailyCashMovement($startDate, $endDate);

        $rows = $movements->map(fn (array $movement) => [
            'date' => $movement['date'],
            'receipts' => $movement['receipts'],
            'payments' => $movement['payments'],
            'net' => $movement['net'],
            'running_balance' => $movement['running_balance'] ?? $movement['balance'] ?? 0,
        ])->toArray();

        return $this->exportReport($rows, 'daily-cash-movement', $format, [
            'date' => 'Tanggal',
            'receipts' => 'Penerimaan',
            'payments' => 'Pengeluaran',
            'net' => 'Neto',
            'running_balance' => 'Saldo Berjalan',
        ]);
    }

    protected function exportReport(array $data, string $filename, string $format, array $headers): Response|JsonResponse|BinaryFileResponse
    {
        if ($format === 'json') {
            return response()->json([
                'data' => $data,
                'headers' => $headers,
            ]);
        }

        $stamp = now()->format('Y-m-d');

        if (in_array($format, ['xlsx', 'excel'], true)) {
            return Excel::download(
                new ReportRowsExport($data, $headers),
                "{$filename}-{$stamp}.xlsx",
                \Maatwebsite\Excel\Excel::XLSX
            );
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadHTML($this->arrayToHtmlTable($data, $headers, $filename));

            return $pdf->download("{$filename}-{$stamp}.pdf");
        }

        $csv = $this->arrayToCsv($data, $headers);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}-{$stamp}.csv\"",
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @param  array<string, string>  $headers
     */
    protected function arrayToHtmlTable(array $data, array $headers, string $title): string
    {
        $headingCells = '';
        foreach (array_values($headers) as $label) {
            $headingCells .= '<th>'.e((string) $label).'</th>';
        }

        $body = '';
        foreach ($data as $row) {
            $body .= '<tr>';
            foreach (array_keys($headers) as $key) {
                $value = $row[$key] ?? '';
                if ($value instanceof \BackedEnum) {
                    $value = $value->value;
                }
                $body .= '<td>'.e((string) $value).'</td>';
            }
            $body .= '</tr>';
        }

        return '<html><body><h1>'.e($title).'</h1><table border="1" cellpadding="4" cellspacing="0"><thead><tr>'
            .$headingCells.'</tr></thead><tbody>'.$body.'</tbody></table></body></html>';
    }

    protected function arrayToCsv(array $data, array $headers): string
    {
        $output = fopen('php://temp', 'r+');

        // Write header
        fputcsv($output, array_values($headers));

        // Write data rows
        foreach ($data as $row) {
            $csvRow = [];
            foreach (array_keys($headers) as $key) {
                $value = $row[$key] ?? '';
                // Convert BackedEnum to its string value
                $csvRow[] = $value instanceof \BackedEnum ? $value->value : $value;
            }
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    protected function flattenBalanceSheet(array $data): array
    {
        $rows = [];

        foreach (['assets' => 'Aset', 'liabilities' => 'Liabilitas', 'equity' => 'Ekuitas'] as $key => $category) {
            foreach ($data[$key] ?? [] as $section) {
                foreach ($section['accounts'] ?? [] as $account) {
                    $account = (array) $account;
                    $rows[] = [
                        'category' => $category,
                        'code' => $account['code'],
                        'name' => $account['name'],
                        'balance' => $account['balance'],
                    ];
                }
            }
        }

        return $rows;
    }

    protected function flattenIncomeStatement(array $data): array
    {
        $rows = [];

        // Revenue sections
        foreach ($data['revenue'] ?? [] as $section) {
            foreach ($section['accounts'] ?? [] as $account) {
                $account = (array) $account;
                $rows[] = [
                    'category' => 'Pendapatan',
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'balance' => $account['balance'],
                ];
            }
        }

        // Expense sections
        foreach ($data['expenses'] ?? [] as $section) {
            foreach ($section['accounts'] ?? [] as $account) {
                $account = (array) $account;
                $rows[] = [
                    'category' => 'Beban',
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'balance' => $account['balance'],
                ];
            }
        }

        // Net income summary
        $rows[] = [
            'category' => 'LABA/RUGI BERSIH',
            'code' => '',
            'name' => '',
            'balance' => $data['net_income'] ?? 0,
        ];

        return $rows;
    }
}
