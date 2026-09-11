<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticBudgetLine extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AnalyticBudgetLineFactory> */
    use HasFactory;

    protected $fillable = [
        'analytic_budget_id',
        'analytic_account_id',
        'planned_amount',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AnalyticBudget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(AnalyticBudget::class, 'analytic_budget_id');
    }

    /**
     * @return BelongsTo<AnalyticAccount, $this>
     */
    public function analyticAccount(): BelongsTo
    {
        return $this->belongsTo(AnalyticAccount::class);
    }
}
