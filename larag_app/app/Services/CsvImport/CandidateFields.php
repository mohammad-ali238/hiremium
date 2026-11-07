<?php

namespace App\Services\CsvImport;

/**
 * The candidate fields a CSV column can be mapped to. Keys are the canonical
 * camelCase names used by ParsedResume/parsed_data (plus `location`, stored on
 * the Candidate itself); labels are what the mapping UI shows.
 */
class CandidateFields
{
    /** Field key => human label, in display order. */
    public const FIELDS = [
        'name' => 'Name',
        'jobTitle' => 'Job Title',
        'email' => 'Email',
        'phone' => 'Phone',
        'location' => 'Location',
        'gender' => 'Gender',
        'dateOfBirth' => 'Date of Birth',
        'websiteUrl' => 'Website URL',
        'linkedinUrl' => 'LinkedIn URL',
        'summary' => 'Bio / Summary',
        'experience' => 'Experiences',
        'education' => 'Educations',
        'projects' => 'Projects',
        'certificates' => 'Certificates',
        'awards' => 'Awards',
        'skills' => 'Skills',
        'expertise' => 'Expertise',
        'languages' => 'Languages',
    ];

    /** Fields whose absence only degrades match quality (warning, not a block). */
    public const QUALITY_FIELDS = ['skills', 'experience'];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    /**
     * Compulsory rule (user-confirmed): Name AND Email must be mapped, plus at
     * least one of Job Title / Bio-Summary.
     *
     * @param  array<string, ?string>  $mapping  field => column (null = unmapped)
     */
    public static function satisfiesCompulsory(array $mapping): bool
    {
        $has = fn (string $field) => ! empty($mapping[$field]);

        return $has('name') && $has('email') && ($has('jobTitle') || $has('summary'));
    }

    /**
     * Non-blocking quality warning when neither skills nor experience is mapped.
     *
     * @param  array<string, ?string>  $mapping
     */
    public static function qualityWarning(array $mapping): ?string
    {
        foreach (self::QUALITY_FIELDS as $field) {
            if (! empty($mapping[$field])) {
                return null;
            }
        }

        return 'No skills or experience column was matched — match scores may be less accurate.';
    }

    /**
     * Keep only known fields mapped to columns that actually exist in the CSV;
     * everything else becomes null (shown as "Not mapped").
     *
     * @param  array<mixed>  $mapping
     * @param  array<int, string>  $columns
     * @return array<string, ?string>
     */
    public static function sanitizeMapping(array $mapping, array $columns): array
    {
        $clean = [];
        foreach (self::keys() as $field) {
            $column = $mapping[$field] ?? null;
            $clean[$field] = (is_string($column) && in_array($column, $columns, true)) ? $column : null;
        }

        return $clean;
    }
}
