<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A resume batch finished processing. In-app always; email when the user opted
 * in (notification_prefs.batch).
 */
class BatchCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $batchId,
        public string $jobTitle,
        public int $analyzed,
        public int $failed,
        public string $status,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notification_prefs['batch'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Resume batch finished — '.$this->jobTitle)
            ->line("Your resume batch for \"{$this->jobTitle}\" has finished processing.")
            ->line("{$this->analyzed} analyzed · {$this->failed} failed.")
            ->action('View candidates', route('app.candidates'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'batch_completed',
            'title' => 'Batch finished: '.$this->jobTitle,
            'message' => "{$this->analyzed} analyzed, {$this->failed} failed.",
            'url' => route('app.batches.show', $this->batchId),
        ];
    }
}
