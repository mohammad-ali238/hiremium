{{--
    Breakdown table shared by the tools / API / free-credits sections.
    The panel has no custom theme build, so Filament's stylesheet only carries
    its own fi-* classes — the styles below are scoped here and lean on
    Filament's colour variables (with fallbacks) so light and dark both work.
--}}
<x-filament::section :heading="$heading">
    @if (count($rows) === 0)
        <p class="hm-breakdown-empty">{{ $emptyMessage }}</p>
    @else
        <div class="hm-breakdown-scroll">
            <table class="hm-breakdown">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th @class(['hm-breakdown-right' => ($column['align'] ?? 'left') === 'right'])>
                                {{ $column['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td @class([
                                    'hm-breakdown-right' => ($column['align'] ?? 'left') === 'right',
                                    'hm-breakdown-label' => $loop->first,
                                ])>
                                    {{ $row[$column['key']] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @once
        <style>
            .hm-breakdown-scroll { overflow-x: auto; }

            .hm-breakdown {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

            .hm-breakdown th,
            .hm-breakdown td {
                padding: 0.625rem 0.75rem;
                text-align: left;
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }

            .hm-breakdown th {
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--gray-500, #71717a);
                border-bottom: 1px solid var(--gray-200, #e4e4e7);
            }

            .hm-breakdown td {
                color: var(--gray-700, #3f3f46);
                border-bottom: 1px solid var(--gray-100, #f4f4f5);
            }

            .hm-breakdown tbody tr:last-child td { border-bottom: 0; }

            .hm-breakdown-label { font-weight: 600; color: var(--gray-950, #09090b); }
            .hm-breakdown-right { text-align: right; }

            .hm-breakdown-empty {
                font-size: 0.875rem;
                color: var(--gray-500, #71717a);
            }

            :where(.dark, .dark *) .hm-breakdown th {
                color: var(--gray-400, #a1a1aa);
                border-bottom-color: var(--gray-700, #3f3f46);
            }

            :where(.dark, .dark *) .hm-breakdown td {
                color: var(--gray-300, #d4d4d8);
                border-bottom-color: var(--gray-800, #27272a);
            }

            :where(.dark, .dark *) .hm-breakdown-label { color: var(--gray-50, #fafafa); }
            :where(.dark, .dark *) .hm-breakdown-empty { color: var(--gray-400, #a1a1aa); }
        </style>
    @endonce
</x-filament::section>
