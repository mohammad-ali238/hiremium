<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to an invited address (an on-demand, mail-only notification — the
 * recipient has no account yet).
 */
class TeamInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $workspaceName,
        public string $role,
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->workspaceName}")
            ->line("You've been invited to join {$this->workspaceName} as {$this->role}.")
            ->action('Accept invite', route('invite.show', $this->token))
            ->line('This invite link expires in 7 days.');
    }
}
