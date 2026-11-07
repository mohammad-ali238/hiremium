@props(['snapshot' => null, 'takenAt' => null, 'note' => null])
@php
    $s = is_array($snapshot) ? $snapshot : [];
    $employment = \App\Models\Job::employmentTypes()[$s['employment_type'] ?? ''] ?? ($s['employment_type'] ?? null);
    $mode = \App\Models\Job::locationTypes()[$s['location_type'] ?? ''] ?? ($s['location_type'] ?? null);
    $taken = $takenAt ?? ($s['snapshot_taken_at'] ?? null);
    $mustHave = array_values(array_filter((array) ($s['must_have_skills'] ?? [])));
    $niceToHave = array_values(array_filter((array) ($s['nice_to_have_skills'] ?? [])));
@endphp
@if (empty($s['title']))
    <x-ui.empty title="No job snapshot" description="This batch was created before job snapshots existed." />
@else
    <div class="space-y-5">
        <div>
            <h4 class="text-lg font-semibold text-app-text">{{ $s['title'] }}</h4>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($employment)<x-ui.badge color="primary">{{ $employment }}</x-ui.badge>@endif
                @if ($mode)<x-ui.badge color="gray">{{ $mode }}</x-ui.badge>@endif
                @if (! empty($s['location']))<x-ui.badge color="gray">{{ $s['location'] }}</x-ui.badge>@endif
                @if (! empty($s['min_experience_years']))<x-ui.badge color="warning">{{ $s['min_experience_years'] }}+ years experience</x-ui.badge>@endif
                @if (! empty($s['education']))<x-ui.badge color="gray">{{ $s['education'] }}</x-ui.badge>@endif
            </div>
        </div>

        @if ($mustHave || $niceToHave)
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($mustHave)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">Must-have skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($mustHave as $skill)<x-ui.badge color="success">{{ $skill }}</x-ui.badge>@endforeach
                        </div>
                    </div>
                @endif
                @if ($niceToHave)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">Nice-to-have skills</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($niceToHave as $skill)<x-ui.badge color="gray">{{ $skill }}</x-ui.badge>@endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if (! empty($s['description']))
            @php
                // Stored already sanitized by App\Support\RichText at save time;
                // the snapshot is a verbatim copy, re-sanitized here as a belt.
                $description = \App\Support\RichText::sanitize((string) $s['description']);

                // A recruiter's description arrives as HTML from the editor, which
                // carries its own paragraphs. A free-tool visitor types into a
                // plain textarea, where the breaks they made are real newlines —
                // and HTML collapses those into one run-on paragraph. Give text
                // without block markup its line breaks back.
                $hasBlockMarkup = (bool) preg_match('/<(p|div|br|ul|ol|li|h2|h3|blockquote)\b/i', (string) $description);
            @endphp
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted">Description</p>
                <div class="prose prose-sm max-w-none rounded-lg border border-line bg-app-bg/40 p-4 text-app-text dark:prose-invert">{!! $hasBlockMarkup ? $description : nl2br($description) !!}</div>
            </div>
        @endif

        <p class="text-xs text-muted">
            @if ($note)
                {{ $note }}
            @else
                Snapshot of the job taken when this batch was created{{ $taken ? ' on '.\Illuminate\Support\Carbon::parse($taken)->format('M j, Y H:i') : '' }}.
                Later edits to the job do not change this analysis.
            @endif
        </p>
    </div>
@endif
