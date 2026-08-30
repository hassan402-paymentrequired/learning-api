<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPublicUuid, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'referral_code',
        'referred_by',
        'paystack_customer_code',
        'push_notifications_enabled',
        'morning_reminder_time',
        'timezone',
        'last_morning_push_date',
        'subscription_reminder_emails_enabled',
        'marketing_emails_enabled',
        'last_push_notification_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'paystack_customer_code',
        'referral_code'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'is_admin' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            'subscription_reminder_emails_enabled' => 'boolean',
            'marketing_emails_enabled' => 'boolean',
            'last_marketing_email_sent_at' => 'datetime',
            'last_reengagement_email_sent_at' => 'datetime',
            'last_morning_push_date' => 'date',
            'last_push_notification_date' => 'date',
        ];
    }

    /**
     * Get the exam attempts for the user.
     */
    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Get the streaks for the user.
     */
    public function streaks()
    {
        return $this->hasMany(UserStreak::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function devicePushTokens()
    {
        return $this->hasMany(DevicePushToken::class);
    }

    /**
     * Get the subscriptions for the user.
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the subscription PINs generated for this user.
     */
    public function subscriptionPins()
    {
        return $this->hasMany(SubscriptionPin::class);
    }


    /**
     * Get users referred by this user.
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referralWithdrawals()
    {
        return $this->hasMany(ReferralWithdrawal::class);
    }

    /**
     * Get the referral that referred this user.
     */
    public function referredByReferral()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    /**
     * Get the user who referred this user.
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Get users referred by this user.
     */
    public function referredUsers()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Check if user has active subscription (ignores device binding).
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Check if user has active subscription valid for this client (by Device ID).
     * Subscription is only valid from the device it was bound to at purchase/activation.
     */
    public function hasActiveSubscriptionForDevice(?string $deviceId): bool
    {
        if (empty($deviceId)) {
            return false;
        }

        return $this->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('device_id', $deviceId)
            ->exists();
    }

    /**
     * Get the currently active subscription for a specific device.
     */
    public function activeSubscription(?string $deviceId = null)
    {
        $query = $this->subscriptions()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());

        if (!empty($deviceId)) {
            $query->where('device_id', $deviceId);
        }

        return $query->latest('expires_at')->first();
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Generate unique referral code for user.
     */
    public function generateReferralCode(): string
    {
        if ($this->referral_code) {
            return $this->referral_code;
        }

        // Generate a unique code based on user ID and random string
        $code = strtoupper(substr($this->name, 0, 3) . $this->id . substr(md5($this->email . $this->id), 0, 4));

        // Ensure uniqueness
        while (static::where('referral_code', $code)->exists()) {
            $code = strtoupper(substr($this->name, 0, 3) . $this->id . substr(md5($this->email . $this->id . time()), 0, 4));
        }

        $this->referral_code = $code;
        $this->save();

        return $code;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
