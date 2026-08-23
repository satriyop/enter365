<?php

declare(strict_types=1);

namespace App\Models\Pos;

use App\Enums\Pos\PosTenderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleTender extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\PosSaleTenderFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_sale_id',
        'type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'type' => PosTenderType::class,
            'amount' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }
}
