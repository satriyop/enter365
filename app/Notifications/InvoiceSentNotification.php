<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sales\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return config('accounting.notifications.invoice_sent.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = config('accounting.company.name', config('app.name'));
        $amount = 'Rp '.number_format($this->invoice->total_amount, 0, ',', '.');
        $dueDate = $this->invoice->due_date?->format('d F Y') ?? '-';

        return (new MailMessage)
            ->subject("Faktur {$this->invoice->invoice_number} dari {$company}")
            ->greeting('Yth. '.$this->invoice->contact?->name.',')
            ->line("Berikut kami sampaikan faktur **{$this->invoice->invoice_number}**.")
            ->line("Jumlah tagihan: **{$amount}**")
            ->line("Jatuh tempo: **{$dueDate}**")
            ->line('Mohon segera diproses. Jika sudah dibayar, abaikan email ini.')
            ->salutation("Hormat kami,\n{$company}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_sent',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total_amount' => $this->invoice->total_amount,
            'contact_id' => $this->invoice->contact_id,
        ];
    }
}
