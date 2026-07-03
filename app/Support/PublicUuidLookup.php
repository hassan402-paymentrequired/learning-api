<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PublicUuidLookup
{
    public static function isUuid(?string $value): bool
    {
        return is_string($value) && Str::isUuid($value);
    }

    /**
     * @template T of Model
     * @param  class-string<T>  $modelClass
     * @return T
     */
    public static function findOrFail(string $modelClass, string $uuid): Model
    {
        /** @var T|null $model */
        $model = $modelClass::query()->where('uuid', $uuid)->first();

        if (!$model) {
            abort(404, class_basename($modelClass) . ' not found.');
        }

        return $model;
    }

    /**
     * @template T of Model
     * @param  class-string<T>  $modelClass
     * @return T|null
     */
    public static function find(string $modelClass, ?string $uuid): ?Model
    {
        if (!self::isUuid($uuid)) {
            return null;
        }

        /** @var T|null */
        return $modelClass::query()->where('uuid', $uuid)->first();
    }
}
