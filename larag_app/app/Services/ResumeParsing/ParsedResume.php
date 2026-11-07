<?php

namespace App\Services\ResumeParsing;

final class ParsedResume
{
    /** Max expertise items the parser may return (mirrors the WP resume builder). */
    public const MAX_EXPERTISE = 5;

    /**
     * Per-section item field maps. Keys are the canonical camelCase field names;
     * values are the value type: 's' = string, 'level' = int 1-5 (0 when unknown).
     * Field sets mirror the Workeera Smart Resume Builder sections.
     */
    private const SECTION_ITEMS = [
        'experience' => [
            'company' => 's',
            'title' => 's',
            'location' => 's',
            'startDate' => 's',
            'endDate' => 's',
            'description' => 's',
        ],
        'education' => [
            'institution' => 's',
            'degree' => 's',
            'field' => 's',
            'location' => 's',
            'startDate' => 's',
            'endDate' => 's',
            'grade' => 's',
        ],
        'projects' => [
            'name' => 's',
            'role' => 's',
            'url' => 's',
            'startDate' => 's',
            'endDate' => 's',
            'description' => 's',
        ],
        'certificates' => [
            'name' => 's',
            'issuer' => 's',
            'issueDate' => 's',
            'expiryDate' => 's',
            'credentialId' => 's',
            'url' => 's',
        ],
        'awards' => [
            'title' => 's',
            'issuer' => 's',
            'date' => 's',
            'description' => 's',
        ],
        'expertise' => [
            'name' => 's',
            'level' => 'level',
        ],
        'languages' => [
            'name' => 's',
            'level' => 'level',
        ],
    ];

    public function __construct(
        public readonly array $data,           // canonical schema (camelCase)
        public readonly ?int $tokensUsed,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $rawResponse,
    ) {}

    /** The parser's certainty about this document, 0-1 (null = not reported). */
    public function confidence(): ?float
    {
        return ParseConfidence::fromData($this->data);
    }

    public static function emptySchema(): array
    {
        return [
            'fullName' => '',
            'email' => '',
            'phone' => '',
            'jobTitle' => '',
            'gender' => '',
            'dateOfBirth' => '',
            'websiteUrl' => '',
            'linkedinUrl' => '',
            'summary' => '',
            'experience' => [],
            'education' => [],
            'projects' => [],
            'certificates' => [],
            'awards' => [],
            'expertise' => [],
            'languages' => [],
            'skills' => [],
            // The parser's own certainty that it read this CV correctly, 0-1.
            // null = the model did not report one.
            'confidence' => null,
        ];
    }

    public static function normalize(array $parsed): array
    {
        $base = self::emptySchema();

        foreach ($base as $key => $default) {
            if (! array_key_exists($key, $parsed)) {
                continue;
            }

            $value = $parsed[$key];

            if (isset(self::SECTION_ITEMS[$key])) {
                $base[$key] = self::normalizeSection($key, $value);
            } elseif ($key === 'confidence') {
                $base[$key] = ParseConfidence::normalize($value);
            } elseif ($key === 'skills') {
                $base[$key] = self::normalizeStringList($value);
            } else {
                $base[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        return $base;
    }

    /**
     * @return list<array<string,string|int>>
     */
    private static function normalizeSection(string $section, mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $fields = self::SECTION_ITEMS[$section];
        $out = [];

        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = [];
            foreach ($fields as $field => $type) {
                $raw = $item[$field] ?? null;
                $normalized[$field] = $type === 'level'
                    ? self::normalizeLevel($raw)
                    : (is_scalar($raw) ? trim((string) $raw) : '');
            }

            // Drop items with no usable content at all.
            $hasContent = collect($normalized)->contains(
                fn ($v) => is_int($v) ? $v > 0 : $v !== ''
            );
            if ($hasContent) {
                $out[] = $normalized;
            }
        }

        if ($section === 'expertise') {
            $out = array_slice($out, 0, self::MAX_EXPERTISE);
        }

        if ($section === 'languages') {
            // Safety net behind the prompt rule: models occasionally leak
            // programming languages into the human-languages list.
            $out = array_values(array_filter(
                $out,
                fn (array $item) => ! self::isProgrammingLanguage((string) ($item['name'] ?? ''))
            ));
        }

        return $out;
    }

    /**
     * Whether a "language" name is actually a programming language, framework
     * or tool that leaked out of the skills list.
     */
    private static function isProgrammingLanguage(string $name): bool
    {
        static $blocklist = [
            'python', 'php', 'java', 'javascript', 'typescript', 'js', 'ts',
            'c', 'c++', 'cpp', 'c#', 'csharp', 'go', 'golang', 'ruby', 'rust',
            'swift', 'kotlin', 'scala', 'perl', 'dart', 'r', 'matlab',
            'sql', 'mysql', 'postgresql', 'html', 'css', 'sass', 'less',
            'bash', 'shell', 'powershell', 'assembly', 'objective-c',
            'react', 'vue', 'angular', 'node', 'nodejs', 'node.js',
            'laravel', 'django', 'flask', 'symfony', 'wordpress',
        ];

        return in_array(mb_strtolower(trim($name)), $blocklist, true);
    }

    /**
     * Level scale mirrors the WP resume builder: int 1-5, 0 = unknown/unset.
     * Also tolerates AI variants like "Level_3", "3/5", or label words.
     */
    private static function normalizeLevel(mixed $raw): int
    {
        if (is_int($raw) || (is_string($raw) && ctype_digit(trim($raw)))) {
            $level = (int) $raw;

            return ($level >= 1 && $level <= 5) ? $level : 0;
        }

        if (is_string($raw)) {
            if (preg_match('/([1-5])/', $raw, $m)) {
                return (int) $m[1];
            }

            $labelMap = [
                'beginner' => 1,
                'elementary' => 2,
                'intermediate' => 3, 'conversational' => 3,
                'advanced' => 4, 'fluent' => 4,
                'expert' => 5, 'native' => 5,
            ];

            $needle = mb_strtolower(trim($raw));
            if (isset($labelMap[$needle])) {
                return $labelMap[$needle];
            }
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            array_values($value),
        ), fn (string $v) => $v !== ''));
    }
}
