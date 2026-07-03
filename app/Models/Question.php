<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'exam_id',
        'subject_id',
        'exam_types',
        'question_text',
        'image',
        'question_type',
        'explanation',
        'expected_answer',
        'is_active',
    ];

    protected $casts = [
        'exam_types' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the exam that owns the question (nullable - questions can exist independently).
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the subject that owns the question.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the answers for the question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('order');
    }

    /**
     * Get the subject tests (DLI tests) this question belongs to (many-to-many).
     */
    public function subjectTests(): BelongsToMany
    {
        return $this->belongsToMany(SubjectTest::class, 'question_subject_test')
            ->withTimestamps();
    }

    /**
     * Get the dynamic exam categories this question belongs to (many-to-many).
     */
    public function examCategories(): BelongsToMany
    {
        return $this->belongsToMany(ExamCategory::class, 'exam_category_question')
            ->withTimestamps();
    }

    /**
     * Get the correct answer for the question.
     * For text_input and numeric_input, returns an answer record with expected_answer if it exists.
     */
    public function correctAnswer()
    {
        // For text_input and numeric_input, check if there's an answer with expected_answer
        if (in_array($this->question_type, ['text_input', 'numeric_input'])) {
            if ($this->expected_answer) {
                // Try to find an answer record that matches expected_answer
                $answer = $this->answers()
                    ->where('answer_text', $this->expected_answer)
                    ->where('is_correct', true)
                    ->first();
                
                if ($answer) {
                    return $answer;
                }
                
                // If no answer record exists, create a virtual answer object
                // This is a fallback - ideally answers should be created
                return (object)[
                    'id' => null,
                    'answer_text' => $this->expected_answer,
                    'is_correct' => true,
                    'order' => null,
                ];
            }
            return null;
        }
        
        // For multiple_choice and true_false, return the answer marked as correct
        return $this->answers()->where('is_correct', true)->first();
    }
}
