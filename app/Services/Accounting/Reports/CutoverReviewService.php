<?php

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Enums\DocumentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CutoverReviewService
{
    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [
            'bill-to-receive',
            'billed-not-received',
            'invoices-to-be-issued',
            'invoiced-not-delivered',
        ];
    }

    /**
     * @return array{report_name: string, kind: string, as_of_date: string, rows: list<array<string, mixed>>, totals: array{count: int, amount: int}}
     */
    public function report(string $kind, ?string $asOfDate = null): array
    {
        $asOf = $asOfDate ?: now()->toDateString();

        $rows = match ($kind) {
            'bill-to-receive' => $this->billToReceive($asOf),
            'billed-not-received' => $this->billedNotReceived($asOf),
            'invoices-to-be-issued' => $this->invoicesToBeIssued($asOf),
            'invoiced-not-delivered' => $this->invoicedNotDelivered($asOf),
            default => throw new \InvalidArgumentException('Unknown cutover review: '.$kind),
        };

        return [
            'report_name' => $this->reportName($kind),
            'kind' => $kind,
            'as_of_date' => $asOf,
            'rows' => $rows->values()->all(),
            'totals' => [
                'count' => $rows->count(),
                'amount' => (int) $rows->sum('amount'),
            ],
        ];
    }

    /**
     * Goods received, vendor bill not yet posted (GRNI).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function billToReceive(string $asOf): Collection
    {
        $postedBillPoIds = DB::table('bills')
            ->whereNull('deleted_at')
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->where('bill_date', '<=', $asOf)
            ->whereNotNull('purchase_order_id')
            ->pluck('purchase_order_id')
            ->all();

        $query = DB::table('goods_receipt_notes as grn')
            ->leftJoin('contacts as c', 'c.id', '=', 'grn.contact_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'grn.purchase_order_id')
            ->leftJoin('contacts as poc', 'poc.id', '=', 'po.contact_id')
            ->leftJoin('goods_receipt_note_items as gri', 'gri.goods_receipt_note_id', '=', 'grn.id')
            ->where('grn.status', DocumentStatus::Completed->value)
            ->where('grn.receipt_date', '<=', $asOf)
            ->groupBy('grn.id', 'grn.grn_number', 'grn.receipt_date', 'grn.status', 'po.po_number', 'c.name', 'poc.name')
            ->select([
                'grn.id',
                'grn.grn_number as number',
                'grn.receipt_date as date',
                'grn.status',
                'po.po_number as reference',
            ])
            ->selectRaw('COALESCE(c.name, poc.name) as partner')
            ->selectRaw('COALESCE(SUM(gri.quantity_received * gri.unit_price), 0) as amount');

        if ($postedBillPoIds !== []) {
            $query->where(function ($inner) use ($postedBillPoIds): void {
                $inner->whereNull('grn.purchase_order_id')
                    ->orWhereNotIn('grn.purchase_order_id', $postedBillPoIds);
            });
        }

        return $query->orderBy('grn.receipt_date')->orderBy('grn.id')->get()
            ->map(fn ($row): array => $this->row($row, 'grn'));
    }

    /**
     * Vendor bill posted, goods not yet received.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function billedNotReceived(string $asOf): Collection
    {
        $receivedPoIds = DB::table('goods_receipt_notes')
            ->where('status', DocumentStatus::Completed->value)
            ->where('receipt_date', '<=', $asOf)
            ->whereNotNull('purchase_order_id')
            ->pluck('purchase_order_id')
            ->all();

        $query = DB::table('bills as b')
            ->leftJoin('contacts as c', 'c.id', '=', 'b.contact_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'b.purchase_order_id')
            ->whereNull('b.deleted_at')
            ->whereNotIn('b.status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->where('b.bill_date', '<=', $asOf)
            ->select([
                'b.id',
                'b.bill_number as number',
                'b.bill_date as date',
                'b.status',
                'b.total_amount as amount',
                'c.name as partner',
                'po.po_number as reference',
            ]);

        if ($receivedPoIds !== []) {
            $query->where(function ($inner) use ($receivedPoIds): void {
                $inner->whereNull('b.purchase_order_id')
                    ->orWhereNotIn('b.purchase_order_id', $receivedPoIds);
            });
        }

        return $query->orderBy('b.bill_date')->orderBy('b.id')->get()
            ->map(fn ($row): array => $this->row($row, 'bill'));
    }

    /**
     * Goods delivered, customer invoice not yet posted.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function invoicesToBeIssued(string $asOf): Collection
    {
        $postedInvoiceIds = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->where('invoice_date', '<=', $asOf)
            ->pluck('id')
            ->all();

        $query = DB::table('delivery_orders as d')
            ->leftJoin('contacts as c', 'c.id', '=', 'd.contact_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'd.invoice_id')
            ->leftJoin('delivery_order_items as di', 'di.delivery_order_id', '=', 'd.id')
            ->whereNull('d.deleted_at')
            ->whereIn('d.status', [DocumentStatus::Shipped->value, DocumentStatus::Delivered->value])
            ->where('d.do_date', '<=', $asOf)
            ->groupBy('d.id', 'd.do_number', 'd.do_date', 'd.status', 'c.name', 'i.invoice_number')
            ->select([
                'd.id',
                'd.do_number as number',
                'd.do_date as date',
                'd.status',
                'c.name as partner',
                'i.invoice_number as reference',
            ])
            ->selectRaw('COALESCE(SUM(di.line_total), 0) as amount');

        if ($postedInvoiceIds !== []) {
            $query->where(function ($inner) use ($postedInvoiceIds): void {
                $inner->whereNull('d.invoice_id')
                    ->orWhereNotIn('d.invoice_id', $postedInvoiceIds);
            });
        }

        return $query->orderBy('d.do_date')->orderBy('d.id')->get()
            ->map(fn ($row): array => $this->row($row, 'delivery_order'));
    }

    /**
     * Customer invoice posted, goods not yet delivered.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function invoicedNotDelivered(string $asOf): Collection
    {
        $deliveredInvoiceIds = DB::table('delivery_orders')
            ->whereNull('deleted_at')
            ->whereIn('status', [DocumentStatus::Shipped->value, DocumentStatus::Delivered->value])
            ->where('do_date', '<=', $asOf)
            ->whereNotNull('invoice_id')
            ->pluck('invoice_id')
            ->all();

        $query = DB::table('invoices as i')
            ->leftJoin('contacts as c', 'c.id', '=', 'i.contact_id')
            ->whereNull('i.deleted_at')
            ->whereNotIn('i.status', [DocumentStatus::Draft->value, DocumentStatus::Cancelled->value])
            ->where('i.invoice_date', '<=', $asOf)
            ->select([
                'i.id',
                'i.invoice_number as number',
                'i.invoice_date as date',
                'i.status',
                'i.total_amount as amount',
                'c.name as partner',
            ])
            ->selectRaw('NULL as reference');

        if ($deliveredInvoiceIds !== []) {
            $query->whereNotIn('i.id', $deliveredInvoiceIds);
        }

        return $query->orderBy('i.invoice_date')->orderBy('i.id')->get()
            ->map(fn ($row): array => $this->row($row, 'invoice'));
    }

    /**
     * @param  object{id: mixed, number: mixed, date: mixed, partner: mixed, reference: mixed, amount: mixed, status: mixed}  $row
     * @return array<string, mixed>
     */
    private function row(object $row, string $documentType): array
    {
        $date = $row->date;
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        }

        return [
            'id' => (int) $row->id,
            'document_type' => $documentType,
            'number' => (string) $row->number,
            'date' => $date ? (string) $date : null,
            'partner' => $row->partner,
            'reference' => $row->reference,
            'amount' => (int) $row->amount,
            'status' => (string) $row->status,
        ];
    }

    private function reportName(string $kind): string
    {
        return match ($kind) {
            'bill-to-receive' => 'Bill to Receive',
            'billed-not-received' => 'Billed Not Received',
            'invoices-to-be-issued' => 'Invoices to Be Issued',
            'invoiced-not-delivered' => 'Invoiced Not Delivered',
            default => $kind,
        };
    }
}
