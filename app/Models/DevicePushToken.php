<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePushToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'token_hash',
        'platform',
        'timezone',
    ];

    protected static function booted(): void
    {
        static::saving(function (DevicePushToken $deviceToken) {
            if ($deviceToken->token) {
                $deviceToken->token_hash = hash('sha256', $deviceToken->token);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isValidExpoToken(string $token): bool
    {
        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[.+]$/', $token);
    }
}
