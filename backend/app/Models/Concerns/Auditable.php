<?php

namespace App\Models\Concerns;

use App\Models\AuditEntry;
use App\Services\AuditService;

/**
 * Drop-in auditing for an Eloquent model.
 *
 * Hooks the model's own lifecycle events, so adding `use Auditable;` to an
 * existing model records every create, update and delete without touching a
 * single line of that model's logic or any controller.
 *
 * Models opt out of noisy columns with:
 *
 *     protected array $auditExclude = ['updated_at', 'quantity_in_stock'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditService::record(
                AuditEntry::EVENT_CREATED,
                $model,
                null,
                $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            if (! $changes) {
                return;
            }

            // getOriginal() still holds the pre-save values at this point.
            $before = [];
            foreach (array_keys($changes) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            AuditService::record(
                AuditEntry::EVENT_UPDATED,
                $model,
                $model->auditableAttributes($before),
                $changes,
            );
        });

        static::deleted(function ($model) {
            AuditService::record(
                AuditEntry::EVENT_DELETED,
                $model,
                $model->auditableAttributes($model->getAttributes()),
                null,
            );
        });
    }

    /** Strip timestamps and any model-declared exclusions. */
    public function auditableAttributes(array $attributes): array
    {
        $skip = array_merge(
            ['created_at', 'updated_at'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        foreach ($skip as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    /** This record's history, newest first. */
    public function auditTrail()
    {
        return AuditService::timelineFor($this);
    }
}
