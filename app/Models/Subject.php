<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'exam_types',
        'is_active',
        'order',
        'department_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'exam_types' => 'array',
    ];

    /**
     * Get the department that owns the subject.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the questions for the subject.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Get the tests for the subject (e.g. Test 1, Test 2 for DLI).
     */
    public function tests(): HasMany
    {
        return $this->hasMany(SubjectTest::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subject) {
            if (empty($subject->slug)) {
                $subject->slug = Str::slug($subject->name);
            }
        });
    }
}
