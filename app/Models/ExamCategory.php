<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_name',
        'flow_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
