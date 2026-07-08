<?php

namespace App\Notifications;

use App\Models\ReferralWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralWithdrawalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReferralWithdrawal $withdrawal)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->withdrawal->user;
        $adminUrl = url('/admin/referral-withdrawals');

        return (new MailMessage)
            ->subject('New referral withdrawal request — ' . config('app.name'))
            ->greeting('New withdrawal request')
            ->line("{$user->name} ({$user->email}) requested a referral payout.")
            ->line('Amount: ₦' . number_format((float) $this->withdrawal->amount, 0))
            ->line('Phone: ' . $this->withdrawal->phone_number . ' (' . strtoupper($this->withdrawal->network) . ')')
            ->action('Review in admin', $adminUrl);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
