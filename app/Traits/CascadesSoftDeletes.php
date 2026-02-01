<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cascades soft deletes and restores to related models that also use SoftDeletes.
 *
 * Usage:
 *   use SoftDeletes, CascadesSoftDeletes;
 *   protected array $cascadeSoftDeletes = ['payments', 'applications'];
 *
 * For recursive self-referencing relationships (e.g., Bom→childBoms),
 * the cascade propagates automatically since the child model also has this trait.
 */
trait CascadesSoftDeletes
{
    protected static function bootCascadesSoftDeletes(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            foreach ($model->getCascadeSoftDeletes() as $relation) {
                $related = $model->$relation()->getRelated();

                if (! in_array(SoftDeletes::class, class_uses_recursive($related))) {
                    continue;
                }

                // Use each() so child model events fire (important for recursive cascades)
                $model->$relation()->each(fn ($child) => $child->delete());
            }
        });

        static::restoring(function ($model) {
            foreach ($model->getCascadeSoftDeletes() as $relation) {
                $related = $model->$relation()->getRelated();

                if (! in_array(SoftDeletes::class, class_uses_recursive($related))) {
                    continue;
                }

                $model->$relation()->onlyTrashed()->each(fn ($child) => $child->restore());
            }
        });
    }

    protected function getCascadeSoftDeletes(): array
    {
        return property_exists($this, 'cascadeSoftDeletes') ? $this->cascadeSoftDeletes : [];
    }
}
