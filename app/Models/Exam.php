<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'title',
        'description',
        'exam_type',
        'subject',
        'total_questions',
        'year',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_questions' => 'integer',
        'year' => 'integer',
    ];

    /**
     * Questions linked to this past question paper (many-to-many).
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_exam')
            ->withTimestamps();
    }

    /**
     * Refresh the cached total_questions count from the pivot table.
     */
    public function refreshTotalQuestions(): void
    {
        $this->update([
            'total_questions' => $this->questions()->count(),
        ]);
    }

    /**
     * Get the exam attempts for the exam.
     */
    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * The categories that belong to the exam.
     */
    public function examCategories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ExamCategory::class, 'exam_category_exam');
    }
}
