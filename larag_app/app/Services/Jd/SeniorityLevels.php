<?php

namespace App\Services\Jd;

/**
 * The seniority ladder a job description is analysed against.
 *
 * Lives here rather than on the tool because the analyser needs the human label
 * for the prompt, and the tool needs the same list for its dropdown — one
 * source so the two can never drift.
 */
class SeniorityLevels
{
    /** value => label, in ladder order. */
    public const LEVELS = [
        'intern' => 'Intern',
        'junior' => 'Junior',
        'mid_level' => 'Mid-level',
        'senior' => 'Senior',
        'lead' => 'Lead',
        'manager' => 'Manager',
        'director' => 'Director',
        'vp' => 'VP',
        'executive' => 'Executive (C-level)',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::LEVELS;
    }

    public static function isValid(string $value): bool
    {
        return array_key_exists($value, self::LEVELS);
    }

    /** The label the AI is told to judge against; falls back to the raw value. */
    public static function label(string $value): string
    {
        return self::LEVELS[$value] ?? $value;
    }
}
