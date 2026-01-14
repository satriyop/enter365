---
adr: "0047"
title: "Notification System"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [notifications, communication]
related_adrs: [0045]
related_modules: [core]
impact: medium
---

# ADR-0047: Notification System

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing user notifications
- Sending emails
- Creating in-app notifications
- Building notification preferences

**Key takeaway:** Use Laravel Notifications with mail and database channels.

---

## Decision

Use Laravel Notification system with database and mail channels, queued for performance.

---

## Context

Notifications needed for:
1. Document approvals
2. Payment reminders
3. Low stock alerts
4. User mentions

---

## Implementation

### Notification Channels

| Channel | Use Case |
|---------|----------|
| database | In-app notifications |
| mail | Email alerts |
| broadcast | Real-time (future) |

### Database Notifications

```php
// notifications table (Laravel default)
$table->uuid('id')->primary();
$table->string('type');
$table->morphs('notifiable');
$table->text('data');
$table->timestamp('read_at')->nullable();
$table->timestamps();
```

### Notification Class

```php
// app/Notifications/InvoiceApproved.php
class InvoiceApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invoice {$this->invoice->number} Approved")
            ->greeting("Hello {$notifiable->name},")
            ->line("Invoice {$this->invoice->number} has been approved.")
            ->line("Customer: {$this->invoice->contact->name}")
            ->line("Amount: " . Currency::format($this->invoice->total))
            ->action('View Invoice', url("/invoices/{$this->invoice->id}"))
            ->line('Thank you for using Enter365!');
    }

    public function toArray(User $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'customer' => $this->invoice->contact->name,
            'total' => $this->invoice->total,
            'message' => "Invoice {$this->invoice->number} approved",
        ];
    }
}
```

### Sending Notifications

```php
// Send to user
$user->notify(new InvoiceApproved($invoice));

// Send to multiple users
Notification::send($users, new InvoiceApproved($invoice));

// Queue with delay
$user->notify((new PaymentReminder($invoice))->delay(now()->addHours(24)));
```

### Common Notifications

| Notification | Channels | Trigger |
|--------------|----------|---------|
| InvoiceApproved | database, mail | Invoice status → approved |
| PaymentReceived | database, mail | Payment recorded |
| PaymentReminder | mail | Invoice overdue |
| LowStockAlert | database, mail | Stock below minimum |
| QuotationExpiring | database, mail | Quotation near expiry |
| WorkOrderComplete | database | Work order finished |

### User Preferences

```php
// notification_preferences table
$table->foreignId('user_id');
$table->string('notification_type');      // App\Notifications\InvoiceApproved
$table->boolean('email')->default(true);
$table->boolean('database')->default(true);
$table->boolean('push')->default(false);  // Future
```

```php
// Check preferences before sending
public function via(User $notifiable): array
{
    $prefs = $notifiable->notificationPreferences()
        ->where('notification_type', static::class)
        ->first();

    $channels = [];

    if ($prefs?->database ?? true) {
        $channels[] = 'database';
    }

    if ($prefs?->email ?? true) {
        $channels[] = 'mail';
    }

    return $channels;
}
```

### Reading Notifications

```php
// User model
public function unreadNotifications(): MorphMany
{
    return $this->notifications()->whereNull('read_at');
}

// Mark as read
$notification->markAsRead();

// Mark all as read
$user->unreadNotifications->markAsRead();
```

### API Endpoints

```php
// routes/api.php
Route::get('notifications', [NotificationController::class, 'index']);
Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
```

### Livewire Component

```php
// Notification dropdown
public function getUnreadCountProperty(): int
{
    return auth()->user()->unreadNotifications()->count();
}

public function markAsRead(string $id): void
{
    auth()->user()->notifications()->find($id)->markAsRead();
}
```

---

## References

- [ADR-0045: Queue Job Strategy](./0045-queue-job-strategy.md)
- [Service Layer](../01-architecture/service-layer.md)

