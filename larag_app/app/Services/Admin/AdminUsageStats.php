<?php

namespace App\Services\Admin;

use App\Models\ProviderBatch;
use App\Models\ResumeBatch;
use App\Models\ToolRun;
use App\Models\UsageLog;
use App\Services\Tools\ToolRegistry;
use App\Support\Stats\StatsPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every number behind the admin dashboard, for one duration filter.
 *
 * AI spend lives in four separate ledgers that never overlap:
 *   - `aitools_run_events`   — public Free Tools
 *   - `pipeline_usage_logs`  — the CV batch pipeline
 *   - `usage_logs`           — the paid API (sync + async lanes)
 *   - `trial_usage_logs`     — the free-credits API
 * so the overall totals are a straight sum of the four.
 *
 * Results are memoised per instance: the ten widgets on the page share one
 * instance per request, so each aggregate is queried once.
 */
class AdminUsageStats
{
    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_GEMINI = 'gemini';

    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(public readonly StatsPeriod $period) {}

    public static function for(StatsPeriod $period): self
    {
        return new self($period);
    }

    // ---------------------------------------------------------------- Tools

    /**
     * Free Tools. "Opened" counts runs whose emailed link was actually opened
     * (that is what starts the AI work); "processed" counts the runs that then
     * finished every AI stage. Each is filtered on its own timestamp so a run
     * opened today still counts today even if it was submitted last week.
     *
     * @return array{opened: int, processed: int, submitted: int, failed: int, tokens: array<string, int>, perTool: array<int, array<string, mixed>>}
     */
    public function tools(): array
    {
        return $this->remember('tools', function (): array {
            $opened = $this->countByTool('aitools_runs', 'opened_at', fn (Builder $query) => $query->whereNotNull('opened_at'));
            $processed = $this->countByTool('aitools_runs', 'completed_at', fn (Builder $query) => $query->where('status', ToolRun::STATUS_COMPLETED));
            $tokensByTool = $this->tokensByGroup('aitools_run_events', 'provider', 'tokens_total', 'created_at', 'tool');

            $labels = $this->toolLabels();
            $slugs = array_keys($labels + $opened + $processed + $tokensByTool);

            $perTool = [];

            foreach ($slugs as $slug) {
                $tokens = $tokensByTool[$slug] ?? $this->emptyTokens();

                if (($opened[$slug] ?? 0) === 0 && ($processed[$slug] ?? 0) === 0 && $tokens['total'] === 0) {
                    continue;
                }

                $perTool[] = [
                    'slug' => $slug,
                    'label' => $labels[$slug] ?? $slug,
                    'opened' => $opened[$slug] ?? 0,
                    'processed' => $processed[$slug] ?? 0,
                    'tokens' => $tokens,
                ];
            }

            usort($perTool, fn (array $a, array $b) => $b['processed'] <=> $a['processed'] ?: $b['opened'] <=> $a['opened']);

            return [
                'opened' => array_sum($opened),
                'processed' => array_sum($processed),
                'submitted' => (int) $this->scope(DB::table('aitools_runs'), 'created_at')->count(),
                'failed' => (int) $this->scope(DB::table('aitools_runs'), 'completed_at')
                    ->where('status', ToolRun::STATUS_FAILED)->count(),
                'tokens' => $this->toolTokens(),
                'perTool' => $perTool,
            ];
        });
    }

    /** @return array<string, int> */
    public function toolTokens(): array
    {
        return $this->remember('toolTokens', fn (): array => $this->tokens('aitools_run_events', 'provider', 'tokens_total', 'created_at'));
    }

    // -------------------------------------------------------------- Batches

