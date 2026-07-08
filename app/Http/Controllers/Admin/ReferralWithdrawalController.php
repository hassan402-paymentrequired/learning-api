<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralWithdrawal;
use App\Notifications\ReferralWithdrawalStatusNotification;
use App\Services\UserPushNotifier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralWithdrawalController extends Controller
{
    public function __construct(private UserPushNotifier $pushNotifier)
    {
    }

    public function index(Request $request)
    {
        $query = ReferralWithdrawal::with(['user:id,name,email', 'processor:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $pendingCount = ReferralWithdrawal::where('status', 'pending')->count();

        return Inertia::render('admin/referral-withdrawals/index', [
            'withdrawals' => $withdrawals,
            'pendingCount' => $pendingCount,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function update(Request $request, ReferralWithdrawal $referralWithdrawal)
    {
        $request->validate([
            'status' => 'required|in:paid,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if (!$referralWithdrawal->isPending()) {
            return back()->withErrors(['status' => 'Only pending withdrawals can be updated.']);
        }

        $referralWithdrawal->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        $referralWithdrawal->load('user');
        $referralWithdrawal->user->notify(new ReferralWithdrawalStatusNotification($referralWithdrawal));

        $amount = number_format((float) $referralWithdrawal->amount, 0);

        if ($referralWithdrawal->status === 'paid') {
            $this->pushNotifier->send($referralWithdrawal->user, [
                'title' => 'Withdrawal paid',
                'body' => "Your referral withdrawal of ₦{$amount} has been paid.",
                'url' => '/referral',
            ]);
        } else {
            $this->pushNotifier->send($referralWithdrawal->user, [
                'title' => 'Withdrawal update',
                'body' => "Your referral withdrawal of ₦{$amount} was rejected. Funds returned to your balance.",
                'url' => '/referral',
            ]);
        }

        return back()->with('success', 'Withdrawal status updated successfully.');
    }
}
