<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralRewardEarnedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Referral $referral,
        public float $amount
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You earned ₦' . number_format($this->amount, 0) . ' from a referral — ' . config('app.name'))
            ->greeting('Great news, ' . ($notifiable->name ?? 'there') . '!')
            ->line('Someone you referred just subscribed. You earned ₦' . number_format($this->amount, 0) . ' in referral credit.')
            ->line('Your available balance is updated on your Refer & Earn page.')
            ->action('View referral balance', rtrim(config('app.frontend_url', config('app.url')), '/') . '/referral');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
