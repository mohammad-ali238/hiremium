<?php

namespace App\Services\CsvImport;

/**
 * Deterministic header-name mapper: matches CSV column names to candidate
 * fields using a synonym table. Used instead of the AI call when testing mode
 * is on, and as the fallback when the AI call fails — never a dead end.
 */
class HeuristicMapper
{
    /** field => regex fragments matched (case-insensitive) against a normalized header. */
    private const SYNONYMS = [
        'name' => ['full ?name', 'candidate( name)?', 'applicant( name)?', '^name$', 'contact name', 'person'],
        'jobTitle' => ['job ?title', 'designation', 'position', '^role$', 'current (title|role|position)', 'profession', '^title$'],
        'email' => ['e ?mail( address)?', '^mail$'],
        'phone' => ['phone( number)?', 'mobile', 'cell', 'telephone', 'contact number', 'whatsapp'],
        'location' => ['location', '^city$', '^country$', 'address', '^state$', 'region'],
        'gender' => ['gender', '^sex$'],
        'dateOfBirth' => ['date of birth', '^dob$', 'birth ?date', 'birthday'],
        'websiteUrl' => ['website', 'portfolio', 'personal (site|url)', '^url$', 'web ?site url', 'homepage'],
        'linkedinUrl' => ['linked ?in'],
        'summary' => ['^bio(graphy)?$', 'summary', 'about( me)?', 'profile', 'objective', 'description', 'overview'],
        'experience' => ['experiences?(?! years)', 'work history', 'employment', 'career', 'past (roles|jobs)'],
        'education' => ['educations?', 'qualifications?', 'academic', 'degrees?', 'schooling'],
        'projects' => ['projects?'],
        'certificates' => ['certificat(es?|ions?)', 'courses?', 'licen[cs]es?'],
        'awards' => ['awards?', 'achievements?', 'hono(u)?rs?'],
        'skills' => ['skills?', 'technologies', 'tech stack', 'competencies', 'keywords'],
        'expertise' => ['expertise', 'specialt(y|ies)', 'specializations?'],
        'languages' => ['languages?( spoken)?'],
    ];

    /**
     * @param  array<int, string>  $headers
     * @return array<string, ?string> field => column name (null when no match)
     */
    public function map(array $headers): array
    {
        $mapping = array_fill_keys(CandidateFields::keys(), null);
        $used = [];

        foreach (self::SYNONYMS as $field => $patterns) {
            foreach ($patterns as $pattern) {
                foreach ($headers as $header) {
                    if (isset($used[$header]) || trim($header) === '') {
                        continue;
                    }
                    $normalized = preg_replace('/[\s_\-]+/', ' ', mb_strtolower(trim($header)));
                    if (preg_match('/'.$pattern.'/i', $normalized)) {
                        $mapping[$field] = $header;
                        $used[$header] = true;

                        continue 3; // next field
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Headerless CSVs have no column names to match, so detect fields from the
     * DATA instead: scan each column's sample values for recognizable patterns
     * (emails, phones, URLs, people names, long free text). Returns field =>
     * 0-based column index. Used in testing mode and as the AI-failure
     * fallback for files without a header row.
     *
     * @param  array<int, array<int, string>>  $samples  sample data rows
     * @return array<string, ?int>
     */
    public function mapByContent(array $samples): array
    {
        $positions = array_fill_keys(CandidateFields::keys(), null);
        if ($samples === []) {
            return $positions;
        }

        $columnCount = max(array_map('count', $samples));
        $used = [];
        $claim = function (string $field, int $i) use (&$positions, &$used): void {
            if ($positions[$field] === null && ! isset($used[$i])) {
                $positions[$field] = $i;
                $used[$i] = true;
            }
        };

        // Share of a column's non-empty sample values matching a predicate.
        $share = function (int $i, callable $test) use ($samples): float {
            $hits = 0;
            $total = 0;
            foreach ($samples as $row) {
                $v = trim((string) ($row[$i] ?? ''));
                if ($v === '') {
                    continue;
                }
                $total++;
                if ($test($v)) {
                    $hits++;
                }
            }

            return $total > 0 ? $hits / $total : 0.0;
        };

        // Unambiguous patterns first, then softer ones on the leftovers.
        for ($i = 0; $i < $columnCount; $i++) {
            if ($share($i, fn ($v) => (bool) filter_var($v, FILTER_VALIDATE_EMAIL)) >= 0.6) {
                $claim('email', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            if ($share($i, fn ($v) => (bool) preg_match('/linkedin\.com/i', $v)) >= 0.6) {
                $claim('linkedinUrl', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            if ($share($i, fn ($v) => (bool) preg_match('~^(https?://|www\.)~i', $v)) >= 0.6) {
                $claim('websiteUrl', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            // Phone: mostly digits/punctuation with at least 7 digits.
            if ($share($i, fn ($v) => preg_match('/^[+()\d][\d\s().\/-]{5,}$/', $v) && preg_match_all('/\d/', $v) >= 7) >= 0.6) {
                $claim('phone', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            // Person name: 2-4 capitalized words, letters only.
            if ($share($i, fn ($v) => (bool) preg_match('/^\p{Lu}[\p{L}\'-]+(\s+\p{Lu}[\p{L}\'-]+){1,3}$/u', $v)) >= 0.6) {
                $claim('name', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            // Skills list: short delimiter-separated tokens.
            if ($share($i, fn ($v) => (str_contains($v, ';') || substr_count($v, ',') >= 1) && mb_strlen($v) <= 120 && str_word_count($v) <= 15) >= 0.6) {
                $claim('skills', $i);
            }
        }
        for ($i = 0; $i < $columnCount; $i++) {
            // Long free text → bio/summary.
            if ($share($i, fn ($v) => mb_strlen($v) >= 40 && str_word_count($v) >= 6) >= 0.6) {
                $claim('summary', $i);
            }
        }

        return $positions;
    }
}
