<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'topic' => 'billing',
            'subject' => 'Invoice question',
            'message' => 'I have a question about my last invoice, thanks.',
        ], $overrides);
    }

    public function test_valid_submission_sends_mail_to_configured_address(): void
    {
        Mail::fake();
        SiteSettings::setContactSendToEmail('support@hiremium.com');

        $this->postJson('/contact', $this->payload())
            ->assertOk()
            ->assertJson(['ok' => true]);

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo('support@hiremium.com')
                && $mail->envelope()->subject === 'Contact Query Topic: Billing - "Invoice question"'
                && $mail->hasReplyTo('jane@example.com');
        });
    }

    public function test_validation_errors_return_422_json(): void
    {
        Mail::fake();

        $this->postJson('/contact', $this->payload([
            'name' => '', 'email' => 'not-an-email', 'topic' => 'nonsense', 'subject' => '', 'message' => 'short',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'topic', 'subject', 'message']);

        Mail::assertNothingSent();
    }

    public function test_max_length_is_enforced(): void
    {
        Mail::fake();

        $this->postJson('/contact', $this->payload([
            'name' => str_repeat('a', 101),
            'subject' => str_repeat('b', 151),
            'message' => str_repeat('c', 3001),
        ]))->assertStatus(422)->assertJsonValidationErrors(['name', 'subject', 'message']);
    }

    public function test_rate_limited_after_three_per_minute(): void
    {
        Mail::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/contact', $this->payload())->assertOk();
        }

        $this->postJson('/contact', $this->payload())->assertStatus(429);
    }
}
