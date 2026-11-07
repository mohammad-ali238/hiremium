<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace's confirmed CSV column mapping, keyed by a hash of the CSV's
 * normalized header row. On a repeat import with the same headers the stored
 * mapping is pre-filled and the AI mapping call is skipped entirely. Always
 * holds the LAST mapping the user actually confirmed.
 */
class CsvMappingPreset extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'header_hash', 'headers', 'mapping', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
        ];
    }

    /**
     * Hash of a header row, tolerant of case/whitespace differences so the
     * same export format always hits the same preset.
     *
     * @param  array<int, string>  $headers
     */
    public static function hashHeaders(array $headers): string
    {
        $normalized = array_map(
            fn ($h) => preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $h))),
            $headers,
        );

        return hash('sha256', implode('|', $normalized));
    }
}
