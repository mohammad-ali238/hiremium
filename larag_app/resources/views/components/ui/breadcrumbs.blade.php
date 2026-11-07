@props(['items' => []])
{{-- :items="[['label'=>'Jobs','url'=>route(...)], ['label'=>'Senior Dev']]" --}}
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'flex items-center gap-1.5 text-sm']) }}>
    @foreach($items as $i => $item)
        @if($i > 0)
            <svg class="h-4 w-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
        @endif
        @if(! empty($item['url']) && $i < count($items) - 1)
            <a href="{{ $item['url'] }}" class="text-muted hover:text-app-text">{{ $item['label'] }}</a>
        @else
            <span class="font-medium text-app-text">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
