<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * Generates e-Faktur CSV files per DJP specification.
 *
 * The CSV contains two row types:
 * - FK (Faktur Keluaran) rows: document headers
 * - OF (Output Faktur) rows: line item details
 *
 * Cancelled invoices are included with is_cancelled flag
 * because DJP requires all allocated NSFP numbers to be reported.
 */
class EfakturExportService
{
    /**
     * Generate e-Faktur CSV content for a date range.
     *
     * Returns CSV string ready for download/file write.
     */
    public function generateCsv(string $startDate, string $endDate): string
    {
        $invoices = $this->getInvoicesForExport($startDate, $endDate);

        if ($invoices->isEmpty()) {
            return '';
        }

        $rows = [];

        foreach ($invoices as $invoice) {
            // FK row (Faktur Keluaran header)
            $rows[] = $this->buildFkRow($invoice);

            // OF rows (line items) — skip for cancelled invoices
            if (! $invoice->is_nsfp_cancelled) {
                foreach ($invoice->items as $item) {
                    $rows[] = $this->buildOfRow($invoice, $item);
                }
            }
        }

        return implode("\n", $rows);
    }

    /**
     * Get invoices that have NSFP numbers assigned within the date range.
     *
     * @return Collection<int, Invoice>
     */
    private function getInvoicesForExport(string $startDate, string $endDate): Collection
    {
        return Invoice::query()
            ->whereNotNull('nsfp_number')
            ->whereBetween('invoice_date', [$startDate, $endDate.' 23:59:59'])
            ->whereIn('status', [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Paid,
                DocumentStatus::Overdue,
                DocumentStatus::Cancelled, // Cancelled NSFP must be reported
            ])
            ->with(['contact', 'items'])
            ->orderBy('nsfp_number')
            ->get();
    }

    /**
     * Build FK (Faktur Keluaran) row — document header.
     *
     * FK format per DJP e-Faktur spec:
     * FK,KodeFaktur,NomorFaktur,BulanFaktur,TahunFaktur,TanggalFaktur,
     * NPWPPembeli,NamaPembeli,AlamatPembeli,DPP,PPN,PPnBM,IsCancelled,IsAmended
     */
    private function buildFkRow(Invoice $invoice): string
    {
        $nsfpParts = $this->parseNsfpNumber($invoice->nsfp_number);

        $fields = [
            'FK',                                          // Row type
            $nsfpParts['transaction_code'],                // Kode Transaksi (e.g., 010)
            $nsfpParts['serial_number'],                   // Nomor Seri (8-digit)
            $invoice->invoice_date->format('m'),           // Bulan
            $invoice->invoice_date->format('Y'),           // Tahun
            $invoice->invoice_date->format('d/m/Y'),       // Tanggal Faktur
            $this->formatNpwp($invoice->contact->npwp ?? ''),
            $this->escapeCsvField($invoice->contact->name),
            $this->escapeCsvField($invoice->contact->address ?? ''),
            $invoice->subtotal,                            // DPP
            $invoice->tax_amount,                          // PPN
            0,                                             // PPnBM (luxury tax, not used)
            $invoice->is_nsfp_cancelled ? 1 : 0,           // Is Cancelled
            0,                                             // Is Amended
        ];

        return implode(',', $fields);
    }

    /**
     * Build OF (Output Faktur) row — line item detail.
     *
     * OF format per DJP:
     * OF,NamaBarang,HargaSatuan,Jumlah,HargaTotal,Diskon,DPP,PPN,TarifPPN
     */
    private function buildOfRow(Invoice $invoice, $item): string
    {
        $fields = [
            'OF',                                            // Row type
            $this->escapeCsvField($item->description),       // Nama Barang/Jasa
            $item->unit_price,                               // Harga Satuan
            $item->quantity,                                 // Jumlah Barang
            $item->line_total,                               // Harga Total
            $item->discount_amount ?? 0,                     // Diskon
            $item->line_total - ($item->tax_amount ?? 0),    // DPP per line
            $item->tax_amount ?? 0,                          // PPN per line
            $item->tax_rate ?? (float) $invoice->tax_rate,   // Tarif PPN
        ];

        return implode(',', $fields);
    }

    /**
     * Parse NSFP number into components.
     *
     * Input: "010.000-26.00000001"
     * Output: ['transaction_code' => '010', 'branch_code' => '000', 'year_code' => '26', 'serial_number' => '00000001']
     *
     * @return array{transaction_code: string, branch_code: string, year_code: string, serial_number: string}
     */
    private function parseNsfpNumber(string $nsfpNumber): array
    {
        // Format: XXX.XXX-YY.NNNNNNNN
        $parts = preg_match('/^(\d{3})\.(\d{3})-(\d{2})\.(\d{8})$/', $nsfpNumber, $matches);

        if (! $parts) {
            return [
                'transaction_code' => '010',
                'branch_code' => '000',
                'year_code' => '00',
                'serial_number' => '00000000',
            ];
        }

        return [
            'transaction_code' => $matches[1],
            'branch_code' => $matches[2],
            'year_code' => $matches[3],
            'serial_number' => $matches[4],
        ];
    }

    /**
     * Format NPWP for e-Faktur (strip dots/dashes).
     */
    private function formatNpwp(string $npwp): string
    {
        return preg_replace('/[^0-9]/', '', $npwp) ?: '';
    }

    /**
     * Escape a field for CSV embedding.
     */
    private function escapeCsvField(string $value): string
    {
        $value = str_replace('"', '""', $value);

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.$value.'"';
        }

        return $value;
    }
}
