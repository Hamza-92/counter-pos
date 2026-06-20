<?php

namespace App\Services\Webhooks;

use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes Eloquent models into webhook-safe payload arrays.
 * Kept minimal — ships the model's own `toArray()` output and never loads
 * relations that weren't already loaded, to avoid heavy or recursive data.
 */
class Payload
{
    public static function fromModel(Model $model, array $extra = []): array
    {
        return array_merge([
            'id'    => $model->getKey(),
            'type'  => class_basename($model),
            'attributes' => $model->toArray(),
        ], $extra);
    }
}
