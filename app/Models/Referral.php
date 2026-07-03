<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'subscription_id',
        'referrer_reward_amount',
        'referred_discount_amount',
        'status',
        'rewarded_at',
        'notes',
    ];

    protected $casts = [
        'referrer_reward_amount' => 'decimal:2',
        'referred_discount_amount' => 'decimal:2',
        'rewarded_at' => 'datetime',
    ];

    /**
     * Get the user who referred.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Get the user who was referred.
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    /**
     * Get the subscription created by the referred user.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Check if referral is rewarded.
     */
    public function isRewarded(): bool
    {
        return $this->status === 'rewarded' && $this->rewarded_at !== null;
    }
}
