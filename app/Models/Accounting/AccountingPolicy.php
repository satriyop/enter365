<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $inventory_method
 * @property string $cogs_recognition
 * @property string $return_accounting
 * @property string $manufacturing_costing
 * @property string $closing_strategy
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class AccountingPolicy extends Model
{
    protected $fillable = [
        'inventory_method',
        'cogs_recognition',
        'return_accounting',
        'manufacturing_costing',
        'closing_strategy',
    ];

    /** Get the singleton row (cached per-request via once()). */
    public static function current(): self
    {
        return once(fn () => self::firstOrFail());
    }
}
