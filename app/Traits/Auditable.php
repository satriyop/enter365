<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Core\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait to automatically log create/update/delete actions via AuditLog.
 *
 * Add this trait to any Eloquent model that needs automatic audit logging:
 *
 * ```php
 * class Invoice extends Model
 * {
 *     use Auditable;
 *
 *     // Optionally exclude fields from audit:
 *     protected array $excludedFromAudit = ['updated_at', 'created_at', 'remember_token'];
 * }
 * ```
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            /** @var Model&Auditable $model */
            AuditLog::log(
                AuditLog::ACTION_CREATED,
                $model,
                null,
                $model->getAuditableNewValues(),
            );
        });

        static::updated(function (Model $model) {
            /** @var Model&Auditable $model */
            $changes = $model->getChanges();
            $excluded = $model->getExcludedAuditFields();
            $changes = array_diff_key($changes, array_flip($excluded));

            if (empty($changes)) {
                return;
            }

            $original = collect($model->getOriginal())
                ->only(array_keys($changes))
                ->toArray();

            AuditLog::log(
                AuditLog::ACTION_UPDATED,
                $model,
                $original,
                $changes,
            );
        });

        static::deleted(function (Model $model) {
            /** @var Model&Auditable $model */
            AuditLog::log(
                AuditLog::ACTION_DELETED,
                $model,
            );
        });
    }

    /**
     * Get fields excluded from audit logging.
     *
     * @return list<string>
     */
    protected function getExcludedAuditFields(): array
    {
        return property_exists($this, 'excludedFromAudit')
            ? $this->excludedFromAudit
            : ['updated_at', 'created_at'];
    }

    /**
     * Get auditable attribute values for new records.
     *
     * @return array<string, mixed>
     */
    protected function getAuditableNewValues(): array
    {
        $excluded = $this->getExcludedAuditFields();

        return array_diff_key($this->attributesToArray(), array_flip($excluded));
    }
}
