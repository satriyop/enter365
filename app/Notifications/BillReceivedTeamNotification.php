<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Purchasing\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillReceivedTeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Bill $bill
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return config('accounting.notifications.bill_received.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = 'Rp '.number_format($this->bill->total_amount, 0, ',', '.');
        $vendor = $this->bill->contact?->name ?? 'Vendor';
        $dueDate = $this->bill->due_date?->format('d F Y') ?? '-';

        return (new MailMessage)
            ->subject("Tagihan Vendor Diterima: {$this->bill->bill_number}")
            ->greeting('Tim Account Payable,')
            ->line("Tagihan **{$this->bill->bill_number}** telah diterima.")
            ->line("Vendor: **{$vendor}**")
            ->line("Nilai: **{$amount}**")
            ->line("Jatuh tempo: **{$dueDate}**")
            ->line('Mohon dijadwalkan untuk pembayaran.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bill_received_team',
            'bill_id' => $this->bill->id,
            'bill_number' => $this->bill->bill_number,
            'total_amount' => $this->bill->total_amount,
            'contact_id' => $this->bill->contact_id,
        ];
    }
}
