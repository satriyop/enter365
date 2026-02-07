<?php

namespace App\Services\Accounting\Reports;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxReportService
{
    /**
     * Get PPN (VAT) summary report.
     *
     * @return array{
     *     period: array{start: string, end: string},
     *     output_tax: array{count: int, base: int, tax: int},
     *     input_tax: array{count: int, base: int, tax: int},
     *     net_tax: int,
     *     details: array{invoices: Collection, bills: Collection}
     * }
     */
    public function getPpnSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        // PPN Keluaran (Output Tax) - From posted invoices
        $invoices = Invoice::query()
            ->whereIn('status', [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('invoice_date', [$startDate, $endDate.' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->get();

        $outputTax = [
            'count' => $invoices->count(),
            'base' => $invoices->sum('subtotal'),
            'tax' => $invoices->sum('tax_amount'),
        ];

        // PPN Masukan (Input Tax) - From posted bills
        $bills = Bill::query()
            ->whereIn('status', [
                DocumentStatus::Received,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('bill_date', [$startDate, $endDate.' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->get();

        $inputTax = [
            'count' => $bills->count(),
            'base' => $bills->sum('subtotal'),
            'tax' => $bills->sum('tax_amount'),
        ];

        // Net tax payable/receivable
        $netTax = $outputTax['tax'] - $inputTax['tax'];

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'output_tax' => $outputTax,
            'input_tax' => $inputTax,
            'net_tax' => $netTax,
            'net_tax_status' => $netTax >= 0 ? 'payable' : 'refundable',
            'details' => [
                'invoices' => $invoices->map(fn ($inv) => [
                    'date' => $inv->invoice_date->format('Y-m-d'),
                    'number' => $inv->invoice_number,
                    'contact' => $inv->contact->name,
                    'npwp' => $inv->contact->npwp,
                    'base' => $inv->subtotal,
                    'tax_rate' => $inv->tax_rate,
                    'tax' => $inv->tax_amount,
                ]),
                'bills' => $bills->map(fn ($bill) => [
                    'date' => $bill->bill_date->format('Y-m-d'),
                    'number' => $bill->bill_number,
                    'vendor_invoice' => $bill->vendor_invoice_number,
                    'contact' => $bill->contact->name,
                    'npwp' => $bill->contact->npwp,
                    'base' => $bill->subtotal,
                    'tax_rate' => $bill->tax_rate,
                    'tax' => $bill->tax_amount,
                ]),
            ],
        ];
    }

    /**
     * Get monthly PPN summary for a year.
     *
     * @return Collection<int, array{month: string, output: int, input: int, net: int}>
     */
    public function getMonthlyPpnSummary(int $year): Collection
    {
        $months = collect();

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $summary = $this->getPpnSummary($startDate->toDateString(), $endDate->toDateString());

            $months->push([
                'month' => $startDate->format('Y-m'),
                'month_name' => $startDate->translatedFormat('F Y'),
                'output' => $summary['output_tax']['tax'],
                'input' => $summary['input_tax']['tax'],
                'net' => $summary['net_tax'],
            ]);
        }

        return $months;
    }

    /**
     * Get tax invoice list for SPT reporting (Faktur Pajak).
     *
     * @return Collection<int, array>
     */
    public function getTaxInvoiceList(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        return Invoice::query()
            ->whereIn('status', [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('invoice_date', [$startDate, $endDate.' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->orderBy('invoice_date')
            ->get()
            ->map(fn ($inv) => [
                'tanggal' => $inv->invoice_date->format('d/m/Y'),
                'nomor_faktur' => $inv->nsfp_number ?? $inv->invoice_number,
                'nama_pembeli' => $inv->contact->name,
                'npwp_pembeli' => $inv->contact->npwp ?? '-',
                'alamat' => $inv->contact->address ?? '-',
                'dpp' => $inv->subtotal,
                'ppn' => $inv->tax_amount,
                'total' => $inv->total_amount,
            ]);
    }

    /**
     * Get input tax list for SPT reporting (Faktur Pajak Masukan).
     *
     * @return Collection<int, array>
     */
    public function getInputTaxList(?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->endOfMonth()->toDateString();

        return Bill::query()
            ->whereIn('status', [
                DocumentStatus::Received,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('bill_date', [$startDate, $endDate.' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->orderBy('bill_date')
            ->get()
            ->map(fn ($bill) => [
                'tanggal' => $bill->bill_date->format('d/m/Y'),
                'nomor_faktur_vendor' => $bill->vendor_invoice_number ?? '-',
                'nomor_internal' => $bill->bill_number,
                'nama_penjual' => $bill->contact->name,
                'npwp_penjual' => $bill->contact->npwp ?? '-',
                'dpp' => $bill->subtotal,
                'ppn' => $bill->tax_amount,
                'total' => $bill->total_amount,
            ]);
    }

    /**
     * Get monthly PPN data for export.
     *
     * @return array{
     *     period: array{month: int, year: int},
     *     output_tax: array{invoices: Collection},
     *     input_tax: array{bills: Collection}
     * }
     */
    public function getMonthlyPpn(int $month, int $year): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // PPN Keluaran (Output Tax) - From posted invoices
        $invoices = Invoice::query()
            ->whereIn('status', [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('invoice_date', [$startDate->toDateString(), $endDate->toDateString().' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->orderBy('invoice_date')
            ->get()
            ->map(fn ($inv) => [
                'invoice_number' => $inv->invoice_number,
                'date' => $inv->invoice_date->format('Y-m-d'),
                'contact' => $inv->contact->name,
                'npwp' => $inv->contact->npwp,
                'subtotal' => $inv->subtotal,
                'tax_amount' => $inv->tax_amount,
            ]);

        // PPN Masukan (Input Tax) - From posted bills
        $bills = Bill::query()
            ->whereIn('status', [
                DocumentStatus::Received,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
            ])
            ->whereBetween('bill_date', [$startDate->toDateString(), $endDate->toDateString().' 23:59:59'])
            ->where('tax_amount', '>', 0)
            ->with('contact')
            ->orderBy('bill_date')
            ->get()
            ->map(fn ($bill) => [
                'bill_number' => $bill->bill_number,
                'date' => $bill->bill_date->format('Y-m-d'),
                'contact' => $bill->contact->name,
                'npwp' => $bill->contact->npwp,
                'subtotal' => $bill->subtotal,
                'tax_amount' => $bill->tax_amount,
            ]);

        return [
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
            'output_tax' => [
                'invoices' => $invoices,
            ],
            'input_tax' => [
                'bills' => $bills,
            ],
        ];
    }
}
