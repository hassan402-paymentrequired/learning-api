<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Check if user is authenticated and email is not verified
        if ($user && !$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before accessing this resource.',
                'data' => [
                    'email_verified' => false,
                    'email' => $user->email,
                ],
            ], 403);
        }

        return $next($request);
    }
}
