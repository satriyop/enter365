<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_POSTED = 'posted';

    public const ACTION_REVERSED = 'reversed';

    public const ACTION_VOIDED = 'voided';

    public const ACTION_CLOSED = 'closed';

    public const ACTION_REOPENED = 'reopened';

    public const ACTION_SENT = 'sent';

    public const ACTION_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log an action.
     */
    public static function log(
        string $action,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null
    ): self {
        $user = auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'auditable_label' => static::getModelLabel($model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'notes' => $notes,
        ]);
    }

    /**
     * Get a human-readable label for a model.
     */
    protected static function getModelLabel(Model $model): string
    {
        return match (true) {
            $model instanceof Invoice => $model->invoice_number,
            $model instanceof Bill => $model->bill_number,
            $model instanceof Payment => $model->payment_number,
            $model instanceof JournalEntry => $model->entry_number,
            $model instanceof Account => $model->code . ' - ' . $model->name,
            $model instanceof Contact => $model->code . ' - ' . $model->name,
            $model instanceof FiscalPeriod => $model->name,
            default => (string) $model->getKey(),
        };
    }

    /**
     * Get logs for a specific model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    public static function forModel(Model $model): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('auditable_type', $model::class)
            ->where('auditable_id', $model->getKey())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get the changes between old and new values.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChanges(): array
    {
        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
