<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxTag extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\TaxTagFactory> */
    use HasFactory;

    public const APPLICABILITY_BASE = 'base';

    public const APPLICABILITY_TAX = 'tax';

    /**
     * @var list<string>
     */
    public const APPLICABILITIES = [
        self::APPLICABILITY_BASE,
        self::APPLICABILITY_TAX,
    ];

    protected $fillable = [
        'code',
        'name',
        'applicability',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'applicability' => self::APPLICABILITY_TAX,
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
}
