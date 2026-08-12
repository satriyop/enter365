<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Sales\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotationWonTeamNotification extends Notification implements ShouldQueue
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
        return config('accounting.notifications.quotation_won.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp '.number_format($this->quotation->total_amount, 0, ',', '.');
        $customer = $this->quotation->contact?->name ?? 'Pelanggan';

        return (new MailMessage)
            ->subject("Penawaran Menang: {$this->quotation->quotation_number}")
            ->greeting('Tim Sales,')
            ->line("Penawaran **{$this->quotation->quotation_number}** ditandai **menang**.")
            ->line("Pelanggan: **{$customer}**")
            ->line("Nilai: **{$amount}**")
            ->line('Lanjutkan proses konversi / fulfillment sesuai prosedur.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quotation_won_team',
            'quotation_id' => $this->quotation->id,
            'quotation_number' => $this->quotation->quotation_number,
            'total_amount' => $this->quotation->total_amount,
            'contact_id' => $this->quotation->contact_id,
        ];
    }
}
