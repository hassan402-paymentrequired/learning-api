<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingStudyTipNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $tip
     */
    public function __construct(
        public array $tip,
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
            ->subject('Study tip from ' . config('app.name'))
            ->view('emails.marketing.study-tip', [
                'user' => $notifiable,
                'tip' => $this->tip,
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
