<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingReengagementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $unsubscribeUrl,
        public string $preferencesUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We miss you at ' . config('app.name'))
            ->view('emails.marketing.reengagement', [
                'user' => $notifiable,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'preferencesUrl' => $this->preferencesUrl,
                'showFooter' => false,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
