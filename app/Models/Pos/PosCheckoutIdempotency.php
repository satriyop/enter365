<?php

declare(strict_types=1);

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCheckoutIdempotency extends Model
{
    use MassPrunable;

    protected $fillable = [
        'pos_session_id',
        'idempotency_key',
        'pos_sale_id',
    ];

    /**
     * Idempotency keys are only meaningful for a short retry window.
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDay());
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }
}
