<?php

namespace App\Notifications;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueNotice extends Notification implements ShouldQueue
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
        return config('accounting.notifications.overdue_alert.channels', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->reminder->remindable;
        $contact = $this->reminder->contact;
        $companyName = config('accounting.company.name', 'Our Company');

        if ($document instanceof Invoice) {
            $documentNumber = $document->invoice_number;
            $dueDate = $document->due_date->format('d F Y');
            $daysOverdue = $document->getDaysOverdue();
            $amount = 'Rp '.number_format($document->getOutstandingAmount(), 0, ',', '.');
            $documentType = 'Faktur';
        } else {
            $documentNumber = $document->bill_number;
            $dueDate = $document->due_date->format('d F Y');
            $daysOverdue = $document->getDaysOverdue();
            $amount = 'Rp '.number_format($document->getOutstandingAmount(), 0, ',', '.');
            $documentType = 'Tagihan';
        }

        $isFinalNotice = $this->reminder->type === PaymentReminder::TYPE_FINAL_NOTICE;
        $subject = $isFinalNotice
            ? "PEMBERITAHUAN AKHIR: {$documentNumber} Telah Jatuh Tempo"
            : "Pemberitahuan Keterlambatan: {$documentNumber}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Yth. {$contact->name},");

        if ($isFinalNotice) {
            $message->line('**PEMBERITAHUAN AKHIR**')
                ->line("Kami memberitahukan bahwa {$documentType} **{$documentNumber}** telah melewati jatuh tempo selama **{$daysOverdue} hari**.")
                ->line("Jatuh tempo: **{$dueDate}**")
                ->line("Jumlah tertunggak: **{$amount}**")
                ->line('Mohon segera melakukan pembayaran untuk menghindari tindakan lebih lanjut.');
        } else {
            $message->line("Kami memberitahukan bahwa {$documentType} **{$documentNumber}** telah melewati jatuh tempo.")
                ->line("Jatuh tempo: **{$dueDate}** ({$daysOverdue} hari yang lalu)")
                ->line("Jumlah tertunggak: **{$amount}**")
                ->line('Mohon untuk segera melakukan pembayaran.');
        }

        return $message
            ->line('Jika Anda sudah melakukan pembayaran, mohon abaikan email ini.')
            ->salutation("Hormat kami,\n{$companyName}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $document = $this->reminder->remindable;

        return [
            'type' => 'overdue_notice',
            'reminder_type' => $this->reminder->type,
            'reminder_id' => $this->reminder->id,
            'document_type' => $document instanceof Invoice ? 'invoice' : 'bill',
            'document_id' => $document->id,
            'document_number' => $document->invoice_number ?? $document->bill_number,
            'contact_id' => $this->reminder->contact_id,
            'contact_name' => $this->reminder->contact->name,
            'due_date' => $document->due_date->format('Y-m-d'),
            'days_overdue' => $document instanceof Invoice
                ? $document->getDaysOverdue()
                : $document->getDaysOverdue(),
            'outstanding_amount' => $document->getOutstandingAmount(),
        ];
    }
}
