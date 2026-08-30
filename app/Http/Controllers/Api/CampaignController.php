<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;

class CampaignController extends Controller
{
    private const TYPES = ['marquee', 'countdown', 'popup'];

    /**
     * Get the single active campaign for each campaign type.
     */
    public function index()
    {
        $data = [];

        foreach (self::TYPES as $type) {
            $campaign = Campaign::active()
                ->where('campaign_type', $type)
                ->orderByDesc('priority')
                ->orderByDesc('created_at')
                ->first();

            $data[$type] = $campaign ? $this->transform($campaign) : null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function transform(Campaign $campaign): array
    {
        return [
            'uuid' => $campaign->uuid,
            'title' => $campaign->title,
            'message' => $campaign->message,
            'image_url' => $campaign->image
                ? asset('storage/' . ltrim($campaign->image, '/'))
                : null,
            'link' => $campaign->link,
            'link_text' => $campaign->link_text,
            'countdown_target_at' => $campaign->countdown_target_at,
            'popup_frequency_days' => $campaign->popup_frequency_days,
        ];
    }
}
