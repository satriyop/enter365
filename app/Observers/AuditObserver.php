<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Core\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Automatically logs create/update/delete actions via AuditLog.
 *
 * Register this observer for any model that needs audit logging.
 * Models can optionally define a `$excludedFromAudit` property
 * to specify fields that should not be tracked:
 *
 * ```php
 * protected array $excludedFromAudit = ['updated_at', 'created_at', 'remember_token'];
 * ```
 */
class AuditObserver
{
    private const DEFAULT_EXCLUDED_FIELDS = ['updated_at', 'created_at'];

    public function created(Model $model): void
    {
        $newValues = array_diff_key(
            $model->attributesToArray(),
            array_flip($this->getExcludedFields($model))
        );

        AuditLog::log(
            AuditLog::ACTION_CREATED,
            $model,
            null,
            $newValues,
        );
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $excluded = $this->getExcludedFields($model);
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
    }

    public function deleted(Model $model): void
    {
        AuditLog::log(
            AuditLog::ACTION_DELETED,
            $model,
        );
    }

    /**
     * Get fields excluded from audit logging for a model.
     *
     * @return list<string>
     */
    private function getExcludedFields(Model $model): array
    {
        if (property_exists($model, 'excludedFromAudit')) {
            return $model->excludedFromAudit;
        }

        return self::DEFAULT_EXCLUDED_FIELDS;
    }
}
