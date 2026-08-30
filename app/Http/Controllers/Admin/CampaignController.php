<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = Campaign::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('campaign_type')) {
            $query->where('campaign_type', $request->campaign_type);
        }

        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $campaigns = $query->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/campaigns/index', [
            'campaigns' => $campaigns,
            'filters' => $request->only(['search', 'campaign_type', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/campaigns/create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCampaign($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('campaigns', 'public');
        }

        Campaign::create($validated);

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function edit(Campaign $campaign)
    {
        return Inertia::render('admin/campaigns/edit', [
            'campaign' => $campaign,
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $validated = $this->validateCampaign($request);

        if ($request->hasFile('image')) {
            if ($campaign->image) {
                Storage::disk('public')->delete($campaign->image);
            }
            $validated['image'] = $request->file('image')->store('campaigns', 'public');
        } else {
            $validated['image'] = $campaign->image;
        }

        $campaign->update($validated);

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    public function toggleActive(Campaign $campaign)
    {
        $campaign->update([
            'is_active' => !$campaign->is_active,
        ]);

        return redirect()->back()
            ->with('success', 'Campaign status updated successfully.');
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->image) {
            Storage::disk('public')->delete($campaign->image);
        }

        $campaign->delete();

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'campaign_type' => 'required|in:marquee,countdown,popup',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'link' => 'nullable|string|max:255',
            'link_text' => 'nullable|string|max:255',
            'countdown_target_at' => 'required_if:campaign_type,countdown|nullable|date',
            'popup_frequency_days' => 'required_if:campaign_type,popup|nullable|integer|min:1',
            'is_active' => 'boolean',
            'priority' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
    }
}
