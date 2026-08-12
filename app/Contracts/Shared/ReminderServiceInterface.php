<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\PaymentReminder;
use Illuminate\Support\Collection;

/**
 * Interface for Payment Reminder operations.
 */
interface ReminderServiceInterface
{
    /**
     * Schedule reminders for an invoice.
     *
     * @return Collection<int, PaymentReminder>
     */
    public function scheduleInvoiceReminders(Invoice $invoice): Collection;

    /**
     * Schedule reminders for a bill.
     *
     * @return Collection<int, PaymentReminder>
     */
    public function scheduleBillReminders(Bill $bill): Collection;

    /**
     * Send all due reminders.
     *
     * @return Collection<int, PaymentReminder>
     */
    public function sendDueReminders(): Collection;

    /**
     * Send a single reminder.
     */
    public function sendReminder(PaymentReminder $reminder): bool;

    /**
     * Cancel all reminders for a document.
     */
    public function cancelReminders(Invoice|Bill $document): int;

    /**
     * Schedule a manual custom reminder for an invoice.
     *
     * @param  array{scheduled_date: string|\DateTimeInterface, type: string, channel: string, message?: string|null}  $data
     */
    public function scheduleManualInvoiceReminder(Invoice $invoice, array $data, ?int $createdBy = null): PaymentReminder;

    /**
     * Create and immediately send a reminder for an invoice.
     *
     * @param  array{message?: string|null, channel?: string|null}  $data
     */
    public function createAndSendImmediateInvoiceReminder(Invoice $invoice, array $data = [], ?int $createdBy = null): PaymentReminder;
}
