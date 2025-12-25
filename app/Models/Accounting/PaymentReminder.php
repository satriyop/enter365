<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentReminder extends Model
{
    use HasFactory;

    public const TYPE_UPCOMING = 'upcoming';

    public const TYPE_OVERDUE = 'overdue';

    public const TYPE_FINAL_NOTICE = 'final_notice';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_DATABASE = 'database';

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'contact_id',
        'type',
        'days_offset',
        'scheduled_date',
        'sent_date',
        'status',
        'channel',
        'message',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'sent_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if reminder is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if reminder was sent.
     */
    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Mark reminder as sent.
     */
    public function markAsSent(array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_date' => now()->toDateString(),
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Mark reminder as failed.
     */
    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    /**
     * Cancel this reminder.
     */
    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Get pending reminders that should be sent today.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PaymentReminder>
     */
    public static function dueToday(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('status', self::STATUS_PENDING)
            ->whereDate('scheduled_date', '<=', now())
            ->get();
    }
}
