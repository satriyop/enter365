<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sales\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Quotation $quotation
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return config('accounting.notifications.quotation_approved.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = config('accounting.company.name', config('app.name'));
        $amount = 'Rp '.number_format($this->quotation->total_amount, 0, ',', '.');
        $validUntil = $this->quotation->valid_until?->format('d F Y') ?? '-';

        return (new MailMessage)
            ->subject("Penawaran {$this->quotation->quotation_number} Disetujui - {$company}")
            ->greeting('Yth. '.$this->quotation->contact?->name.',')
            ->line("Penawaran **{$this->quotation->quotation_number}** telah disetujui.")
            ->line("Nilai penawaran: **{$amount}**")
            ->line("Berlaku hingga: **{$validUntil}**")
            ->line('Silakan hubungi kami jika Anda ingin melanjutkan ke pesanan/faktur.')
            ->salutation("Hormat kami,\n{$company}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_approved',
            'quotation_id' => $this->quotation->id,
            'quotation_number' => $this->quotation->quotation_number,
            'total_amount' => $this->quotation->total_amount,
            'contact_id' => $this->quotation->contact_id,
        ];
    }
}
