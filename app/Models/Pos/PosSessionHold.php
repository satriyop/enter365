<?php

declare(strict_types=1);

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSessionHold extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\PosSessionHoldFactory> */
    use HasFactory;

    protected $fillable = [
        'pos_session_id',
        'lines',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'taken_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }
}
