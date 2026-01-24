<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityViolation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'violation_type',
        'details',
        'attempt_id',
        'url',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Get the user that committed the violation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exam attempt related to this violation.
     */
    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }
}
