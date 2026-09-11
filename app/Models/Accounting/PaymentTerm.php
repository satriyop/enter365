<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property string|null $note
 * @property list<array{type: string, value: int|float, days: int, due_type: string}> $lines
 */
class PaymentTerm extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\PaymentTermFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active', 'note', 'lines'];

    protected $attributes = [
        'is_active' => true,
        'note' => null,

    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lines' => 'array',
        ];
    }
}
