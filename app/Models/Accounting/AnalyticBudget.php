<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date_from
 * @property Carbon|null $date_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnalyticBudget extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AnalyticBudgetFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'name',
        'date_from',
        'date_to',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * @return HasMany<AnalyticBudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(AnalyticBudgetLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
