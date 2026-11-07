<div class="space-y-6">
    <div>
        <x-ui.breadcrumbs :items="[['label' => 'Matched Candidates', 'url' => route('app.candidates')], ['label' => 'Compare']]" class="mb-2" />
        <h1 class="text-2xl font-bold text-app-text">Compare candidates</h1>
        <p class="mt-1 text-sm text-muted">Side-by-side on score, skills, experience and education.</p>
    </div>

    @if ($matches->count() < 2)
        <x-ui.card>
            <x-ui.empty title="Pick candidates to compare" description="Select 2 or 3 candidates on the Candidates list and click Compare.">
                <x-slot:action><x-ui.button href="{{ route('app.candidates') }}">Go to candidates</x-ui.button></x-slot:action>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-line">
                        {{-- Header row --}}
                        <tr>
                            <td class="w-40 bg-surface px-4 py-4"></td>
                            @foreach ($matches as $m)
                                <td class="px-4 py-4 align-top">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <x-ui.avatar :name="$m->candidate?->name ?? '?'" size="sm" />
                                            <div>
                                                <a href="{{ route('app.candidates.show', $m) }}" class="font-semibold text-app-text hover:text-primary">{{ $m->candidate?->name ?? 'Unknown' }}</a>
                                                <p class="text-xs text-muted">{{ $m->job?->title }}</p>
                                            </div>
                                        </div>
                                        <button wire:click="remove({{ $m->id }})" class="text-muted hover:text-danger" aria-label="Remove">✕</button>
                                    </div>
                                    <div class="mt-2"><x-ui.score-pill :score="$m->score" /></div>
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="bg-surface px-4 py-3 font-medium text-muted">Recommendation</td>
                            @foreach ($matches as $m)<td class="px-4 py-3 text-app-text">{{ \App\Models\CandidateJobMatch::categoryLabels()[$m->category] ?? '—' }}</td>@endforeach
                        </tr>
                        @foreach (['skills' => 'Skills score', 'experience' => 'Experience score', 'education' => 'Education score'] as $key => $label)
                            <tr>
                                <td class="bg-surface px-4 py-3 font-medium text-muted">{{ $label }}</td>
                                @foreach ($matches as $m)<td class="px-4 py-3"><span class="tabular-nums font-semibold text-app-text">{{ $m->sub_scores[$key] ?? '—' }}</span></td>@endforeach
                            </tr>
                        @endforeach
                        <tr>
                            <td class="bg-surface px-4 py-3 font-medium text-muted align-top">Matched skills</td>
                            @foreach ($matches as $m)<td class="px-4 py-3"><div class="flex flex-wrap gap-1">@forelse ($m->matched_skills ?? [] as $s)<x-ui.badge color="success" size="sm">{{ $s }}</x-ui.badge>@empty<span class="text-muted">—</span>@endforelse</div></td>@endforeach
                        </tr>
                        <tr>
                            <td class="bg-surface px-4 py-3 font-medium text-muted align-top">Missing skills</td>
                            @foreach ($matches as $m)<td class="px-4 py-3"><div class="flex flex-wrap gap-1">@forelse ($m->missing_skills ?? [] as $s)<x-ui.badge color="danger" size="sm">{{ $s }}</x-ui.badge>@empty<span class="text-muted">—</span>@endforelse</div></td>@endforeach
                        </tr>
                        @if ($matches->contains(fn ($m) => ! empty($m->strengths)))
                            <tr>
                                <td class="bg-surface px-4 py-3 font-medium text-muted align-top">Reasons to hire</td>
                                @foreach ($matches as $m)<td class="px-4 py-3 align-top"><ul class="space-y-1 text-xs text-app-text">@forelse ($m->strengths ?? [] as $s)<li class="flex gap-1.5"><span class="shrink-0 font-bold text-success">✓</span><span>{{ $s }}</span></li>@empty<li class="text-muted">—</li>@endforelse</ul></td>@endforeach
                            </tr>
                        @endif
                        @if ($matches->contains(fn ($m) => ! empty($m->concerns)))
                            <tr>
                                <td class="bg-surface px-4 py-3 font-medium text-muted align-top">Reasons to reject</td>
                                @foreach ($matches as $m)<td class="px-4 py-3 align-top"><ul class="space-y-1 text-xs text-app-text">@forelse ($m->concerns ?? [] as $s)<li class="flex gap-1.5"><span class="shrink-0 font-bold text-danger">✕</span><span>{{ $s }}</span></li>@empty<li class="text-muted">—</li>@endforelse</ul></td>@endforeach
                            </tr>
                        @endif
                        <tr>
                            <td class="bg-surface px-4 py-3 font-medium text-muted">CV</td>
                            @foreach ($matches as $m)<td class="px-4 py-3">@if ($m->resumeFile)<a href="{{ route('app.cv', $m->resumeFile) }}" target="_blank" class="text-primary hover:underline">Download CV</a>@else—@endif</td>@endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</div>
