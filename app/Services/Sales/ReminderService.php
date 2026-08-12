<?php

namespace App\Services\Sales;

use App\Contracts\Shared\ReminderServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\PaymentReminder;
use App\Notifications\OverdueNotice;
use App\Notifications\PaymentReminderNotification;
use App\Services\Base\Traits\WithOperationContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ReminderService implements ReminderServiceInterface
{
    use WithOperationContext;

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
            if ($remindable->status === DocumentStatus::Paid ||
                $remindable->status === DocumentStatus::Cancelled) {
                $reminder->cancel();

                return false;
            }
        }

        if ($remindable instanceof Bill) {
            if ($remindable->status === DocumentStatus::Paid ||
                $remindable->status === DocumentStatus::Cancelled) {
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

    /**
     * Cancel a single pending reminder.
     */
    public function cancelReminder(PaymentReminder $reminder): PaymentReminder
    {
        if (! $reminder->isPending()) {
            throw BusinessRuleException::operationNotAllowed(
                'membatalkan pengingat',
                'Hanya pengingat dengan status pending yang dapat dibatalkan.'
            );
        }

        $reminder->cancel();

        return $reminder->fresh(['remindable', 'contact']) ?? $reminder;
    }

    /**
     * Schedule a manual custom reminder for an invoice.
     *
     * @param  array{scheduled_date: string|\DateTimeInterface, type: string, channel: string, message?: string|null}  $data
     */
    public function scheduleManualInvoiceReminder(Invoice $invoice, array $data, ?int $createdBy = null): PaymentReminder
    {
        $scheduledDate = Carbon::parse($data['scheduled_date']);
        $daysOffset = $invoice->due_date->diffInDays($scheduledDate, false);

        return PaymentReminder::create([
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'type' => $data['type'],
            'days_offset' => $daysOffset,
            'scheduled_date' => $scheduledDate,
            'status' => PaymentReminder::STATUS_PENDING,
            'channel' => $data['channel'],
            'message' => $data['message'] ?? null,
            'created_by' => $createdBy ?? $this->getUserId(),
        ]);
    }

    /**
     * Create and immediately attempt to send a reminder for an invoice.
     *
     * @param  array{message?: string|null, channel?: string|null}  $data
     */
    public function createAndSendImmediateInvoiceReminder(Invoice $invoice, array $data = [], ?int $createdBy = null): PaymentReminder
    {
        $reminder = PaymentReminder::create([
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
            'type' => $invoice->isOverdue()
                ? PaymentReminder::TYPE_OVERDUE
                : PaymentReminder::TYPE_UPCOMING,
            'days_offset' => 0,
            'scheduled_date' => today(),
            'status' => PaymentReminder::STATUS_PENDING,
            'channel' => $data['channel'] ?? PaymentReminder::CHANNEL_EMAIL,
            'message' => $data['message'] ?? null,
            'created_by' => $createdBy ?? $this->getUserId(),
        ]);

        $this->sendReminder($reminder);

        return $reminder->fresh(['remindable', 'contact']) ?? $reminder;
    }
}
