<?php

namespace CleaniqueCoders\ConfigWebhook\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model an auto-generated, route-bound `uuid` public identifier.
 *
 * Kept inside the package so it never depends on the host app's base model.
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->{$model->getUuidColumn()})) {
                $model->{$model->getUuidColumn()} = (string) Str::uuid();
            }
        });
    }

    public function getUuidColumn(): string
    {
        return 'uuid';
    }

    public function getRouteKeyName(): string
    {
        return $this->getUuidColumn();
    }
}
