<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitlistSignup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'platform' => 'required|in:ios,android',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        WaitlistSignup::updateOrCreate(
            [
                'email' => strtolower($request->email),
                'platform' => $request->platform,
            ],
            []
        );

        return response()->json([
            'success' => true,
            'message' => 'You are on the waitlist. We will notify you when the app launches.',
        ]);
    }
}
