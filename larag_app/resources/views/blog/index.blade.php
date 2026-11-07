{{-- Serves both /blog and /blog/category/{slug}: same page, one is filtered. --}}
<x-app-layout :title="$title" :seo="$seo">
    @if (\App\Support\BlogSettings::feedEnabled())
        @push('head')
            <link rel="alternate" type="application/rss+xml"
                  title="{{ \App\Support\BlogSettings::feedTitle() }}"
                  href="{{ route('blog.feed') }}">
        @endpush
    @endif

    <section class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            @if ($category)
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                    <a href="{{ route('blog.index') }}" class="hover:text-indigo-800">{{ \App\Support\BlogSettings::blogTitle() }}</a>
                </p>
                <h1 class="mt-2 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">{{ $category->name }}</h1>
                @if ($category->description)
                    <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">{{ $category->description }}</p>
                @endif
            @else
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900">{{ $title }}</h1>
                @if ($intro = \App\Support\BlogSettings::blogIntro())
                    <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">{{ $intro }}</p>
                @endif
            @endif
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">
            <nav class="flex flex-wrap items-center gap-2" aria-label="Categories">
                <a href="{{ route('blog.index') }}"
                   class="rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition
                          {{ $category === null ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-slate-600 ring-slate-200 hover:text-slate-900 hover:ring-slate-300' }}">
                    All
                </a>
                @foreach ($categories as $item)
                    <a href="{{ route('blog.category', $item->slug) }}"
                       class="rounded-full px-3 py-1.5 text-sm font-medium ring-1 transition
                              {{ $category?->is($item) ? 'bg-indigo-600 text-white ring-indigo-600' : 'bg-white text-slate-600 ring-slate-200 hover:text-slate-900 hover:ring-slate-300' }}">
                        {{ $item->name }}
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        @if ($articles->isEmpty())
            <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-12 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 4h9l3 3v13H6z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 11h6M9 15h4"/>
                </svg>
                <p class="mt-4 font-semibold text-slate-900">Nothing published yet</p>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $category ? 'There are no articles in this category yet.' : 'New articles will appear here.' }}
                </p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <x-site.article-card :article="$article" />
                @endforeach
            </div>

            @if ($articles->hasPages())
                <div class="mt-10">{{ $articles->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
