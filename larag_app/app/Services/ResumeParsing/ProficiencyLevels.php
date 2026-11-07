<?php

namespace App\Services\ResumeParsing;

/**
 * The 1-5 proficiency scale a parsed resume stores, and the words behind it.
 *
 * The parser is told these bands in the parsing prompt and stores the integer;
 * everything that displays one reads its label from here, so the prompt and the
 * screen can never drift apart. ParsedResume::normalizeLevel() maps the same
 * words back the other way when a model answers "Fluent" instead of 4.
 */
class ProficiencyLevels
{
    public const MAX = 5;

    /** Skill depth. */
    public const EXPERTISE = [
        1 => 'Beginner',
        2 => 'Elementary',
        3 => 'Intermediate',
        4 => 'Advanced',
        5 => 'Expert',
    ];

    /** Human languages — the same scale, different words. */
    public const LANGUAGE = [
        1 => 'Beginner',
        2 => 'Elementary',
        3 => 'Conversational',
        4 => 'Fluent',
        5 => 'Native',
    ];

    /**
     * How an expertise level is shown: "4/5".
     *
     * A bare "4" reads as a count of something. The denominator is what makes
     * it a rating.
     */
    public static function expertise(int $level): string
    {
        return self::isValid($level) ? $level.'/'.self::MAX : '';
    }

    /**
     * How a language level is shown: "Native".
     *
     * Nobody describes their Spanish as 5/5, so languages get the word rather
     * than the number.
     */
    public static function language(int $level): string
    {
        return self::LANGUAGE[$level] ?? '';
    }

    /** The word behind an expertise number, for a tooltip. */
    public static function expertiseLabel(int $level): string
    {
        return self::EXPERTISE[$level] ?? '';
    }

    /** 0 means the resume never said, and nothing should be shown. */
    private static function isValid(int $level): bool
    {
        return $level >= 1 && $level <= self::MAX;
    }
}
