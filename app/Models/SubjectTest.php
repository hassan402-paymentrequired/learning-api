<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SubjectTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'name',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Get the subject that owns the test.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the questions that belong to this test (many-to-many).
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_subject_test')
            ->withTimestamps();
    }
}
