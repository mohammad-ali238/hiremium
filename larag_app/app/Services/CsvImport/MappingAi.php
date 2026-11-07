<?php

namespace App\Services\CsvImport;

use App\Models\AiSetting;
use App\Support\ProviderHttpClient;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Maps CSV columns to candidate fields with one small AI call, using the
 * PARSING provider settings from AI Settings (mapping is a parsing-class
 * task). Free of charge — pre-batch, so no credits and no usage-log row; token
 * counts go to the app log for observability.
 *
 * The AI maps columns BY POSITION ("Col #3") and decides whether the first row
 * is a header row or already candidate data — so files without a header row
 * are fully supported: their columns are simply named Col #1..N in the UI.
 *
 * This call ALWAYS uses the real AI API — site-settings testing mode fakes
 * only the parse/score pipeline, never column detection. The deterministic
 * HeuristicMapper (header-name synonyms when a header row exists, data-pattern
 * detection when it doesn't) is purely the fallback for AI failures and
 * rate-limit hits, so the user always gets a mapping to review.
 */
class MappingAi
{
    public function __construct(private HeuristicMapper $heuristics) {}

    /**
     * @param  list<string>  $firstRow  the file's first row (header labels OR the first candidate)
     * @param  array<int, array<int, string>>  $samples  representative data rows (excluding the first row)
     * @param  bool  $headerGuess  cheap local guess whether the first row is a header
     * @return array{source: string, has_header_row: bool, has_candidate_data: bool, consistent: bool, reason: string, positions: array<string, ?int>}
     */
    public function suggest(array $firstRow, array $samples, bool $headerGuess): array
    {
        try {
            $raw = $this->callProvider($firstRow, $samples, $headerGuess);
        } catch (Throwable $e) {
            Log::warning('CSV mapping AI call failed — heuristic fallback used', ['error' => $e->getMessage()]);

            return $this->heuristicResult($firstRow, $samples, $headerGuess, 'fallback');
        }

        return [
            'source' => 'ai',
            'has_header_row' => (bool) ($raw['has_header_row'] ?? $headerGuess),
            'has_candidate_data' => (bool) ($raw['has_candidate_data'] ?? false),
            'consistent' => (bool) ($raw['consistent'] ?? true),
            'reason' => is_string($raw['reason'] ?? null) ? $raw['reason'] : '',
            'positions' => $this->parsePositions((array) ($raw['mapping'] ?? []), count($firstRow)),
        ];
    }

    /** Compulsory rule on positions (0 is a valid index — never use empty()). */
    public static function positionsSatisfyCompulsory(array $positions): bool
    {
        $has = fn (string $f) => ($positions[$f] ?? null) !== null;

        return $has('name') && $has('email') && ($has('jobTitle') || $has('summary'));
    }

    /** Public entry for callers that must skip the AI call (e.g. rate limit hit). */
    public function heuristicSuggestion(array $firstRow, array $samples, bool $headerGuess): array
    {
        return $this->heuristicResult($firstRow, $samples, $headerGuess, 'fallback');
    }

    /**
     * @param  list<string>  $firstRow
     * @param  array<int, array<int, string>>  $samples
     */
    private function heuristicResult(array $firstRow, array $samples, bool $headerGuess, string $source): array
    {
        if ($headerGuess) {
            // Header names exist → synonym matching, converted to positions.
            $labels = CsvRowReader::uniqueLabels($firstRow);
            $byLabel = array_flip($labels);
            $positions = array_map(
                fn ($label) => $label !== null ? ($byLabel[$label] ?? null) : null,
                $this->heuristics->map($labels),
            );
        } else {
            // No header row → detect fields from the data itself (the first
            // row IS data, so it joins the sample set).
            $positions = $this->heuristics->mapByContent(array_merge([$firstRow], $samples));
        }

        return [
            'source' => $source,
            'has_header_row' => $headerGuess,
            'has_candidate_data' => self::positionsSatisfyCompulsory($positions),
            'consistent' => true,
            'reason' => '',
            'positions' => $positions,
        ];
    }

    /**
     * AI mapping values are "Col #N" strings (1-based) — convert to validated
     * 0-based indexes; anything unknown becomes null.
     *
     * @return array<string, ?int>
     */
    private function parsePositions(array $mapping, int $columnCount): array
    {
        $positions = array_fill_keys(CandidateFields::keys(), null);
        foreach (CandidateFields::keys() as $field) {
            $value = $mapping[$field] ?? null;
            if (is_string($value) && preg_match('/^col\s*#?\s*(\d+)$/i', trim($value), $m)) {
                $i = ((int) $m[1]) - 1;
                if ($i >= 0 && $i < $columnCount) {
                    $positions[$field] = $i;
                }
            } elseif (is_int($value) && $value >= 1 && $value <= $columnCount) {
                $positions[$field] = $value - 1; // tolerate bare numbers
            }
        }

        return $positions;
    }

    /**
     * @param  list<string>  $firstRow
     * @param  array<int, array<int, string>>  $samples
     * @return array<string, mixed>
     */
    private function callProvider(array $firstRow, array $samples, bool $headerGuess): array
    {
        $settings = AiSetting::current();
        $key = $settings->parsing_ai_provider === AiSetting::PROVIDER_GEMINI
            ? $settings->gemini_api_key
            : $settings->openai_api_key;
        if (empty($key)) {
            throw new CsvImportException('Mapping AI: no API key configured for the parsing provider.');
        }
        $system = $this->systemPrompt();
        $user = $this->userPrompt($firstRow, $samples, $headerGuess);

        if ($settings->parsing_ai_provider === AiSetting::PROVIDER_GEMINI) {
            $response = ProviderHttpClient::gemini($settings->gemini_api_key)
                ->generativeModel($settings->parsing_gemini_model)
                ->withSystemInstruction(Content::parse($system, Role::USER))
                ->withGenerationConfig(new GenerationConfig(
                    temperature: (float) $settings->parsing_temperature,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                ))
                ->generateContent($user);

            $content = $response->text() ?? '';
            $tokens = $response->usageMetadata?->totalTokenCount ?? null;
            $model = $settings->parsing_gemini_model;
        } else {
            $response = ProviderHttpClient::openai($settings->openai_api_key)->chat()->create([
                'model' => $settings->parsing_openai_model,
                'temperature' => (float) $settings->parsing_temperature,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

            $content = $response->choices[0]->message->content ?? '';
            $tokens = $response->usage?->totalTokens;
            $model = $settings->parsing_openai_model;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new CsvImportException('Mapping AI returned an unparseable response.');
        }

        Log::info('CSV mapping AI call', [
            'provider' => $settings->parsing_ai_provider, 'model' => $model,
            'tokens' => $tokens, 'columns' => count($firstRow), 'samples' => count($samples),
        ]);

        return $decoded;
    }

    private function systemPrompt(): string
    {
        $fields = collect(CandidateFields::FIELDS)
            ->map(fn ($label, $key) => "- {$key} ({$label})")
            ->implode("\n");

        return <<<PROMPT
You map the columns of a recruitment CSV file to candidate profile fields.

You receive the file's FIRST ROW and a random sample of its other rows. Columns are referred to BY POSITION: "Col #1" is the first column, "Col #2" the second, and so on.

Candidate fields:
{$fields}

Rules:
- Respond with ONLY a JSON object, no prose.
- "has_header_row": true when the first row contains column labels (e.g. "Full Name", "Email"); false when the first row is already a candidate's data (e.g. an actual person's name and email). Decide from the content, regardless of any hint.
- "mapping" must contain every field key listed above. The value is the column position as a string, e.g. "Col #3", or null when no column holds that data. Decide from the header labels when they exist AND from the sample data in each column position.
- Map each column position to at most one field (the best fit).
- "has_candidate_data": false when the columns clearly do not describe people/candidates (e.g. products, orders, logs).
- "consistent": check the sample rows column by column — when a column's values are mixed or unrelated to each other (e.g. dates mixed with names, random text), set consistent to false and explain briefly in "reason".

Response JSON shape:
{"has_header_row": true, "has_candidate_data": true, "consistent": true, "reason": "", "mapping": {"name": "Col #1", "email": "Col #4", "...": null}}
PROMPT;
    }

    /**
     * @param  list<string>  $firstRow
     * @param  array<int, array<int, string>>  $samples
     */
    private function userPrompt(array $firstRow, array $samples, bool $headerGuess): string
    {
        $lines = [
            'Column count: '.count($firstRow),
            'First row (may be headers or a data row): '.json_encode($firstRow, JSON_UNESCAPED_UNICODE),
            'Local guess about a header row being present: '.($headerGuess ? 'yes' : 'no'),
            '',
            'Sample data rows:',
        ];
        foreach ($samples as $row) {
            $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }
}
