<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MatchingScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job' => ['required', 'array'],
            'job.title' => ['required', 'string', 'max:255'],
            'job.description' => ['required', 'string', 'max:20000'],
            'job.requirements' => ['nullable', 'string', 'max:10000'],
            'job.skills' => ['nullable', 'array'],
            'job.skills.*' => ['string', 'max:100'],

            'application' => ['array', 'required_without:resume_file'],
            'application.name' => ['nullable', 'string', 'max:255'],
            'application.email' => ['nullable', 'email', 'max:255'],
            'application.resume_text' => ['nullable', 'required_without:resume_file', 'string', 'max:30000'],
            'application.cover_letter' => ['nullable', 'string', 'max:10000'],
            'application.experience_years' => ['nullable', 'numeric', 'min:0', 'max:80'],
            'application.skills' => ['nullable', 'array'],
            'application.skills.*' => ['string', 'max:100'],

            // Optional CV file; its extracted text is appended to the AI prompt. 3 MB cap.
            'resume_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,txt', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'application.resume_text.required_without' => 'Provide application.resume_text or attach a resume_file.',
            'resume_file.mimes' => 'The resume file must be a PDF, DOC, DOCX, or TXT.',
            'resume_file.max' => 'The resume file must not exceed 3 MB.',
        ];
    }

    public function jobText(): string
    {
        $job = $this->validated('job');
        $parts = [
            'Title: '.$job['title'],
            'Description: '.$job['description'],
        ];
        if (! empty($job['requirements'])) {
            $parts[] = 'Requirements: '.$job['requirements'];
        }
        if (! empty($job['skills'])) {
            $parts[] = 'Required skills: '.implode(', ', $job['skills']);
        }

        return implode("\n", $parts);
    }

    public function applicationText(?string $resumeFileText = null): string
    {
        $app = $this->validated('application') ?? [];
        $parts = [];
        if (! empty($app['name'])) {
            $parts[] = 'Candidate: '.$app['name'];
        }
        if (isset($app['experience_years'])) {
            $parts[] = 'Experience years: '.$app['experience_years'];
        }
        if (! empty($app['skills'])) {
            $parts[] = 'Candidate skills: '.implode(', ', $app['skills']);
        }
        if (! empty($app['resume_text'])) {
            $parts[] = 'Resume: '.$app['resume_text'];
        }
        if (! empty($app['cover_letter'])) {
            $parts[] = 'Cover letter: '.$app['cover_letter'];
        }
        if ($resumeFileText !== null && $resumeFileText !== '') {
            $parts[] = 'Resume file content: '.$resumeFileText;
        }

        return implode("\n", $parts);
    }

    /**
     * Validated payload made safe for usage-log storage: the UploadedFile
     * object is replaced with file metadata plus the extracted text.
     */
    public function logPayload(?string $resumeFileText = null): array
    {
        $payload = $this->validated();
        unset($payload['resume_file']);

        if ($this->hasFile('resume_file')) {
            $file = $this->file('resume_file');
            $payload['resume_file'] = [
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
            if ($resumeFileText !== null) {
                $payload['resume_file_text'] = $resumeFileText;
            }
        }

        return $payload;
    }
}
