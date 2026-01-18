<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Sales;

use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;

class SendInvoicePaidNotification
{
    public function handle(object $event): void
    {
        if (! $event instanceof \App\Domain\Sales\Invoices\Events\InvoiceFullyPaid) {
            return;
        }

        $invoice = Invoice::with('contact')->find($event->invoiceId);

        if (! $invoice) {
            return;
        }

        $customer = $invoice->contact;

        if (! $customer || empty($customer->email)) {
            return;
        }

        $this->sendPaymentConfirmationToCustomer($customer, $invoice);
    }

    private function sendPaymentConfirmationToCustomer(Contact $customer, Invoice $invoice): void
    {
        $formattedAmount = $this->formatAmount($invoice->total_amount, $invoice->currency);

        $mailData = [
            'customer_name' => $customer->name,
            'invoice_number' => $invoice->invoice_number,
            'amount_paid' => $formattedAmount,
            'invoice_date' => $invoice->invoice_date->format('d/m/Y'),
            'payment_date' => now()->format('d/m/Y'),
            'company_name' => config('app.name', 'Enter365'),
        ];

        try {
            \Illuminate\Support\Facades\Mail::send(
                'emails.payment-confirmation',
                $mailData,
                function ($message) use ($customer, $invoice) {
                    $message->to($customer->email, $customer->name)
                        ->subject("Pembayaran Diterima - {$invoice->invoice_number}");
                }
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error(
                'Failed to send payment confirmation email',
                [
                    'invoice_id' => $invoice->id,
                    'customer_email' => $customer->email,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    private function formatAmount(int $amount, string $currency): string
    {
        if ($currency === 'IDR') {
            return 'Rp '.number_format($amount, 0, ',', '.');
        }

        return $currency.' '.number_format($amount / 100, 2, '.', ',');
    }

    public function shouldHandle(object $event): bool
    {
        return $event instanceof \App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
    }
}
