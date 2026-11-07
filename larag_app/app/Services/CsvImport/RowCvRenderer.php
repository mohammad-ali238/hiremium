<?php

namespace App\Services\CsvImport;

/**
 * Renders one CSV row into a plain-text CV using the user-confirmed column
 * mapping. The output is DETERMINISTIC — no timestamps, randomness or
 * environment-dependent content — because its sha256 is the file hash the
 * pipeline dedupes and reuses by: the same row must always produce the same
 * bytes, on any run.
 */
class RowCvRenderer
{
    /** field key => column index into the row's cells */
    private array $index = [];

    /**
     * @param  array<string, ?string>  $mapping  field => column label (from the confirmed mapping)
     * @param  list<string>  $labels  unique column labels, position = column index
     */
    public function __construct(array $mapping, array $labels)
    {
        $byLabel = array_flip($labels);
        foreach (CandidateFields::keys() as $field) {
            $label = $mapping[$field] ?? null;
            if (is_string($label) && isset($byLabel[$label])) {
                $this->index[$field] = $byLabel[$label];
            }
        }
    }

    /** @param list<string> $cells */
    public function value(array $cells, string $field): string
    {
        $i = $this->index[$field] ?? null;

        return $i === null ? '' : trim((string) ($cells[$i] ?? ''));
    }

    /**
     * Import rule (user-confirmed): a row needs a non-empty Name AND at least
     * one contact detail (email or phone).
     *
     * @param  list<string>  $cells
     */
    public function isValidRow(array $cells): bool
    {
        return $this->value($cells, 'name') !== ''
            && ($this->value($cells, 'email') !== '' || $this->value($cells, 'phone') !== '');
    }

    /** @param list<string> $cells */
    public function fileName(array $cells, int $rowNumber): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]/', '', $this->value($cells, 'name'));
        $name = trim(mb_substr($name, 0, 80));

        return ($name !== '' ? "{$name} (row {$rowNumber})" : "Row {$rowNumber}").'.txt';
    }

    /**
     * The generated CV text: headline + contact block, then labeled sections in
     * a fixed order. Unmapped / empty parts are omitted entirely.
     *
     * @param  list<string>  $cells
     */
    public function render(array $cells): string
    {
        $lines = [];

        $name = $this->value($cells, 'name');
        if ($name !== '') {
            $lines[] = $name;
        }

        foreach ([
            'jobTitle' => 'Job Title',
            'email' => 'Email',
            'phone' => 'Phone',
            'location' => 'Location',
            'gender' => 'Gender',
            'dateOfBirth' => 'Date of Birth',
            'websiteUrl' => 'Website',
            'linkedinUrl' => 'LinkedIn',
        ] as $field => $label) {
            $value = $this->value($cells, $field);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        foreach ([
            'summary' => 'SUMMARY',
            'experience' => 'EXPERIENCE',
            'education' => 'EDUCATION',
            'projects' => 'PROJECTS',
            'certificates' => 'CERTIFICATES',
            'awards' => 'AWARDS',
            'skills' => 'SKILLS',
            'expertise' => 'EXPERTISE',
            'languages' => 'LANGUAGES',
        ] as $field => $heading) {
            $value = $this->value($cells, $field);
            if ($value !== '') {
                $lines[] = '';
                $lines[] = $heading;
                $lines[] = $value;
            }
        }

        return implode("\n", $lines)."\n";
    }
}
