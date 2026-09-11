<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $journal_id
 * @property int $next_number
 * @property string $layout
 * @property bool $manual_numbering
 */
class CheckSetting extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\CheckSettingFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'journal_id', 'next_number', 'layout', 'manual_numbering'];

    protected $attributes = [
        'is_active' => true,
        'next_number' => 1,
        'layout' => 'top',
        'manual_numbering' => false,

    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'next_number' => 'integer',
            'manual_numbering' => 'boolean',
        ];
    }

    public function journal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
