<?php

namespace App\Notifications;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PaymentReminder $reminder
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('accounting.notifications.payment_reminder.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->reminder->remindable;
        $contact = $this->reminder->contact;
        $companyName = config('accounting.company.name', 'Our Company');

        if ($document instanceof Invoice) {
            $documentNumber = $document->invoice_number;
            $dueDate = $document->due_date->format('d F Y');
            $amount = 'Rp ' . number_format($document->getOutstandingAmount(), 0, ',', '.');
            $documentType = 'Faktur';
        } else {
            $documentNumber = $document->bill_number;
            $dueDate = $document->due_date->format('d F Y');
            $amount = 'Rp ' . number_format($document->getOutstandingAmount(), 0, ',', '.');
            $documentType = 'Tagihan';
        }

        return (new MailMessage)
            ->subject("Pengingat Pembayaran: {$documentNumber}")
            ->greeting("Yth. {$contact->name},")
            ->line("Ini adalah pengingat bahwa {$documentType} **{$documentNumber}** akan jatuh tempo pada **{$dueDate}**.")
            ->line("Jumlah yang harus dibayar: **{$amount}**")
            ->line('Mohon untuk melakukan pembayaran tepat waktu untuk menghindari keterlambatan.')
            ->line("Jika Anda sudah melakukan pembayaran, mohon abaikan email ini.")
            ->salutation("Hormat kami,\n{$companyName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $document = $this->reminder->remindable;

        return [
            'type' => 'payment_reminder',
            'reminder_id' => $this->reminder->id,
            'document_type' => $document instanceof Invoice ? 'invoice' : 'bill',
            'document_id' => $document->id,
            'document_number' => $document->invoice_number ?? $document->bill_number,
            'contact_id' => $this->reminder->contact_id,
            'contact_name' => $this->reminder->contact->name,
            'due_date' => $document->due_date->format('Y-m-d'),
            'outstanding_amount' => $document->getOutstandingAmount(),
        ];
    }
}
