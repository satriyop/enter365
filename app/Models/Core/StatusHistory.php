<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Enums\DocumentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Tracks status transitions for documents.
 *
 * Provides an audit trail of all status changes with:
 * - Who made the change
 * - When it happened
 * - Optional context data
 *
 * @property int $id
 * @property string $statusable_type
 * @property int $statusable_id
 * @property DocumentStatus $from_status
 * @property DocumentStatus $to_status
 * @property int|null $user_id
 * @property array|null $context
 * @property \Carbon\Carbon $created_at
 * @property-read Model $statusable
 * @property-read User|null $user
 */
class StatusHistory extends Model
{
    protected $table = 'status_histories';

    public $timestamps = false;

    protected $fillable = [
        'statusable_type',
        'statusable_id',
        'from_status',
        'to_status',
        'user_id',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => DocumentStatus::class,
            'to_status' => DocumentStatus::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the parent model (Invoice, WorkOrder, etc.).
     */
    public function statusable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who made the status change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human-readable description of the transition.
     */
    public function getDescription(): string
    {
        return sprintf(
            '%s → %s',
            $this->from_status->label(),
            $this->to_status->label()
        );
    }

    /**
     * Get formatted timestamp.
     */
    public function getFormattedTime(): string
    {
        return $this->created_at->format('d M Y H:i');
    }
}
