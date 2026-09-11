<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticAccount extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AnalyticAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'analytic_plan_id',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<AnalyticPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AnalyticPlan::class, 'analytic_plan_id');
    }
}
