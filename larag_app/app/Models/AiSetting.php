<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'openai_api_key', 'gemini_api_key', 'byo_keys_enabled', 'default_provider',
    'default_model', 'system_prompt', 'temperature', 'max_tokens',
    'enforce_min_experience', 'min_experience_cap_score',
    'enforce_must_have_skills', 'must_have_cap_score', 'must_have_soft_cap_min_skills', 'must_have_soft_cap_score',
    'enforce_career_gap', 'career_gap_cap_score',
    'parsing_ai_provider', 'parsing_openai_model', 'parsing_gemini_model',
    'parsing_system_prompt', 'parsing_temperature',
    'parsing_credit_cost_text', 'parsing_credit_cost_vision',
    'parsing_max_file_size_kb',
    'trial_credit_cost_match', 'trial_credit_cost_parse_text', 'trial_credit_cost_parse_vision',
])]
class AiSetting extends Model
{
    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_GEMINI = 'gemini';

    protected function casts(): array
    {
        return [
            'openai_api_key' => 'encrypted',
            'gemini_api_key' => 'encrypted',
            'byo_keys_enabled' => 'boolean',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'enforce_min_experience' => 'boolean',
            'min_experience_cap_score' => 'integer',
            'enforce_must_have_skills' => 'boolean',
            'must_have_cap_score' => 'integer',
            'must_have_soft_cap_min_skills' => 'integer',
            'must_have_soft_cap_score' => 'integer',
            'enforce_career_gap' => 'boolean',
            'career_gap_cap_score' => 'integer',
            'parsing_temperature' => 'decimal:2',
            'parsing_credit_cost_text' => 'integer',
            'parsing_credit_cost_vision' => 'integer',
            'parsing_max_file_size_kb' => 'integer',
            'trial_credit_cost_match' => 'integer',
            'trial_credit_cost_parse_text' => 'integer',
            'trial_credit_cost_parse_vision' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'default_provider' => self::PROVIDER_OPENAI,
            'default_model' => 'gpt-4o-mini',
            'system_prompt' => self::defaultSystemPrompt(),
            'temperature' => 0.20,
            'max_tokens' => 800,
            'parsing_ai_provider' => self::PROVIDER_OPENAI,
            'parsing_openai_model' => 'gpt-4o-mini',
            'parsing_gemini_model' => 'gemini-1.5-flash',
            'parsing_system_prompt' => self::defaultParsingPrompt(),
            'parsing_temperature' => 0.10,
            'parsing_credit_cost_text' => 2,
            'parsing_credit_cost_vision' => 5,
            'parsing_max_file_size_kb' => 5120,
            'trial_credit_cost_match' => 1,
            'trial_credit_cost_parse_text' => 1,
            'trial_credit_cost_parse_vision' => 1,
        ]);
    }

    /**
     * Seed value for the legacy `system_prompt` column (the table requires it).
     *
     * The matching prompt is no longer configurable: every lane sends
     * App\Services\Ai\MatchPrompt, which is fixed in code because the rubric in
     * App\Services\Matching\MatchRubric depends on the exact findings it asks
     * for. Nothing reads this column any more.
     *
     * @deprecated
     */
    public static function defaultSystemPrompt(): string
    {
        return 'Not in use — the matching prompt is fixed in code (App\Services\Ai\MatchPrompt).';
    }

    public static function defaultParsingPrompt(): string
    {
        return <<<'PROMPT'
You are a precise resume parser. Read the resume content provided (text or image/PDF) and extract structured candidate data.

Return ONLY a JSON object with exactly these keys (camelCase). If a field is not present, return an empty string, empty array, or 0 for numeric levels. Do not invent data.

Schema:
{
  "fullName": "string",
  "email": "string",
  "phone": "string",
  "jobTitle": "string",
  "gender": "string",
  "dateOfBirth": "YYYY-MM-DD",
  "websiteUrl": "string",
  "linkedinUrl": "string",
  "summary": "string",
  "experience": [
    {
      "company": "string",
      "title": "string",
      "location": "string",
      "startDate": "YYYY-MM",
      "endDate": "YYYY-MM or 'Present'",
      "description": "string"
    }
  ],
  "education": [
    {
      "institution": "string",
      "degree": "string",
      "field": "string",
      "location": "string",
      "startDate": "YYYY-MM",
      "endDate": "YYYY-MM or 'Present'",
      "grade": "string"
    }
  ],
  "projects": [
    {
      "name": "string",
      "role": "string",
      "url": "string",
      "startDate": "YYYY-MM",
      "endDate": "YYYY-MM or 'Present'",
      "description": "string"
    }
  ],
  "certificates": [
    {
      "name": "string",
      "issuer": "string",
      "issueDate": "YYYY-MM",
      "expiryDate": "YYYY-MM",
      "credentialId": "string",
      "url": "string"
    }
  ],
  "awards": [
    {
      "title": "string",
      "issuer": "string",
      "date": "YYYY-MM",
      "description": "string"
    }
  ],
  "expertise": [
    {
      "name": "string",
      "level": 1
    }
  ],
  "languages": [
    {
      "name": "string",
      "level": 1
    }
  ],
  "skills": ["string"],
  "confidence": 0.95
}

Rules:
- Dates: always YYYY-MM. If only a year is given, use YYYY-01. If unknown, empty string.
- Use "Present" (capitalised) as endDate for ongoing roles, studies, or projects.
- description fields (experience, projects, awards): include the COMPLETE original text for that item — every bullet point, responsibility and achievement, word for word. NEVER summarise, shorten, or drop lines. Put each bullet point on its own line prefixed with "- "; keep plain sentences as their own lines.
- projects: extract EVERY project mentioned anywhere in the resume — a dedicated projects section, a portfolio list, or projects described inside experience entries. Do not skip any project.
- jobTitle is the candidate's most recent or current title.
- gender: ONLY if explicitly stated in the resume (e.g. "Male", "Female"). Never guess from the name or photo. Empty string when not stated.
- dateOfBirth: ONLY if explicitly stated. Format YYYY-MM-DD; if only month or year is given, use YYYY-MM or YYYY. Empty string when not stated.
- websiteUrl: the candidate's personal website or portfolio URL if present.
- linkedinUrl: the candidate's LinkedIn profile URL if present (full URL, e.g. https://linkedin.com/in/...).
- summary is the candidate's professional summary or objective if present, otherwise empty.
- education.grade: the final grade, GPA, or classification if stated (e.g. "3.8 GPA", "First Class"), otherwise empty string.
- certificates: professional certifications/licences. credentialId and url only when explicitly stated.
- awards: honours, prizes, and recognitions.
- expertise: derive UP TO 5 core areas of expertise AUTOMATICALLY by analysing the resume content and skills (e.g. "Backend Development", "Project Management"). Assign each a proficiency level as an integer from 1 to 5 (1=Beginner, 2=Elementary, 3=Intermediate, 4=Advanced, 5=Expert) judged from seniority, years of experience, and depth of usage. If no expertise can be confidently detected, return an empty array. Never more than 5 items.
- languages: HUMAN spoken/written languages ONLY (e.g. English, Spanish, Urdu) with proficiency level as an integer from 1 to 5 (1=Beginner, 2=Elementary, 3=Conversational, 4=Fluent, 5=Native). If proficiency is not stated, estimate conservatively or use 0. NEVER list programming languages, frameworks, or tools here — Python, PHP, JavaScript and the like belong in skills/expertise, not languages.
- skills: a flat array of skill keyword strings, exactly as found in the resume.
- confidence: your own certainty that you read this document correctly and extracted its data accurately, as a number between 0 and 1 with at most 2 decimals (e.g. 0.9, 0.8, 0.75). Judge it from the source itself: 0.95-1 = clean machine-readable text with every section clear; 0.75-0.94 = readable with some ambiguity (odd layout, a few unclear dates or sections); below 0.75 = scanned, blurred, partly unreadable, or so sparse that key details had to be left empty. Never report high confidence for a document you could not actually read.
- Return valid JSON only — no commentary, no markdown.
PROMPT;
    }
}
