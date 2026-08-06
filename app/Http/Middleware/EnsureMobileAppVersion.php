<?php

namespace App\Http\Middleware;

use App\Services\MobileAppVersionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAppVersion
{
    public function __construct(
        private readonly MobileAppVersionService $mobileAppVersionService
    ) {}

    /**
     * Block outdated native app builds (mobile sends X-App-Platform + X-App-Version).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/app-version', 'app-version')) {
            return $next($request);
        }

        $client = $this->mobileAppVersionService->resolveFromRequest($request);

        if ($client && $client['update_required']) {
            return response()->json([
                'success' => false,
                'message' => config('mobile_app.message'),
                'data' => [
                    'update_required' => true,
                    'min_version' => $client['min_version'],
                    'store_url' => $client['store_url'],
                ],
            ], 426);
        }

        return $next($request);
    }
}
