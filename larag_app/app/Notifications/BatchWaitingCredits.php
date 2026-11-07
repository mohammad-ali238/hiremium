<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once when a batch has processed everything the workspace could afford
 * and the remaining CVs are waiting on credits. Deliberately NOT a "finished"
 * or "failed" message — the CVs are fine; the user just needs to top up and
 * press "Resume waiting on credits" on the batch page.
 */
class BatchWaitingCredits extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $batchId,
        public string $jobTitle,
        public int $analyzed,
        public int $waiting,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Batch paused — {$this->waiting} CV(s) waiting on credits · {$this->jobTitle}")
            ->line("Your resume batch for \"{$this->jobTitle}\" analyzed {$this->analyzed} CV(s) and then ran out of credits.")
            ->line("{$this->waiting} CV(s) are waiting — nothing is wrong with them. Top up your credits, then open the batch and press \"Resume waiting on credits\".")
            ->action('Open batch', route('app.batches.show', $this->batchId));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Batch paused: waiting on credits — '.$this->jobTitle,
            'message' => "{$this->analyzed} analyzed; {$this->waiting} waiting on credits. Top up, then resume from the batch page.",
            'url' => route('app.batches.show', $this->batchId),
        ];
    }
}
