<?php

namespace App\Notifications;

use App\Models\ReferralWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralWithdrawalStatusNotification extends Notification implements ShouldQueue
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
        $amount = number_format((float) $this->withdrawal->amount, 0);

        if ($this->withdrawal->status === 'paid') {
            return (new MailMessage)
                ->subject('Your referral withdrawal was paid — ' . config('app.name'))
                ->greeting('Payment sent')
                ->line("Your withdrawal of ₦{$amount} has been marked as paid.")
                ->line('Paid to: ' . ($this->withdrawal->account_name ?: 'your bank account'))
                ->line(($this->withdrawal->bank_name ?: 'Bank') . ' · ' . ($this->withdrawal->account_number ?: 'N/A'))
                ->action('View referral page', rtrim(config('app.frontend_url', config('app.url')), '/') . '/referral');
        }

        $message = (new MailMessage)
            ->subject('Referral withdrawal update — ' . config('app.name'))
            ->greeting('Withdrawal update')
            ->line("Your withdrawal request of ₦{$amount} was not approved.");

        if ($this->withdrawal->admin_notes) {
            $message->line('Reason: ' . $this->withdrawal->admin_notes);
        }

        $message->line('The amount has been returned to your available balance.')
            ->action('View referral page', rtrim(config('app.frontend_url', config('app.url')), '/') . '/referral');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
