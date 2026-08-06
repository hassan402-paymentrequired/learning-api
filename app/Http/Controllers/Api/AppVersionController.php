<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileAppVersionService;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function __construct(
        private readonly MobileAppVersionService $mobileAppVersionService
    ) {}

    /**
     * Public mobile version policy (used on app launch).
     */
    public function show(Request $request)
    {
        $config = $this->mobileAppVersionService->publicConfig();
        $client = $this->mobileAppVersionService->resolveFromRequest($request);

        $data = $config;

        if ($client) {
            $data['update_required'] = $client['update_required'];
            $data['client_version'] = $client['version'];
            $data['min_version'] = $client['min_version'];
            $data['store_url'] = $client['store_url'];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