    /**
     * CV batches. A "batch" is one recruiter upload (`resume_batches`); the AI
     * requests inside it are the per-CV parse/match calls in
     * `pipeline_usage_logs`, across both the live and provider-batch lanes.
     *
     * @return array{total: int, completed: int, partial: int, failed: int, requestsSuccess: int, requestsFailed: int, providerBatches: int, providerBatchesCompleted: int, tokens: array<string, int>, perLane: array<int, array<string, mixed>>}
     */
    public function batches(): array
    {
        return $this->remember('batches', function (): array {
            $byStatus = $this->scope(DB::table('resume_batches'), 'created_at')
                ->whereNull('deleted_at')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $requests = $this->scope(DB::table('pipeline_usage_logs'), 'created_at')
                ->selectRaw('lane, status, COUNT(*) AS aggregate')
                ->groupBy('lane', 'status')
                ->get();

            $perLane = [];

            foreach ($requests as $row) {
                $lane = $row->lane ?: 'live';
                $perLane[$lane] ??= ['lane' => $lane, 'success' => 0, 'failed' => 0, 'total' => 0];
                $key = $row->status === 'success' ? 'success' : 'failed';
                $perLane[$lane][$key] += (int) $row->aggregate;
                $perLane[$lane]['total'] += (int) $row->aggregate;
            }

            ksort($perLane);

            return [
                'total' => (int) $byStatus->sum(),
                'completed' => (int) ($byStatus[ResumeBatch::STATUS_COMPLETED] ?? 0),
                'partial' => (int) ($byStatus[ResumeBatch::STATUS_PARTIAL] ?? 0),
                'failed' => (int) ($byStatus[ResumeBatch::STATUS_FAILED] ?? 0),
                'requestsSuccess' => (int) array_sum(array_column($perLane, 'success')),
                'requestsFailed' => (int) array_sum(array_column($perLane, 'failed')),
                'providerBatches' => (int) $this->scope(DB::table('provider_batches'), 'created_at')->count(),
                'providerBatchesCompleted' => (int) $this->scope(DB::table('provider_batches'), 'created_at')
                    ->where('status', ProviderBatch::STATUS_COMPLETED)->count(),
                'tokens' => $this->batchTokens(),
                'perLane' => array_values($perLane),
            ];
        });
    }

    /** @return array<string, int> */
    public function batchTokens(): array
    {
        return $this->remember('batchTokens', fn (): array => $this->tokens('pipeline_usage_logs', 'ai_provider', 'tokens_used', 'created_at'));
    }

    // ------------------------------------------------------------------ API

    /**
     * Paid API traffic. Sync and async calls both write one `usage_logs` row;
     * the `lane` column says which. A refunded row is a failed call — the
     * credits went back to the customer.
     *
     * @return array{total: int, success: int, failed: int, sync: int, async: int, tokens: array<string, int>, perEndpoint: array<int, array<string, mixed>>}
     */
    public function api(): array
    {
        return $this->remember('api', fn (): array => $this->endpointBreakdown('usage_logs', 'lane') + [
            'tokens' => $this->apiTokens(),
        ]);
    }

    /** @return array<string, int> */
    public function apiTokens(): array
    {
        return $this->remember('apiTokens', fn (): array => $this->tokens('usage_logs', 'ai_provider', 'tokens_used', 'created_at'));
    }

    // --------------------------------------------------------- Free credits

    /**
     * The free-credits (trial) API log. Trial keys can only call the sync
     * endpoints — the async ones are behind an active subscription — so every
     * row here is the sync lane.
     *
     * @return array{total: int, success: int, failed: int, sync: int, async: int, tokens: array<string, int>, perEndpoint: array<int, array<string, mixed>>}
     */
    public function freeCredits(): array
    {
        return $this->remember('freeCredits', fn (): array => $this->endpointBreakdown('trial_usage_logs', null) + [
            'tokens' => $this->freeCreditTokens(),
        ]);
    }

    /** @return array<string, int> */
    public function freeCreditTokens(): array
    {
        return $this->remember('freeCreditTokens', fn (): array => $this->tokens('trial_usage_logs', 'ai_provider', 'tokens_used', 'created_at'));
    }

    // -------------------------------------------------------------- Overall

    /**
     * Every token spent in the window, whichever ledger booked it.
     *
     * @return array<string, int>
     */
    public function overallTokens(): array
    {
        return $this->remember('overallTokens', function (): array {
            $total = $this->emptyTokens();

            foreach ([$this->toolTokens(), $this->batchTokens(), $this->apiTokens(), $this->freeCreditTokens()] as $tokens) {
                foreach ($tokens as $key => $value) {
                    $total[$key] += $value;
                }
            }

            return $total;
        });
    }

    /**
     * AI request counts per source, for the share-of-traffic chart.
     *
     * @return array<string, int>
     */
    public function requestsBySource(): array
    {
        return $this->remember('requestsBySource', function (): array {
            $batches = $this->batches();
            $api = $this->api();
            $free = $this->freeCredits();

            return [
                'Free Tools' => (int) $this->scope(DB::table('aitools_run_events'), 'created_at')->count(),
                'Batches' => $batches['requestsSuccess'] + $batches['requestsFailed'],
                'API' => $api['total'],
                'Free credits' => $free['total'],
            ];
        });
    }

