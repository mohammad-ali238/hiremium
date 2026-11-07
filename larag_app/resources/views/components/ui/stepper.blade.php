@props(['stages' => []])
{{-- Batch-progress stages. :stages="[
       ['label'=>'Uploaded','count'=>50,'state'=>'done'],
       ['label'=>'Parsed','count'=>32,'state'=>'active'],
       ['label'=>'Analyzed','count'=>0,'state'=>'pending'],
       ['label'=>'Failed','count'=>1,'state'=>'failed'],
     ]" --}}
<div {{ $attributes->merge(['class' => 'flex items-center']) }}>
    @foreach($stages as $i => $st)
        @php $state = $st['state'] ?? 'pending'; @endphp
        <div class="flex items-center gap-2.5">
            <span @class([
                'grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-semibold',
                'bg-primary text-primary-fg' => $state === 'active',
                'bg-success text-white' => $state === 'done',
                'bg-danger text-white' => $state === 'failed',
                'bg-line text-muted' => $state === 'pending',
            ])>
                @if($state === 'done')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @elseif($state === 'failed')
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-sm font-medium text-app-text">{{ $st['label'] }}</p>
                @isset($st['count'])<p class="text-xs tabular-nums text-muted">{{ $st['count'] }}</p>@endisset
            </div>
        </div>
        @unless($loop->last)<div class="mx-3 h-px flex-1 bg-line"></div>@endunless
    @endforeach
</div>
