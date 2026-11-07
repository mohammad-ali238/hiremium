@php
    $s = is_array($snapshot ?? null) ? $snapshot : [];
    $employment = \App\Models\Job::employmentTypes()[$s['employment_type'] ?? ''] ?? ($s['employment_type'] ?? null);
    $mode = \App\Models\Job::locationTypes()[$s['location_type'] ?? ''] ?? ($s['location_type'] ?? null);
    $chips = array_filter([$employment, $mode, $s['location'] ?? null, ! empty($s['min_experience_years']) ? $s['min_experience_years'].'+ years experience' : null, $s['education'] ?? null]);
    $mustHave = array_values(array_filter((array) ($s['must_have_skills'] ?? [])));
    $niceToHave = array_values(array_filter((array) ($s['nice_to_have_skills'] ?? [])));
    $taken = $s['snapshot_taken_at'] ?? null;
@endphp
@if (empty($s['title']))
    <p style="color:#6b7280">This batch has no job snapshot (created before snapshots existed).</p>
@else
    <div style="display:grid;gap:18px;font-size:14px;line-height:1.5">
        <div>
            <div style="font-size:18px;font-weight:600">{{ $s['title'] }}</div>
            @if ($chips)
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
                    @foreach ($chips as $chip)<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:500">{{ $chip }}</span>@endforeach
                </div>
            @endif
        </div>
        @if ($mustHave || $niceToHave)
            <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
                @if ($mustHave)
                    <div><div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:6px">Must-have skills</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach ($mustHave as $skill)<span style="padding:2px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px">{{ $skill }}</span>@endforeach</div></div>
                @endif
                @if ($niceToHave)
                    <div><div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:6px">Nice-to-have skills</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px">@foreach ($niceToHave as $skill)<span style="padding:2px 10px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:12px">{{ $skill }}</span>@endforeach</div></div>
                @endif
            </div>
        @endif
        @if (! empty($s['description']))
            <div><div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:6px">Description</div>
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;background:#fafafa;max-height:420px;overflow:auto">{!! \App\Support\RichText::sanitize((string) $s['description']) !!}</div></div>
        @endif
        <div style="font-size:12px;color:#6b7280">Snapshot taken when the batch was created{{ $taken ? ' on '.\Illuminate\Support\Carbon::parse($taken)->format('M j, Y H:i') : '' }}. Later edits to the job do not change this analysis.</div>
    </div>
@endif
