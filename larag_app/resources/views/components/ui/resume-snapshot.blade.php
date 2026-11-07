@props(['resume' => null, 'note' => null])

@php
    /**
     * The full parsed resume, formatted for reading.
     *
     * Every section is optional — a CV that never mentioned projects renders no
     * Projects block rather than an empty one, so the popup shows what the file
     * actually contained and nothing more.
     */
    $r = is_array($resume) ? $resume : [];

    // Confidence is metadata about the parse, not resume content: on its own it
    // does not make a snapshot worth rendering.
    $hasContent = ! empty(array_filter(array_diff_key($r, ['confidence' => null])));

    $contacts = array_filter([
        'email' => $r['email'] ?? '',
        'phone' => $r['phone'] ?? '',
        'linkedinUrl' => $r['linkedinUrl'] ?? '',
        'websiteUrl' => $r['websiteUrl'] ?? '',
    ]);

    $facts = array_filter([
        'Gender' => $r['gender'] ?? '',
        'Date of birth' => $r['dateOfBirth'] ?? '',
    ]);

    // Dated entries share one shape, so they share one renderer below.
    $timelines = [
        'experience' => ['title' => 'Experience', 'heading' => 'title', 'org' => 'company'],
        'education' => ['title' => 'Education', 'heading' => 'degree', 'org' => 'institution'],
        'projects' => ['title' => 'Projects', 'heading' => 'name', 'org' => 'role'],
        'certificates' => ['title' => 'Certificates', 'heading' => 'name', 'org' => 'issuer'],
        'awards' => ['title' => 'Awards', 'heading' => 'title', 'org' => 'issuer'],
    ];

    // Both are name + 1-5 level, but they are read differently: a skill rating
    // wants its denominator, a language wants the word.
    $rated = [
        'expertise' => 'Expertise',
        'languages' => 'Languages',
    ];
    $levels = \App\Services\ResumeParsing\ProficiencyLevels::class;

    $dates = function (array $item): string {
        $range = trim(implode(' – ', array_filter([$item['startDate'] ?? '', $item['endDate'] ?? ''])), ' –');

        return $range ?: trim((string) ($item['date'] ?? $item['issueDate'] ?? ''));
    };
@endphp

@if (! $hasContent)
    <p class="text-sm text-slate-500">Nothing could be read from this file.</p>
@else
    <div class="space-y-6 text-sm">
        {{-- Identity --}}
        <div>
            @if (! empty($r['fullName']))
                <h4 class="text-lg font-semibold text-slate-900">{{ $r['fullName'] }}</h4>
            @endif
            @if (! empty($r['jobTitle']))
                <p class="text-slate-600">{{ $r['jobTitle'] }}</p>
            @endif

            @if ($contacts)
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                    @foreach ($contacts as $key => $value)
                        <span class="break-all">
                            @if ($key === 'email')✉@elseif ($key === 'phone')☎@else🔗@endif
                            {{ $value }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($facts)
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($facts as $label => $value)
                        <x-ui.badge color="gray" size="sm">{{ $label }}: {{ $value }}</x-ui.badge>
                    @endforeach
                </div>
            @endif

            {{-- How certain the parser was that it read this document correctly. --}}
            @if (\App\Services\ResumeParsing\ParseConfidence::percent($r['confidence'] ?? null) !== null)
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>Read with</span>
                    <x-ui.confidence-pill :confidence="$r['confidence'] ?? null" size="sm" />
                    <span>certainty</span>
                </div>
            @endif
        </div>

        @if (! empty($r['summary']))
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Summary</p>
                <p class="leading-relaxed text-slate-700">{{ $r['summary'] }}</p>
            </div>
        @endif

        @if (! empty($r['skills']))
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Skills</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($r['skills'] as $skill)<x-ui.badge size="sm">{{ $skill }}</x-ui.badge>@endforeach
                </div>
            </div>
        @endif

        {{-- Name + level pairs --}}
        @foreach ($rated as $key => $label)
            @if (! empty($r[$key]))
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($r[$key] as $item)
                            @if (! empty($item['name']))
                                @php
                                    $level = (int) ($item['level'] ?? 0);
                                    $shown = $key === 'languages'
                                        ? $levels::language($level)
                                        : $levels::expertise($level);
                                    // "4/5" is clearer with the word behind it on hover.
                                    $word = $key === 'expertise' ? $levels::expertiseLabel($level) : '';
                                @endphp
                                <x-ui.badge color="gray" size="sm" :title="$word ?: null">
                                    {{ $item['name'] }}@if ($shown) · {{ $shown }}@endif
                                </x-ui.badge>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Dated entries --}}
        @foreach ($timelines as $key => $spec)
            @if (! empty($r[$key]))
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $spec['title'] }}</p>
                    <ul class="space-y-3">
                        @foreach ($r[$key] as $item)
                            @php
                                $heading = trim((string) ($item[$spec['heading']] ?? ''));
                                $org = trim((string) ($item[$spec['org']] ?? ''));
                                $when = $dates($item);
                                $place = trim((string) ($item['location'] ?? ''));
                                // A degree reads as "B.Sc. Computer Science".
                                if ($key === 'education' && ! empty($item['field'])) {
                                    $heading = trim($heading.' '.$item['field']);
                                }
                            @endphp
                            @if ($heading || $org)
                                <li class="border-l-2 border-slate-100 pl-3">
                                    <p class="font-medium text-slate-900">
                                        {{ $heading ?: $org }}@if ($heading && $org) <span class="font-normal text-slate-500">· {{ $org }}</span>@endif
                                    </p>
                                    @if ($when || $place)
                                        <p class="text-xs text-slate-500">{{ trim($when.($when && $place ? ' · ' : '').$place) }}</p>
                                    @endif
                                    @if (! empty($item['grade']))
                                        <p class="text-xs text-slate-500">Grade: {{ $item['grade'] }}</p>
                                    @endif
                                    @if (! empty($item['url']))
                                        <p class="text-xs break-all text-indigo-600">{{ $item['url'] }}</p>
                                    @endif
                                    @if (! empty($item['description']))
                                        <p class="mt-1 leading-relaxed text-slate-700">{{ $item['description'] }}</p>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach

        @if ($note)
            <p class="border-t border-slate-100 pt-4 text-xs text-slate-500">{{ $note }}</p>
        @endif
    </div>
@endif
