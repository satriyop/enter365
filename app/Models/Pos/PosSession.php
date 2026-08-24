<?php

declare(strict_types=1);

namespace App\Models\Pos;

use App\Enums\Pos\PosPricingMode;
use App\Enums\Pos\PosSessionStatus;
use App\Models\Accounting\Account;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\PosSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'session_number',
        'status',
        'warehouse_id',
        'cash_account_id',
        'qris_account_id',
        'pricing_mode',
        'service_rate',
        'tax_add_rate',
        'tax_add_name',
        'opening_cash_amount',
        'expected_cash_amount',
        'counted_cash_amount',
        'cash_difference_amount',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosSessionStatus::class,
            'pricing_mode' => PosPricingMode::class,
            'service_rate' => 'float',
            'tax_add_rate' => 'float',
            'opening_cash_amount' => 'integer',
            'expected_cash_amount' => 'integer',
            'counted_cash_amount' => 'integer',
            'cash_difference_amount' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function qrisAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'qris_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return HasMany<PosSale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    /**
     * @return HasMany<PosSessionHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(PosSessionHold::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PosSessionStatus::Open;
    }

    public function usesAddOnPricing(): bool
    {
        return $this->pricing_mode === PosPricingMode::Add;
    }
}
