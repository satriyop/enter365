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
}
