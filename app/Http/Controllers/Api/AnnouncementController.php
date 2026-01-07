<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Get active announcements.
     */
    public function index(Request $request)
    {
        $announcements = Announcement::active()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'type' => $announcement->type,
                    'link' => $announcement->link,
                    'link_text' => $announcement->link_text,
                    'created_at' => $announcement->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }
}
