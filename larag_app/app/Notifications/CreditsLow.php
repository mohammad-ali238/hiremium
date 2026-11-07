<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The workspace credit balance is running low. In-app always; email when the
 * user opted in (notification_prefs.credits).
 */
class CreditsLow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $remaining) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notification_prefs['credits'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your credits are running low')
            ->line("You have {$this->remaining} AI credits left.")
            ->line('Top up or upgrade to keep screening candidates.')
            ->action('Manage credits', route('app.usage'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credits_low',
            'title' => 'Credits running low',
            'message' => "You have {$this->remaining} credits left.",
            'url' => route('app.usage'),
        ];
    }
}