    /**
     * Tokens per provider over time, bucketed by hour, day or month depending
     * on how wide the selected duration is.
     *
     * @return array{labels: array<int, string>, openai: array<int, int>, gemini: array<int, int>}
     */
    public function tokenTrend(): array
    {
        return $this->remember('tokenTrend', function (): array {
            $sources = [
                ['aitools_run_events', 'provider', 'tokens_total'],
                ['pipeline_usage_logs', 'ai_provider', 'tokens_used'],
                ['usage_logs', 'ai_provider', 'tokens_used'],
                ['trial_usage_logs', 'ai_provider', 'tokens_used'],
            ];

            $series = [];

            foreach ($sources as [$table, $providerColumn, $tokenColumn]) {
                $rows = $this->scope(DB::table($table), 'created_at')
                    ->selectRaw("{$this->bucketExpression('created_at')} AS bucket, {$providerColumn} AS provider, SUM({$tokenColumn}) AS aggregate")
                    ->groupBy('bucket', 'provider')
                    ->get();

                foreach ($rows as $row) {
                    $provider = $this->normaliseProvider($row->provider);

                    if ($provider === 'other') {
                        continue;
                    }

                    $series[$row->bucket][$provider] = ($series[$row->bucket][$provider] ?? 0) + (int) $row->aggregate;
                }
            }

            $buckets = $this->trendBuckets();

            foreach (array_keys($series) as $bucket) {
                if (! in_array($bucket, $buckets, true)) {
                    $buckets[] = $bucket;
                }
            }

            sort($buckets);

            return [
                'labels' => array_map(fn (string $bucket) => $this->formatBucket($bucket), $buckets),
                'openai' => array_map(fn (string $bucket) => $series[$bucket][self::PROVIDER_OPENAI] ?? 0, $buckets),
                'gemini' => array_map(fn (string $bucket) => $series[$bucket][self::PROVIDER_GEMINI] ?? 0, $buckets),
            ];
        });
    }

    /** "OpenAI: 1,024 | Gemini: 512", the wording used across the dashboard. */
    public function formatTokens(array $tokens): string
    {
        $parts = [
            'OpenAI: '.number_format($tokens[self::PROVIDER_OPENAI] ?? 0),
            'Gemini: '.number_format($tokens[self::PROVIDER_GEMINI] ?? 0),
        ];

        if (($tokens['other'] ?? 0) > 0) {
            $parts[] = 'Other: '.number_format($tokens['other']);
        }

        return implode(' | ', $parts);
    }

    // ------------------------------------------------------------ Internals

    /**
     * Endpoint × lane × outcome, shared by the API and free-credits sections.
     * `$laneColumn` is null for ledgers that only have one lane.
     */
    private function endpointBreakdown(string $table, ?string $laneColumn): array
    {
        $laneSelect = $laneColumn ? "{$laneColumn} AS lane" : "'".UsageLog::LANE_SYNC."' AS lane";

        $groupBy = array_values(array_filter(['endpoint', $laneColumn, 'status']));

        $rows = $this->scope(DB::table($table), 'created_at')
            ->selectRaw("endpoint, {$laneSelect}, status, COUNT(*) AS aggregate")
            ->groupBy($groupBy)
            ->get();

        $perEndpoint = [];
        $totals = ['total' => 0, 'success' => 0, 'failed' => 0, 'sync' => 0, 'async' => 0];

        foreach ($rows as $row) {
            $lane = $row->lane ?: UsageLog::LANE_SYNC;
            $key = $row->endpoint.'|'.$lane;
            $count = (int) $row->aggregate;
            $outcome = $row->status === UsageLog::STATUS_SUCCESS ? 'success' : 'failed';

            $perEndpoint[$key] ??= [
                'endpoint' => $row->endpoint,
                'lane' => $lane,
                'success' => 0,
                'failed' => 0,
                'total' => 0,
            ];

            $perEndpoint[$key][$outcome] += $count;
            $perEndpoint[$key]['total'] += $count;

            $totals[$outcome] += $count;
            $totals['total'] += $count;
            $totals[$lane] = ($totals[$lane] ?? 0) + $count;
        }

        uasort($perEndpoint, fn (array $a, array $b) => [$a['endpoint'], $a['lane']] <=> [$b['endpoint'], $b['lane']]);

        return $totals + ['perEndpoint' => array_values($perEndpoint)];
    }

