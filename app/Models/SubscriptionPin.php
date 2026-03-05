<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'generated_by',
        'pin',
        'status',
        'used_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'used_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * The user this PIN was generated for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who generated this PIN.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Check if the PIN is still valid (unused and not expired).
     */
    public function isValid(): bool
    {
        return $this->status === 'unused'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
