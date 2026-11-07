<?php

namespace App\Http\Requests;

use App\Rules\Recaptcha;
use App\Support\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    /**
     * Selectable contact topics (value => label). Shared by the form and the
     * validation whitelist.
     *
     * @return array<string, string>
     */
    public static function topics(): array
    {
        return [
            'technical_issue' => 'Technical issue',
            'billing' => 'Billing',
            'feature_request' => 'Feature request',
            'api_issue' => 'API issue',
            'bug_report' => 'Bug report',
            'account' => 'Account',
            'other' => 'Other',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'topic' => ['required', 'string', 'in:'.implode(',', array_keys(self::topics()))],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ];

        if (SiteSettings::contactRecaptchaEnabled()) {
            $rules['g-recaptcha-response'] = ['required', new Recaptcha];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'Please confirm you are not a robot.',
            'topic.in' => 'Please choose a valid topic.',
        ];
    }

    /** The human-readable label for the submitted topic. */
    public function topicLabel(): string
    {
        return self::topics()[$this->validated('topic')] ?? 'General';
    }
}
