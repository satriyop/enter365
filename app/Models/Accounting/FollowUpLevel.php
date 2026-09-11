<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $delay_days
 * @property int $sequence
 * @property bool $send_email
 * @property bool $join_invoices
 * @property string|null $message
 * @property bool $is_active
 * @property string|null $notes
 */
class FollowUpLevel extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\FollowUpLevelFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sequence' => 10,
        'send_email' => true,
        'join_invoices' => true,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'delay_days',
        'sequence',
        'send_email',
        'join_invoices',
        'message',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'delay_days' => 'integer',
            'sequence' => 'integer',
            'send_email' => 'boolean',
            'join_invoices' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
