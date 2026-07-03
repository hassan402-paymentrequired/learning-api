<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamCategory;
use App\Support\PublicId;
use Illuminate\Http\Request;

class ExamCategoryController extends Controller
{
    /**
     * Get all active exam categories.
     */
    public function index()
    {
        $categories = ExamCategory::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => PublicId::collection($categories),
        ]);
    }
}
