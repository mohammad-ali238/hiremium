<?php

namespace App\Filament\Admin\Resources\Candidates\Schemas;

use App\Services\ResumeParsing\ParseConfidence;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

/**
 * Renders whatever the CV parser produced, as Filament components.
 *
 * Built from the data rather than from a fixed field list: the parser's schema
 * has changed before and will again, and a modal that quietly drops unknown
 * keys is worse than useless for debugging a bad parse. Anything not recognised
 * still lands in the raw JSON section at the bottom.
 *
 * Deliberately not a Blade view — the admin panel compiles its own CSS, so app
 * utility classes do not exist inside it.
 */
class ParsedDataInfolist
{
    /** Keys most likely to identify one item of a repeated group, best first. */
    private const TITLE_KEYS = ['title', 'name', 'degree', 'institution', 'company', 'issuer', 'role'];

    /** @return array<int, mixed> */
    public static function for(?array $data): array
    {
        $data = array_filter(
            $data ?? [],
            fn ($value) => $value !== null && $value !== '' && $value !== [],
        );

        if ($data === []) {
            return [
                Section::make()->schema([
                    TextEntry::make('empty')
                        ->hiddenLabel()
                        ->state('This candidate has no parsed data. Either the CV was never parsed, or parsing failed.')
                        ->color('gray'),
                ]),
            ];
        }

        $scalars = [];
        $sections = [];

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                $entry = TextEntry::make('scalar_'.$key)
                    ->label($key === 'confidence' ? 'Parse confidence' : self::label($key))
                    // Confidence is stored 0-1 but always read as a percentage.
                    ->state($key === 'confidence' ? ParseConfidence::format($value) : self::text($value));

                // A summary squeezed into a third of the width is unreadable.
                if (is_string($value) && mb_strlen($value) > 120) {
                    $entry->columnSpanFull();
                }

                $scalars[] = $entry;

                continue;
            }

            $sections[] = self::isListOfArrays($value)
                ? self::groupSection($key, $value)
                : self::badgeSection($key, $value);
        }

        return array_values(array_filter(array_merge(
            $scalars === [] ? [] : [Section::make('Details')->columns(3)->schema($scalars)],
            $sections,
            [self::rawSection($data)],
        )));
    }

    /** A repeated group — experience, education, projects — one card per item. */
    private static function groupSection(string $key, array $items): Section
    {
        $cards = [];

        foreach (array_values($items) as $index => $item) {
            $fields = [];

            foreach ((array) $item as $field => $value) {
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                $entry = TextEntry::make($key.'_'.$index.'_'.$field)
                    ->label(self::label($field))
                    ->state(self::text($value));

                // Descriptions and the like need the full width to stay readable.
                if (is_string($value) && mb_strlen($value) > 120) {
                    $entry->columnSpanFull();
                }

                $fields[] = $entry;
            }

            if ($fields === []) {
                continue;
            }

            $cards[] = Section::make(self::itemTitle((array) $item, $index))
                ->columns(3)
                ->compact()
                ->schema($fields);
        }

        return Section::make(self::label($key))
            ->description(count($cards).' '.Str::plural('entry', count($cards)))
            ->collapsible()
            ->schema($cards);
    }

    /** A flat list — skills, keywords — shown as badges. */
    private static function badgeSection(string $key, array $values): Section
    {
        return Section::make(self::label($key))
            ->compact()
            ->schema([
                TextEntry::make('list_'.$key)
                    ->hiddenLabel()
                    ->badge()
                    ->state(array_map(fn ($v) => self::text($v), array_values($values))),
            ]);
    }

    /** Full fidelity, collapsed: nothing the parser returned is hidden. */
    private static function rawSection(array $data): Section
    {
        return Section::make('Raw JSON')
            ->description('Exactly what the parser stored.')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextEntry::make('raw_parsed_data')
                    ->hiddenLabel()
                    ->copyable()
                    ->copyMessage('Parsed data copied')
                    ->state(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            ]);
    }

    /** A repeated group: a list whose every entry is itself an array. */
    private static function isListOfArrays(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private static function itemTitle(array $item, int $index): string
    {
        foreach (self::TITLE_KEYS as $key) {
            if (filled($item[$key] ?? null) && is_string($item[$key])) {
                return $item[$key];
            }
        }

        return '#'.($index + 1);
    }

    /** camelCase and snake_case keys both become readable labels. */
    private static function label(string $key): string
    {
        return Str::of($key)->snake()->replace('_', ' ')->ucfirst()->toString();
    }

    private static function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            // A nested list inside an item (e.g. bullet points on a role).
            return implode(', ', array_map(
                fn ($v) => is_scalar($v) ? (string) $v : json_encode($v),
                $value,
            ));
        }

        return (string) $value;
    }
}
