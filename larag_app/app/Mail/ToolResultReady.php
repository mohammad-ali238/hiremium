<?php

namespace App\Mail;

use App\Models\ToolRun;
use App\Services\Tools\Contracts\ToolDefinition;
use Illuminate\Bus\Queueable;
use App\Support\ToolSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one email a free-tool visitor gets: the link to their result.
 *
 * Opening it is what confirms the address — nothing is processed until then,
 * which is also why the link is the whole message.
 */
class ToolResultReady extends Mailable
{
    use Queueable, SerializesModels;

    public string $toolName;

    public string $resultUrl;

    public int $expiryHours;

    public function __construct(
        public ToolRun $run,
        ToolDefinition $tool,
    ) {
        $this->toolName = $tool->name();
        $this->resultUrl = $run->publicUrl();
        $this->expiryHours = $run->hoursUntilExpiry() ?? 0;

        // An admin subject wins; empty falls back to the tool's own line.
        $this->subject = ToolSettings::emailSubject($tool->slug()) ?: $tool->emailSubject();
    }

    public function envelope(): Envelope
    {
        $fromName = ToolSettings::emailFromName();

        return new Envelope(
            from: $fromName === ''
                ? null
                : new Address((string) config('mail.from.address'), $fromName),
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tool-result-ready');
    }
}