    /** @return array<string, int> */
    private function tokens(string $table, string $providerColumn, string $tokenColumn, string $dateColumn): array
    {
        $rows = $this->scope(DB::table($table), $dateColumn)
            ->selectRaw("{$providerColumn} AS provider, SUM({$tokenColumn}) AS aggregate")
            ->groupBy('provider')
            ->get();

        $tokens = $this->emptyTokens();

        foreach ($rows as $row) {
            $bucket = $this->normaliseProvider($row->provider);
            $tokens[$bucket] += (int) $row->aggregate;
            $tokens['total'] += (int) $row->aggregate;
        }

        return $tokens;
    }

    /** Same as tokens(), split by an extra grouping column. @return array<string, array<string, int>> */
    private function tokensByGroup(string $table, string $providerColumn, string $tokenColumn, string $dateColumn, string $groupColumn): array
    {
        $rows = $this->scope(DB::table($table), $dateColumn)
            ->selectRaw("{$groupColumn} AS grouping_key, {$providerColumn} AS provider, SUM({$tokenColumn}) AS aggregate")
            ->groupBy('grouping_key', 'provider')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->grouping_key] ??= $this->emptyTokens();
            $bucket = $this->normaliseProvider($row->provider);
            $grouped[$row->grouping_key][$bucket] += (int) $row->aggregate;
            $grouped[$row->grouping_key]['total'] += (int) $row->aggregate;
        }

        return $grouped;
    }

    /** @return array<string, int> */
    private function countByTool(string $table, string $dateColumn, callable $constrain): array
    {
        $query = $this->scope(DB::table($table), $dateColumn);
        $constrain($query);

        return $query->selectRaw('tool, COUNT(*) AS aggregate')
            ->groupBy('tool')
            ->pluck('aggregate', 'tool')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /** @return array<string, string> */
    private function toolLabels(): array
    {
        $labels = [];

        foreach (app(ToolRegistry::class)->all() as $tool) {
            $labels[$tool->slug()] = $tool->menuLabel();
        }

        return $labels;
    }

    private function scope(Builder $query, string $column): Builder
    {
        $from = $this->period->startsAt();

        return $query
            ->when($from, fn (Builder $inner) => $inner->where($column, '>=', $from))
            ->where($column, '<=', $this->period->endsAt());
    }

    /** Bucket key for the trend chart. SQLite is here for the test suite. */
    private function bucketExpression(string $column): string
    {
        $bucket = $this->period->bucket();

        if (DB::connection()->getDriverName() === 'sqlite') {
            return match ($bucket) {
                'hour' => "strftime('%Y-%m-%d %H:00', {$column})",
                'day' => "strftime('%Y-%m-%d', {$column})",
                default => "strftime('%Y-%m', {$column})",
            };
        }

        return match ($bucket) {
            'hour' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
            'day' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** Empty buckets so a quiet hour or day still draws a point on the chart. */
    private function trendBuckets(): array
    {
        $from = $this->period->startsAt();

        if ($from === null) {
            return [];
        }

        $buckets = [];
        $cursor = $from->copy();
        $end = $this->period->endsAt();
        $bucket = $this->period->bucket();

        while ($cursor <= $end && count($buckets) < 400) {
            $buckets[] = match ($bucket) {
                'hour' => $cursor->format('Y-m-d H:00'),
                'day' => $cursor->format('Y-m-d'),
                default => $cursor->format('Y-m'),
            };

            $cursor = match ($bucket) {
                'hour' => $cursor->addHour(),
                'day' => $cursor->addDay(),
                default => $cursor->addMonth(),
            };
        }

        return array_values(array_unique($buckets));
    }

    private function formatBucket(string $bucket): string
    {
        return match ($this->period->bucket()) {
            'hour' => substr($bucket, 11, 5),
            'day' => date('j M', strtotime($bucket)),
            default => date('M Y', strtotime($bucket.'-01')),
        };
    }

    private function normaliseProvider(?string $provider): string
    {
        return match (strtolower(trim((string) $provider))) {
            self::PROVIDER_OPENAI => self::PROVIDER_OPENAI,
            self::PROVIDER_GEMINI => self::PROVIDER_GEMINI,
            default => 'other',
        };
    }

    /** @return array<string, int> */
    private function emptyTokens(): array
    {
        return [self::PROVIDER_OPENAI => 0, self::PROVIDER_GEMINI => 0, 'other' => 0, 'total' => 0];
    }

    private function remember(string $key, callable $resolve): mixed
    {
        return $this->memo[$key] ??= $resolve();
    }
}
