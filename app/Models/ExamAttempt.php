<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'user_id',
        'device_id',
        'exam_id',
        'started_at',
        'completed_at',
        'time_spent',
        'score',
        'total_questions',
        'correct_answers',
        'status',
        'subjects',
        'subjects_data',
        'duration_minutes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'time_spent' => 'integer',
        'score' => 'integer',
        'total_questions' => 'integer',
        'correct_answers' => 'integer',
        'subjects' => 'array',
        'subjects_data' => 'array',
        'duration_minutes' => 'integer',
    ];

    /**
     * Get the user that owns the exam attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exam for the attempt.
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the user answers for the attempt.
     */
    public function userAnswers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    /**
     * Calculate the percentage score.
     */
    public function getPercentageAttribute(): float
    {
        // For JAMB, percentage is based on 400 marks
        if ($this->exam && $this->exam->exam_type === 'JAMB') {
            return round(($this->score / 400) * 100, 2);
        }

        // For other exams, percentage is based on total questions
        if ($this->total_questions === 0) {
            return 0;
        }

        return round(($this->correct_answers / $this->total_questions) * 100, 2);
    }
}
