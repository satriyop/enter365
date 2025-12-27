<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentReminder;
use App\Notifications\OverdueNotice;
use App\Notifications\PaymentReminderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ReminderService
{
    /**
     * Create scheduled reminders for an invoice.
     *
     * @return Collection<int, PaymentReminder>
     */
    public function scheduleInvoiceReminders(Invoice $invoice): Collection
    {
        if (! config('accounting.notifications.payment_reminder.enabled', true)) {
            return collect();
        }

        $reminders = collect();
        $intervals = config('accounting.overdue.reminder_intervals', [1, 7, 14, 30]);

        // Create upcoming reminder (before due date)
        $upcomingDate = $invoice->due_date->copy()->subDays(3);
        if ($upcomingDate->isFuture()) {
            $reminders->push(PaymentReminder::create([
                'remindable_type' => Invoice::class,
                'remindable_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'type' => PaymentReminder::TYPE_UPCOMING,
                'days_offset' => -3,
                'scheduled_date' => $upcomingDate,
                'status' => PaymentReminder::STATUS_PENDING,
                'channel' => PaymentReminder::CHANNEL_EMAIL,
            ]));
        }

        // Create overdue reminders
        foreach ($intervals as $days) {
            $reminders->push(PaymentReminder::create([
                'remindable_type' => Invoice::class,
                'remindable_id' => $invoice->id,
                'contact_id' => $invoice->contact_id,
                'type' => $days >= 30 ? PaymentReminder::TYPE_FINAL_NOTICE : PaymentReminder::TYPE_OVERDUE,
                'days_offset' => $days,
                'scheduled_date' => $invoice->due_date->copy()->addDays($days),
                'status' => PaymentReminder::STATUS_PENDING,
                'channel' => PaymentReminder::CHANNEL_EMAIL,
            ]));
        }

        return $reminders;
    }

    /**
     * Create scheduled reminders for a bill (internal reminders).
     *
     * @return Collection<int, PaymentReminder>
     */
    public function scheduleBillReminders(Bill $bill): Collection
    {
        $reminders = collect();

        // Create upcoming reminder (before due date)
        $upcomingDate = $bill->due_date->copy()->subDays(3);
        if ($upcomingDate->isFuture()) {
            $reminders->push(PaymentReminder::create([
                'remindable_type' => Bill::class,
                'remindable_id' => $bill->id,
                'contact_id' => $bill->contact_id,
                'type' => PaymentReminder::TYPE_UPCOMING,
                'days_offset' => -3,
                'scheduled_date' => $upcomingDate,
                'status' => PaymentReminder::STATUS_PENDING,
                'channel' => PaymentReminder::CHANNEL_DATABASE,
            ]));
        }

        return $reminders;
    }

    /**
     * Send all pending reminders that are due.
     *
     * @return Collection<int, PaymentReminder>
     */
    public function sendDueReminders(): Collection
    {
        $sent = collect();
        $reminders = PaymentReminder::dueToday();

        foreach ($reminders as $reminder) {
            if ($this->sendReminder($reminder)) {
                $sent->push($reminder);
            }
        }

        return $sent;
    }

    /**
     * Send a single reminder.
     */
    public function sendReminder(PaymentReminder $reminder): bool
    {
        $remindable = $reminder->remindable;

        // Check if document is still unpaid
        if ($remindable instanceof Invoice) {
            if ($remindable->status === Invoice::STATUS_PAID ||
                $remindable->status === Invoice::STATUS_CANCELLED) {
                $reminder->cancel();

                return false;
            }
        }

        if ($remindable instanceof Bill) {
            if ($remindable->status === Bill::STATUS_PAID ||
                $remindable->status === Bill::STATUS_CANCELLED) {
                $reminder->cancel();

                return false;
            }
        }

        try {
            // Send notification based on type
            $contact = $reminder->contact;

            if ($reminder->channel === PaymentReminder::CHANNEL_EMAIL && $contact->email) {
                if ($reminder->type === PaymentReminder::TYPE_UPCOMING) {
                    Notification::route('mail', $contact->email)
                        ->notify(new PaymentReminderNotification($reminder));
                } else {
                    Notification::route('mail', $contact->email)
                        ->notify(new OverdueNotice($reminder));
                }
            }

            // Update reminder count on the document
            if ($remindable instanceof Invoice || $remindable instanceof Bill) {
                $remindable->update([
                    'reminder_count' => $remindable->reminder_count + 1,
                    'last_reminder_at' => now(),
                ]);
            }

            $reminder->markAsSent();

            return true;
        } catch (\Exception $e) {
            $reminder->markAsFailed($e->getMessage());

            return false;
        }
    }

    /**
     * Cancel all pending reminders for a document.
     */
    public function cancelReminders(Invoice|Bill $document): int
    {
        return PaymentReminder::query()
            ->where('remindable_type', $document::class)
            ->where('remindable_id', $document->id)
            ->where('status', PaymentReminder::STATUS_PENDING)
            ->update(['status' => PaymentReminder::STATUS_CANCELLED]);
    }
}
