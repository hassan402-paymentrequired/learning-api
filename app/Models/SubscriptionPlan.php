<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SubscriptionPlan extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'interval',
        'interval_count',
        'currency',
        'is_active',
        'trial_days',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'interval_count' => 'integer',
        'is_active' => 'boolean',
        'trial_days' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get active plan (yearly plan).
     */
    public static function getActivePlan()
    {
        return static::where('is_active', true)->where('interval', 'year')->first();
    }
}
