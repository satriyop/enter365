<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationActivity extends Model
{
    use HasFactory;

    // Activity types
    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_NOTE = 'note';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_FOLLOW_UP_SCHEDULED = 'follow_up_scheduled';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_VISIT = 'visit';

    // Contact methods
    public const METHOD_PHONE = 'phone';

    public const METHOD_WHATSAPP = 'whatsapp';

    public const METHOD_EMAIL = 'email';

    public const METHOD_VISIT = 'visit';

    // Activity outcomes
    public const OUTCOME_POSITIVE = 'positive';

    public const OUTCOME_NEUTRAL = 'neutral';

    public const OUTCOME_NEGATIVE = 'negative';

    public const OUTCOME_NO_ANSWER = 'no_answer';

    protected $fillable = [
        'quotation_id',
        'user_id',
        'type',
        'contact_method',
        'subject',
        'description',
        'activity_at',
        'duration_minutes',
        'contact_person',
        'contact_phone',
        'next_follow_up_at',
        'follow_up_type',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'activity_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by activity type.
     *
     * @param  Builder<QuotationActivity>  $query
     * @return Builder<QuotationActivity>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for call activities.
     *
     * @param  Builder<QuotationActivity>  $query
     * @return Builder<QuotationActivity>
     */
    public function scopeCalls(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CALL);
    }

    /**
     * Scope for email activities.
     *
     * @param  Builder<QuotationActivity>  $query
     * @return Builder<QuotationActivity>
     */
    public function scopeEmails(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EMAIL);
    }

    /**
     * Scope for activities with scheduled follow-ups.
     *
     * @param  Builder<QuotationActivity>  $query
     * @return Builder<QuotationActivity>
     */
    public function scopeWithFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at');
    }

    /**
     * Get type label in Indonesian.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CALL => 'Telepon',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_MEETING => 'Rapat',
            self::TYPE_NOTE => 'Catatan',
            self::TYPE_STATUS_CHANGE => 'Perubahan Status',
            self::TYPE_FOLLOW_UP_SCHEDULED => 'Follow-up Dijadwalkan',
            self::TYPE_WHATSAPP => 'WhatsApp',
            self::TYPE_VISIT => 'Kunjungan',
            default => $this->type,
        };
    }

    /**
     * Get outcome label in Indonesian.
     */
    public function getOutcomeLabel(): ?string
    {
        return match ($this->outcome) {
            self::OUTCOME_POSITIVE => 'Positif',
            self::OUTCOME_NEUTRAL => 'Netral',
            self::OUTCOME_NEGATIVE => 'Negatif',
            self::OUTCOME_NO_ANSWER => 'Tidak Dijawab',
            default => null,
        };
    }

    /**
     * Get available activity types with labels.
     *
     * @return array<string, string>
     */
    public static function getActivityTypes(): array
    {
        return [
            self::TYPE_CALL => 'Telepon',
            self::TYPE_EMAIL => 'Email',
            self::TYPE_MEETING => 'Rapat',
            self::TYPE_NOTE => 'Catatan',
            self::TYPE_WHATSAPP => 'WhatsApp',
            self::TYPE_VISIT => 'Kunjungan',
        ];
    }

    /**
     * Get available contact methods with labels.
     *
     * @return array<string, string>
     */
    public static function getContactMethods(): array
    {
        return [
            self::METHOD_PHONE => 'Telepon',
            self::METHOD_WHATSAPP => 'WhatsApp',
            self::METHOD_EMAIL => 'Email',
            self::METHOD_VISIT => 'Kunjungan',
        ];
    }

    /**
     * Get available outcomes with labels.
     *
     * @return array<string, string>
     */
    public static function getOutcomes(): array
    {
        return [
            self::OUTCOME_POSITIVE => 'Positif',
            self::OUTCOME_NEUTRAL => 'Netral',
            self::OUTCOME_NEGATIVE => 'Negatif',
            self::OUTCOME_NO_ANSWER => 'Tidak Dijawab',
        ];
    }

    /**
     * Format duration as human-readable string.
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->duration_minutes === null) {
            return null;
        }

        if ($this->duration_minutes < 60) {
            return "{$this->duration_minutes} menit";
        }

        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($minutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$minutes} menit";
    }
}
