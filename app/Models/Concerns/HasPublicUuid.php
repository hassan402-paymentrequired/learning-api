<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assigns a stable public UUID for client/API use while keeping the internal bigint primary key.
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Public API routes use UUIDs; admin routes keep using internal numeric IDs.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }

        // Admin portal and legacy links still pass bigint IDs.
        if (is_numeric($value)) {
            return $this->where($this->getKeyName(), $value)->first();
        }

        return $this->where('uuid', $value)->first();
    }
}
