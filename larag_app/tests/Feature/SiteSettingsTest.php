<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_open_registration_and_captcha_off(): void
    {
        $this->assertTrue(SiteSettings::registrationEnabled());
        $this->assertFalse(SiteSettings::recaptchaEnabled());
    }

    public function test_settings_roundtrip(): void
    {
        SiteSettings::setRegistrationEnabled(false);
        SiteSettings::setRecaptchaEnabled(true);
        SiteSettings::setRecaptchaSiteKey('site-key-123');
        SiteSettings::setRecaptchaSecretKey('secret-key-456');

        $this->assertFalse(SiteSettings::registrationEnabled());
        $this->assertTrue(SiteSettings::recaptchaEnabled());
        $this->assertSame('site-key-123', SiteSettings::recaptchaSiteKey());
        $this->assertSame('secret-key-456', SiteSettings::recaptchaSecretKey());
    }

    public function test_recaptcha_enabled_requires_both_keys(): void
    {
        SiteSettings::setRecaptchaEnabled(true);
        SiteSettings::setRecaptchaSiteKey('site-key-only');
        // secret key intentionally left empty

        $this->assertFalse(SiteSettings::recaptchaEnabled(), 'toggle on but missing secret should read as disabled');
    }

    public function test_secret_key_is_encrypted_at_rest(): void
    {
        SiteSettings::setRecaptchaSecretKey('super-secret-value');

        $raw = Setting::where('key', SiteSettings::SETTINGS_KEY)->first()->value;

        $this->assertArrayHasKey('recaptcha_secret_key', $raw);
        $this->assertNotSame('super-secret-value', $raw['recaptcha_secret_key']);
        $this->assertStringNotContainsString('super-secret-value', $raw['recaptcha_secret_key']);
    }

    public function test_all_settings_stored_in_single_row(): void
    {
        SiteSettings::setRegistrationEnabled(true);
        SiteSettings::setRecaptchaEnabled(false);
        SiteSettings::setRecaptchaSiteKey('k');

        $this->assertSame(1, Setting::count());
    }
}
