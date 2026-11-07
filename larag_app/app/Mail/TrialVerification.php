<?php

namespace App\Mail;

use App\Models\TrialCredit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Verification email for a free-trial AI credits claim.
 */
class TrialVerification extends Mailable
{
    use Queueable, SerializesModels;

    public int $credits;

    public int $days;

    public function __construct(
        public TrialCredit $trial,
        public string $verifyUrl,
    ) {
        $this->credits = (int) config('trial.credits', TrialCredit::DEFAULT_CREDITS);
        $this->days = (int) config('trial.days', TrialCredit::DEFAULT_DAYS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your email to unlock free AI credits for Workeera',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trial-verification');
    }
}
