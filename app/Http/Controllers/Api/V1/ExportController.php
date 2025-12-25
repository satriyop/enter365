<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\AgingReportService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\TaxReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    public function __construct(
        private FinancialReportService $reportService,
        private AccountBalanceService $balanceService,
        private AgingReportService $agingService,
        private TaxReportService $taxService
    ) {}

    public function trialBalance(Request $request): Response|JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $format = $request->input('format', 'csv');

        $data = $this->balanceService->getTrialBalance($date);

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

    public function balanceSheet(Request $request): Response|JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $format = $request->input('format', 'csv');

        $data = $this->reportService->getBalanceSheet($date);

        $rows = $this->flattenBalanceSheet($data);

        return $this->exportReport($rows, 'balance-sheet', $format, [
            'category' => 'Kategori',
            'code' => 'Kode',
            'name' => 'Nama Akun',
            'balance' => 'Saldo',
        ]);
    }

    public function incomeStatement(Request $request): Response|JsonResponse
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $format = $request->input('format', 'csv');

        $data = $this->reportService->getIncomeStatement($startDate, $endDate);

        $rows = $this->flattenIncomeStatement($data);

        return $this->exportReport($rows, 'income-statement', $format, [
            'category' => 'Kategori',
            'code' => 'Kode',
            'name' => 'Nama Akun',
            'balance' => 'Jumlah',
        ]);
    }

    public function generalLedger(Request $request): Response|JsonResponse
    {
        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $format = $request->input('format', 'csv');

        if (! $accountId) {
            abort(422, 'account_id wajib diisi.');
        }

        $account = Account::findOrFail($accountId);
        $ledger = $this->balanceService->getLedger($account, $startDate, $endDate);

        $rows = $ledger->map(fn ($entry) => [
            'date' => $entry->date,
            'entry_number' => $entry->entry_number,
            'description' => $entry->description,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
            'balance' => $entry->balance,
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

    public function receivableAging(Request $request): Response|JsonResponse
    {
        $format = $request->input('format', 'csv');
        $data = $this->agingService->getReceivableAging();

        $rows = [];
        foreach ($data['contacts'] as $contact) {
            $rows[] = [
                'contact' => $contact['contact_name'],
                'bucket_0' => $contact['buckets']['bucket_0'] ?? 0,
                'bucket_1' => $contact['buckets']['bucket_1'] ?? 0,
                'bucket_2' => $contact['buckets']['bucket_2'] ?? 0,
                'bucket_3' => $contact['buckets']['bucket_3'] ?? 0,
                'bucket_4' => $contact['buckets']['bucket_4'] ?? 0,
                'total' => $contact['buckets']['total'] ?? 0,
            ];
        }

        return $this->exportReport($rows, 'receivable-aging', $format, [
            'contact' => 'Pelanggan',
            'bucket_0' => 'Belum Jatuh Tempo',
            'bucket_1' => '1-30 Hari',
            'bucket_2' => '31-60 Hari',
            'bucket_3' => '61-90 Hari',
            'bucket_4' => '> 90 Hari',
            'total' => 'Total',
        ]);
    }

    public function payableAging(Request $request): Response|JsonResponse
    {
        $format = $request->input('format', 'csv');
        $data = $this->agingService->getPayableAging();

        $rows = [];
        foreach ($data['contacts'] as $contact) {
            $rows[] = [
                'contact' => $contact['contact_name'],
                'bucket_0' => $contact['buckets']['bucket_0'] ?? 0,
                'bucket_1' => $contact['buckets']['bucket_1'] ?? 0,
                'bucket_2' => $contact['buckets']['bucket_2'] ?? 0,
                'bucket_3' => $contact['buckets']['bucket_3'] ?? 0,
                'bucket_4' => $contact['buckets']['bucket_4'] ?? 0,
                'total' => $contact['buckets']['total'] ?? 0,
            ];
        }

        return $this->exportReport($rows, 'payable-aging', $format, [
            'contact' => 'Supplier',
            'bucket_0' => 'Belum Jatuh Tempo',
            'bucket_1' => '1-30 Hari',
            'bucket_2' => '31-60 Hari',
            'bucket_3' => '61-90 Hari',
            'bucket_4' => '> 90 Hari',
            'total' => 'Total',
        ]);
    }

    public function invoices(Request $request): Response|JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');
        $format = $request->input('format', 'csv');

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

    public function bills(Request $request): Response|JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');
        $format = $request->input('format', 'csv');

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

    public function taxReport(Request $request): Response|JsonResponse
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        $format = $request->input('format', 'csv');

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

    protected function exportReport(array $data, string $filename, string $format, array $headers): Response|JsonResponse
    {
        if ($format === 'json') {
            return response()->json([
                'data' => $data,
                'headers' => $headers,
            ]);
        }

        // Default to CSV
        $csv = $this->arrayToCsv($data, $headers);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}-" . now()->format('Y-m-d') . ".csv\"",
        ]);
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
                $csvRow[] = $row[$key] ?? '';
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

        // Assets
        foreach ($data['assets']['accounts'] ?? [] as $account) {
            $rows[] = [
                'category' => 'Aset',
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
            ];
        }

        // Liabilities
        foreach ($data['liabilities']['accounts'] ?? [] as $account) {
            $rows[] = [
                'category' => 'Liabilitas',
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
            ];
        }

        // Equity
        foreach ($data['equity']['accounts'] ?? [] as $account) {
            $rows[] = [
                'category' => 'Ekuitas',
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
            ];
        }

        return $rows;
    }

    protected function flattenIncomeStatement(array $data): array
    {
        $rows = [];

        // Revenue
        foreach ($data['revenue']['accounts'] ?? [] as $account) {
            $rows[] = [
                'category' => 'Pendapatan',
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
            ];
        }

        // Expenses
        foreach ($data['expenses']['accounts'] ?? [] as $account) {
            $rows[] = [
                'category' => 'Beban',
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
            ];
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
