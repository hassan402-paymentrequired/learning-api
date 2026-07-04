<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
        public int $daysRemaining
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->daysRemaining === 1
            ? 'Your ' . config('app.name') . ' subscription expires tomorrow'
            : 'Your ' . config('app.name') . ' subscription expires in 7 days';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.subscription-expiry-reminder', [
                'user' => $notifiable,
                'subscription' => $this->subscription,
                'daysRemaining' => $this->daysRemaining,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
