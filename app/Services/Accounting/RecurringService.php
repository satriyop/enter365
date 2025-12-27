<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\RecurringTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecurringService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Generate documents for all due recurring templates.
     *
     * @return Collection<int, Invoice|Bill>
     */
    public function generateDueDocuments(): Collection
    {
        $templates = RecurringTemplate::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('occurrences_limit')
                    ->orWhereRaw('occurrences_count < occurrences_limit');
            })
            ->where(function ($q) {
                $q->whereNull('next_generate_date')
                    ->orWhere('next_generate_date', '<=', now());
            })
            ->get();

        $generated = collect();

        foreach ($templates as $template) {
            $document = $this->generateFromTemplate($template);
            if ($document) {
                $generated->push($document);
            }
        }

        return $generated;
    }

    /**
     * Generate a document from a recurring template.
     */
    public function generateFromTemplate(RecurringTemplate $template): Invoice|Bill|null
    {
        if (! $template->shouldGenerate()) {
            return null;
        }

        return DB::transaction(function () use ($template) {
            $document = match ($template->type) {
                RecurringTemplate::TYPE_INVOICE => $this->generateInvoice($template),
                RecurringTemplate::TYPE_BILL => $this->generateBill($template),
                default => null,
            };

            if ($document) {
                // Update template
                $template->update([
                    'occurrences_count' => $template->occurrences_count + 1,
                    'next_generate_date' => $template->calculateNextDate(),
                ]);

                // Auto-post if configured
                if ($template->auto_post) {
                    if ($document instanceof Invoice) {
                        $this->journalService->postInvoice($document);
                    } elseif ($document instanceof Bill) {
                        $this->journalService->postBill($document);
                    }
                }
            }

            return $document;
        });
    }

    /**
     * Generate an invoice from template.
     */
    protected function generateInvoice(RecurringTemplate $template): Invoice
    {
        $invoiceDate = now();
        $dueDate = $invoiceDate->copy()->addDays($template->payment_term_days);

        $subtotal = 0;
        foreach ($template->items as $item) {
            $subtotal += (int) round($item['quantity'] * $item['unit_price']);
        }

        $taxAmount = (int) round($subtotal * ($template->tax_rate / 100));
        $totalAmount = $subtotal + $taxAmount - $template->discount_amount;

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'contact_id' => $template->contact_id,
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'description' => $template->description,
            'reference' => $template->reference,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $template->tax_rate,
            'discount_amount' => $template->discount_amount,
            'early_discount_percent' => $template->early_discount_percent,
            'early_discount_days' => $template->early_discount_days,
            'early_discount_deadline' => $template->early_discount_days > 0
                ? $invoiceDate->copy()->addDays($template->early_discount_days)
                : null,
            'total_amount' => $totalAmount,
            'currency' => $template->currency,
            'exchange_rate' => 1,
            'base_currency_total' => $totalAmount,
            'status' => Invoice::STATUS_DRAFT,
            'recurring_template_id' => $template->id,
            'created_by' => $template->created_by,
        ]);

        foreach ($template->items as $item) {
            $amount = (int) round($item['quantity'] * $item['unit_price']);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'amount' => $amount,
                'revenue_account_id' => $item['revenue_account_id'] ?? null,
            ]);
        }

        return $invoice;
    }

    /**
     * Generate a bill from template.
     */
    protected function generateBill(RecurringTemplate $template): Bill
    {
        $billDate = now();
        $dueDate = $billDate->copy()->addDays($template->payment_term_days);

        $subtotal = 0;
        foreach ($template->items as $item) {
            $subtotal += (int) round($item['quantity'] * $item['unit_price']);
        }

        $taxAmount = (int) round($subtotal * ($template->tax_rate / 100));
        $totalAmount = $subtotal + $taxAmount - $template->discount_amount;

        $bill = Bill::create([
            'bill_number' => Bill::generateBillNumber(),
            'contact_id' => $template->contact_id,
            'bill_date' => $billDate,
            'due_date' => $dueDate,
            'description' => $template->description,
            'reference' => $template->reference,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $template->tax_rate,
            'discount_amount' => $template->discount_amount,
            'early_discount_percent' => $template->early_discount_percent,
            'early_discount_days' => $template->early_discount_days,
            'early_discount_deadline' => $template->early_discount_days > 0
                ? $billDate->copy()->addDays($template->early_discount_days)
                : null,
            'total_amount' => $totalAmount,
            'currency' => $template->currency,
            'exchange_rate' => 1,
            'base_currency_total' => $totalAmount,
            'status' => Bill::STATUS_DRAFT,
            'recurring_template_id' => $template->id,
            'created_by' => $template->created_by,
        ]);

        foreach ($template->items as $item) {
            $amount = (int) round($item['quantity'] * $item['unit_price']);
            BillItem::create([
                'bill_id' => $bill->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $item['unit_price'],
                'amount' => $amount,
                'expense_account_id' => $item['expense_account_id'] ?? null,
            ]);
        }

        return $bill;
    }

    /**
     * Create a recurring template from an existing invoice.
     */
    public function createTemplateFromInvoice(Invoice $invoice, array $scheduleData): RecurringTemplate
    {
        $items = $invoice->items->map(function ($item) {
            return [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'revenue_account_id' => $item->revenue_account_id,
            ];
        })->toArray();

        return RecurringTemplate::create([
            'name' => $scheduleData['name'] ?? 'Recurring: '.$invoice->invoice_number,
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => $invoice->contact_id,
            'frequency' => $scheduleData['frequency'],
            'interval' => $scheduleData['interval'] ?? 1,
            'start_date' => $scheduleData['start_date'],
            'end_date' => $scheduleData['end_date'] ?? null,
            'next_generate_date' => $scheduleData['start_date'],
            'occurrences_limit' => $scheduleData['occurrences_limit'] ?? null,
            'description' => $invoice->description,
            'reference' => $invoice->reference,
            'tax_rate' => $invoice->tax_rate,
            'discount_amount' => $invoice->discount_amount,
            'early_discount_percent' => $invoice->early_discount_percent ?? 0,
            'early_discount_days' => $invoice->early_discount_days ?? 0,
            'payment_term_days' => $invoice->contact->payment_term_days ?? 30,
            'currency' => $invoice->currency ?? 'IDR',
            'items' => $items,
            'is_active' => true,
            'auto_post' => $scheduleData['auto_post'] ?? false,
            'auto_send' => $scheduleData['auto_send'] ?? false,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Create a recurring template from an existing bill.
     */
    public function createTemplateFromBill(Bill $bill, array $scheduleData): RecurringTemplate
    {
        $items = $bill->items->map(function ($item) {
            return [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'expense_account_id' => $item->expense_account_id,
            ];
        })->toArray();

        return RecurringTemplate::create([
            'name' => $scheduleData['name'] ?? 'Recurring: '.$bill->bill_number,
            'type' => RecurringTemplate::TYPE_BILL,
            'contact_id' => $bill->contact_id,
            'frequency' => $scheduleData['frequency'],
            'interval' => $scheduleData['interval'] ?? 1,
            'start_date' => $scheduleData['start_date'],
            'end_date' => $scheduleData['end_date'] ?? null,
            'next_generate_date' => $scheduleData['start_date'],
            'occurrences_limit' => $scheduleData['occurrences_limit'] ?? null,
            'description' => $bill->description,
            'reference' => $bill->reference,
            'tax_rate' => $bill->tax_rate,
            'discount_amount' => $bill->discount_amount,
            'early_discount_percent' => $bill->early_discount_percent ?? 0,
            'early_discount_days' => $bill->early_discount_days ?? 0,
            'payment_term_days' => $bill->contact->payment_term_days ?? 30,
            'currency' => $bill->currency ?? 'IDR',
            'items' => $items,
            'is_active' => true,
            'auto_post' => $scheduleData['auto_post'] ?? false,
            'auto_send' => $scheduleData['auto_send'] ?? false,
            'created_by' => auth()->id(),
        ]);
    }
}
