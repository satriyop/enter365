<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'account_id',
        'jan_amount',
        'feb_amount',
        'mar_amount',
        'apr_amount',
        'may_amount',
        'jun_amount',
        'jul_amount',
        'aug_amount',
        'sep_amount',
        'oct_amount',
        'nov_amount',
        'dec_amount',
        'annual_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'jan_amount' => 'integer',
            'feb_amount' => 'integer',
            'mar_amount' => 'integer',
            'apr_amount' => 'integer',
            'may_amount' => 'integer',
            'jun_amount' => 'integer',
            'jul_amount' => 'integer',
            'aug_amount' => 'integer',
            'sep_amount' => 'integer',
            'oct_amount' => 'integer',
            'nov_amount' => 'integer',
            'dec_amount' => 'integer',
            'annual_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the month amount column name.
     */
    public static function getMonthColumn(int $month): string
    {
        $months = [
            1 => 'jan_amount',
            2 => 'feb_amount',
            3 => 'mar_amount',
            4 => 'apr_amount',
            5 => 'may_amount',
            6 => 'jun_amount',
            7 => 'jul_amount',
            8 => 'aug_amount',
            9 => 'sep_amount',
            10 => 'oct_amount',
            11 => 'nov_amount',
            12 => 'dec_amount',
        ];

        return $months[$month] ?? 'jan_amount';
    }

    /**
     * Get amount for a specific month.
     */
    public function getMonthAmount(int $month): int
    {
        $column = self::getMonthColumn($month);

        return $this->{$column} ?? 0;
    }

    /**
     * Set amount for a specific month.
     */
    public function setMonthAmount(int $month, int $amount): void
    {
        $column = self::getMonthColumn($month);
        $this->{$column} = $amount;
    }

    /**
     * Get monthly amounts as array.
     *
     * @return array<int, int>
     */
    public function getMonthlyAmounts(): array
    {
        return [
            1 => $this->jan_amount,
            2 => $this->feb_amount,
            3 => $this->mar_amount,
            4 => $this->apr_amount,
            5 => $this->may_amount,
            6 => $this->jun_amount,
            7 => $this->jul_amount,
            8 => $this->aug_amount,
            9 => $this->sep_amount,
            10 => $this->oct_amount,
            11 => $this->nov_amount,
            12 => $this->dec_amount,
        ];
    }

    /**
     * Set monthly amounts from array.
     *
     * @param  array<int, int>  $amounts
     */
    public function setMonthlyAmounts(array $amounts): void
    {
        foreach ($amounts as $month => $amount) {
            $this->setMonthAmount($month, $amount);
        }
        $this->recalculateAnnual();
    }

    /**
     * Distribute annual amount evenly across months.
     */
    public function distributeEvenly(int $annualAmount): void
    {
        $monthlyAmount = (int) floor($annualAmount / 12);
        $remainder = $annualAmount - ($monthlyAmount * 12);

        for ($month = 1; $month <= 12; $month++) {
            // Add remainder to the last month
            $amount = $month === 12 ? $monthlyAmount + $remainder : $monthlyAmount;
            $this->setMonthAmount($month, $amount);
        }

        $this->annual_amount = $annualAmount;
    }

    /**
     * Recalculate annual amount from monthly amounts.
     */
    public function recalculateAnnual(): void
    {
        $this->annual_amount = $this->jan_amount
            + $this->feb_amount
            + $this->mar_amount
            + $this->apr_amount
            + $this->may_amount
            + $this->jun_amount
            + $this->jul_amount
            + $this->aug_amount
            + $this->sep_amount
            + $this->oct_amount
            + $this->nov_amount
            + $this->dec_amount;
    }

    /**
     * Get budget for a quarter.
     */
    public function getQuarterAmount(int $quarter): int
    {
        return match ($quarter) {
            1 => $this->jan_amount + $this->feb_amount + $this->mar_amount,
            2 => $this->apr_amount + $this->may_amount + $this->jun_amount,
            3 => $this->jul_amount + $this->aug_amount + $this->sep_amount,
            4 => $this->oct_amount + $this->nov_amount + $this->dec_amount,
            default => 0,
        };
    }

    /**
     * Get YTD budget up to a specific month.
     */
    public function getYtdBudget(int $month): int
    {
        $total = 0;
        for ($m = 1; $m <= $month; $m++) {
            $total += $this->getMonthAmount($m);
        }

        return $total;
    }
}
