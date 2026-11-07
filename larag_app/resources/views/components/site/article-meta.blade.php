@props(['article'])

{{-- Byline, date and reading time. Each part is optional and each can be switched
     off site-wide in Blog Settings, so the separators are built, not hardcoded. --}}
@php
    $bits = [];

    if (\App\Support\BlogSettings::showAuthor() && filled($article->author_name)) {
        $bits[] = e($article->author_name);
    }

    if ($article->published_at) {
        $bits[] = '<time datetime="'.$article->published_at->toAtomString().'">'
            .e($article->published_at->format('M j, Y')).'</time>';
    }

    if (\App\Support\BlogSettings::showReadingTime() && $article->reading_minutes) {
        $bits[] = e($article->reading_minutes.' min read');
    }
@endphp

@if (count($bits) > 0)
    <p {{ $attributes->merge(['class' => 'text-xs text-slate-500']) }}>
        {!! implode(' <span class="text-slate-300">&middot;</span> ', $bits) !!}
    </p>
@endif
