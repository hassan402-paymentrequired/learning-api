<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SubscriptionPin;
use App\Models\SubscriptionSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::withCount('examAttempts')
            ->withExists(['subscriptions as has_active_subscription' => function ($q) {
                $q->where('status', 'active')->where('expires_at', '>', now());
            }]);

        // Search
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by subscription status
        if ($request->filled('subscription_status')) {
            if ($request->subscription_status === 'active') {
                $query->whereHas('subscriptions', function ($q) {
                    $q->where('status', 'active')->where('expires_at', '>', now());
                });
            } else {
                $query->whereDoesntHave('subscriptions', function ($q) {
                    $q->where('status', 'active')->where('expires_at', '>', now());
                });
            }
        }

        // Filter by registration date
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        $users->getCollection()->transform(function ($user) {
            $user->subscription_status = $user->has_active_subscription ? 'active' : 'inactive';
            // We could fetch the exact type, but for the list view, "Active" or "No Sub" is usually enough.
            // However, to keep the PIN/Manual colors, let's try to get the type if active.
            if ($user->has_active_subscription) {
                 $activeSub = $user->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->latest('expires_at')
                    ->first();
                 $user->subscription_type = $activeSub?->type;
            } else {
                 $user->subscription_type = null;
            }
            return $user;
        });

        return Inertia::render('admin/users/index', [
            'users'   => $users,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'subscription_status']),
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->loadCount('examAttempts');

        // Get user statistics
        $stats = [
            'total_attempts'     => $user->examAttempts()->count(),
            'completed_attempts' => $user->examAttempts()->where('status', 'completed')->count(),
            'average_score'      => (int)$user->examAttempts()
                ->where('status', 'completed')
                ->selectRaw('AVG((correct_answers * 100.0) / NULLIF(total_questions, 0)) as avg_score')
                ->value('avg_score') ?? 0,
            'total_time_spent'   => $user->examAttempts()
                ->where('status', 'completed')
                ->sum('time_spent'),
        ];

        // Get practice history
        $practiceHistory = $user->examAttempts()
            ->with('exam')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($attempt) {
                $examTitle = $attempt->exam?->title;
                $examType = $attempt->exam?->exam_type;

                if (!$examTitle) {
                    $subjectNames = collect($attempt->subjects ?? [])
                        ->pluck('subject')
                        ->filter()
                        ->values()
                        ->all();

                    $examTitle = !empty($subjectNames)
                        ? 'Practice: ' . implode(', ', $subjectNames)
                        : 'Practice Session';
                    $examType = $examType ?? 'Practice';
                }

                return [
                    'id'              => $attempt->id,
                    'exam_title'      => $examTitle,
                    'exam_type'       => $examType ?? 'Practice',
                    'status'          => $attempt->status,
                    'score'           => $attempt->score,
                    'correct_answers' => $attempt->correct_answers,
                    'total_questions' => $attempt->total_questions,
                    'percentage'      => $attempt->percentage,
                    'started_at'      => $attempt->started_at,
                    'completed_at'    => $attempt->completed_at,
                ];
            });

        // Get subject performance
        $subjectPerformance = $user->examAttempts()
            ->where('status', 'completed')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('exams.subject')
            ->selectRaw('AVG((exam_attempts.correct_answers * 100.0) / NULLIF(exam_attempts.total_questions, 0)) as avg_score')
            ->selectRaw('COUNT(*) as attempts')
            ->whereNotNull('exams.subject')
            ->groupBy('exams.subject')
            ->get();

        // Get subscription PINs for this user
        $subscriptionPins = $user->subscriptionPins()
            ->with('generatedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($pin) => [
                'id'           => $pin->id,
                'pin'          => $pin->pin,
                'status'       => $pin->status,
                'generated_by' => $pin->generatedBy?->name,
                'used_at'      => $pin->used_at,
                'expires_at'   => $pin->expires_at,
                'created_at'   => $pin->created_at,
            ]);

        $activeSub = $user->activeSubscription();
        return Inertia::render('admin/users/show', [
            'user'               => array_merge($user->toArray(), [
                'subscription_status'    => $activeSub ? 'active' : 'inactive',
                'subscription_type'      => $activeSub?->type ?? 'paystack',
                'subscription_expires_at'=> $activeSub?->expires_at,
            ]),
            'stats'              => $stats,
            'practiceHistory'    => $practiceHistory,
            'subjectPerformance' => $subjectPerformance,
            'subscriptionPins'   => $subscriptionPins,
            'flash'              => session('generated_pin') ? ['generated_pin' => session('generated_pin')] : null,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        return Inertia::render('admin/users/edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'is_admin' => 'boolean',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Toggle admin status of a user.
     */
    public function toggleAdmin(Request $request, User $user)
    {
        $user->update([
            'is_admin' => !$user->is_admin,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', $user->is_admin ? 'User granted admin access.' : 'User admin access revoked.');
    }

    /**
     * Generate a 6-digit subscription PIN for the user.
     */
    public function generatePin(Request $request, User $user)
    {
        // Generate a unique 6-digit PIN
        do {
            $pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (SubscriptionPin::where('pin', $pin)->where('status', 'unused')->exists());

        $expiresAt = null;
        if ($request->filled('expires_at')) {
            $expiresAt = $request->date('expires_at');
        }

        SubscriptionPin::create([
            'user_id'      => $user->id,
            'generated_by' => auth()->id(),
            'pin'          => $pin,
            'status'       => 'unused',
            'expires_at'   => $expiresAt,
            'notes'        => $request->input('notes'),
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('generated_pin', $pin)
            ->with('success', "PIN {$pin} generated successfully for {$user->name}.");
    }

    /**
     * Cancel (invalidate) a specific subscription PIN.
     */
    public function cancelPin(User $user, SubscriptionPin $pin)
    {
        if ($pin->user_id !== $user->id) {
            abort(403, 'PIN does not belong to this user.');
        }

        if ($pin->status !== 'unused') {
            return back()->withErrors(['pin' => 'Only unused PINs can be cancelled.']);
        }

        $pin->update(['status' => 'cancelled']);

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'PIN cancelled successfully.');
    }

    /**
     * Manually activate or cancel a user's subscription.
     */
    public function toggleSubscription(Request $request, User $user)
    {
        if ($user->hasActiveSubscription()) {
            // Cancel ALL active subscriptions for this user
            $user->subscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);
            $message = "{$user->name}'s active subscriptions have been cancelled.";
        } else {
            // Activate NEW subscription
            $days     = (int) SubscriptionSetting::get('default_subscription_days', 365);
            $globalExpiry = SubscriptionSetting::get('global_expiry_date');

            if ($globalExpiry) {
                $expiresAt = \Carbon\Carbon::parse($globalExpiry)->endOfDay();
            } else {
                $expiresAt = now()->addDays($days);
            }

            $subscription = $user->subscriptions()->create([
                'subscription_plan_id' => 1, // Default plan
                'status'               => 'active',
                'type'                 => 'manual',
                'starts_at'            => now(),
                'expires_at'           => $expiresAt,
                'amount_paid'          => 0,
                'original_amount'      => 0,
                'discount_amount'      => 0,
                'notes'                => 'Manually activated by admin',
            ]);

            app(\App\Services\ReferralService::class)->rewardOnSubscription($user, $subscription);
            app(\App\Services\ReferralService::class)->ensureReferralCode($user);

            $message = "{$user->name}'s manual subscription activated until {$expiresAt->toDateString()}.";
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', $message);
    }

    /**
     * Set a specific expiry date for the user's subscription.
     */
    public function setExpiry(Request $request, User $user)
    {
        $request->validate([
            'expires_at' => 'required|date|after:today',
        ]);

        $user->subscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first()
            ?->update([
                'expires_at' => \Carbon\Carbon::parse($request->expires_at)->endOfDay(),
            ]);

        // If no active, create one
        if (!$user->hasActiveSubscription()) {
            $user->subscriptions()->create([
                'subscription_plan_id' => 1,
                'status'               => 'active',
                'type'                 => 'manual',
                'starts_at'            => now(),
                'expires_at'           => \Carbon\Carbon::parse($request->expires_at)->endOfDay(),
                'amount_paid'          => 0,
                'original_amount'      => 0,
                'discount_amount'      => 0,
            ]);
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Subscription expiry updated to {$request->expires_at}.");
    }

    /**
     * Change the subscription type (paystack / pin / manual) for a user.
     */
    public function setSubscriptionType(Request $request, User $user)
    {
        $request->validate([
            'subscription_type' => 'required|in:paystack,pin,manual',
        ]);

        $user->update([
            'subscription_type' => $request->subscription_type,
        ]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "Subscription type updated to {$request->subscription_type}.");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'user' => 'You cannot delete your own account.'
            ]);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
