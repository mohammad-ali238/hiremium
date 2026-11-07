<x-app-layout :title="$article->title" :seo="$seo">
    @php $cover = $article->coverUrl(); @endphp

    @if (\App\Support\BlogSettings::feedEnabled())
        @push('head')
            <link rel="alternate" type="application/rss+xml"
                  title="{{ \App\Support\BlogSettings::feedTitle() }}"
                  href="{{ route('blog.feed') }}">
        @endpush
    @endif

    @push('head')
        <x-site.article-schema :article="$article" />
    @endpush

    {{-- The author's own CSS, sanitised on save (no '<', no @import, size capped). --}}
    @if (filled($article->custom_css))
        @push('head')
            <style>{!! $article->custom_css !!}</style>
        @endpush
    @endif

    @if ($isPreview)
        <div class="bg-amber-50 border-b border-amber-200">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 text-sm text-amber-900 flex items-center gap-2">
                <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/>
                </svg>
                <span>
                    <strong class="font-semibold">Preview.</strong>
                    {{ $article->published_at?->isFuture()
                        ? 'This article is scheduled for '.$article->published_at->format('M j, Y H:i').'. Nobody else can see it until then.'
                        : 'This article is a draft. Nobody else can see it.' }}
                </span>
            </div>
        </div>
    @endif

    <article>
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
                <nav class="text-sm" aria-label="Breadcrumb">
                    <a href="{{ route('blog.index') }}" class="font-medium text-indigo-600 hover:text-indigo-800">
                        {{ \App\Support\BlogSettings::blogTitle() }}
                    </a>
                    @if ($article->category)
                        <span class="mx-2 text-slate-300">/</span>
                        <a href="{{ route('blog.category', $article->category->slug) }}" class="text-slate-500 hover:text-slate-800">
                            {{ $article->category->name }}
                        </a>
                    @endif
                </nav>

                <h1 class="mt-4 text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">{{ $article->title }}</h1>

                @if ($article->excerpt)
                    <p class="mt-4 text-lg leading-relaxed text-slate-600">{{ $article->excerpt }}</p>
                @endif

                <x-site.article-meta :article="$article" class="mt-6 text-sm" />
            </div>
        </header>

        @if ($cover)
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8">
                <img src="{{ $cover }}"
                     alt="{{ $article->cover_alt ?: $article->title }}"
                     class="w-full rounded-2xl ring-1 ring-slate-200 shadow-sm">
            </div>
        @endif

        <div class="px-4 sm:px-6 lg:px-8 py-12">
            <x-site.article-body>
                {{-- Rendered as written. HTML mode is verbatim unless "Sanitize
                     pasted HTML" is switched on in Blog Settings. --}}
                {!! $article->body() !!}
            </x-site.article-body>
        </div>
    </article>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <a href="{{ route('blog.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01.02 1.06L9.06 10l3.75 3.71a.75.75 0 11-1.06 1.06l-4.25-4.24a.75.75 0 010-1.06l4.25-4.24a.75.75 0 011.04.02z" clip-rule="evenodd"/>
            </svg>
            All articles
        </a>
    </div>
</x-app-layout>
