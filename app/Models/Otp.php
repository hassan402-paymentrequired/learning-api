<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'otp',
        'type',
        'is_used',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the OTP.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is valid (not used and not expired).
     */
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    /**
     * Generate a 6-digit OTP.
     */
    public static function generate(): string
    {
        return str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new OTP for email verification.
     */
    public static function createForEmailVerification(User $user): self
    {
        // Invalidate any existing unused OTPs for this user
        self::where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return self::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'otp' => self::generate(),
            'type' => 'email_verification',
            'expires_at' => Carbon::now()->addMinutes(10), // OTP expires in 10 minutes
        ]);
    }

    /**
     * Create a new OTP for password reset.
     */
    public static function createForPasswordReset(string $email): self
    {
        // Invalidate any existing unused OTPs for this email
        self::where('email', $email)
            ->where('type', 'password_reset')
            ->where('is_used', false)
            ->update(['is_used' => true]);

        return self::create([
            'user_id' => null,
            'email' => $email,
            'otp' => self::generate(),
            'type' => 'password_reset',
            'expires_at' => Carbon::now()->addMinutes(15), // OTP expires in 15 minutes
        ]);
    }
}
