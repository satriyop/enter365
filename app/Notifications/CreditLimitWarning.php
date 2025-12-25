<?php

namespace App\Notifications;

use App\Models\Accounting\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditLimitWarning extends Notification
{
    use Queueable;

    public function __construct(
        public Contact $contact,
        public string $warningType = 'warning' // 'warning' or 'exceeded'
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('accounting.notifications.credit_limit_warning.channels', ['database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $creditLimit = 'Rp ' . number_format($this->contact->credit_limit, 0, ',', '.');
        $outstanding = 'Rp ' . number_format($this->contact->getReceivableBalance(), 0, ',', '.');
        $utilization = number_format($this->contact->getCreditUtilization(), 1) . '%';

        $subject = $this->warningType === 'exceeded'
            ? "Peringatan: Batas Kredit Terlampaui - {$this->contact->name}"
            : "Peringatan: Batas Kredit Hampir Penuh - {$this->contact->name}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Peringatan Batas Kredit');

        if ($this->warningType === 'exceeded') {
            $message->line("Pelanggan **{$this->contact->name}** telah melampaui batas kredit yang ditetapkan.")
                ->line("- Batas Kredit: {$creditLimit}")
                ->line("- Piutang Outstanding: {$outstanding}")
                ->line("- Utilisasi: {$utilization}")
                ->line('Faktur baru untuk pelanggan ini akan diblokir sampai piutang dikurangi.');
        } else {
            $message->line("Pelanggan **{$this->contact->name}** mendekati batas kredit yang ditetapkan.")
                ->line("- Batas Kredit: {$creditLimit}")
                ->line("- Piutang Outstanding: {$outstanding}")
                ->line("- Utilisasi: {$utilization}")
                ->line('Mohon tinjau status pembayaran pelanggan ini.');
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credit_limit_warning',
            'warning_type' => $this->warningType,
            'contact_id' => $this->contact->id,
            'contact_code' => $this->contact->code,
            'contact_name' => $this->contact->name,
            'credit_limit' => $this->contact->credit_limit,
            'outstanding' => $this->contact->getReceivableBalance(),
            'utilization' => $this->contact->getCreditUtilization(),
        ];
    }
}
