@props(['article'])

@php $cover = $article->coverUrl(); @endphp

<article class="group flex flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200 transition hover:ring-indigo-300 hover:shadow-lg">
    <a href="{{ route('blog.show', $article->slug) }}" class="block aspect-[16/9] overflow-hidden bg-slate-100">
        @if ($cover)
            <img src="{{ $cover }}"
                 alt="{{ $article->cover_alt ?: $article->title }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <span class="flex h-full w-full items-center justify-center bg-gradient-to-br from-indigo-50 to-sky-50">
                <svg class="h-10 w-10 text-indigo-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 15l4-4 3 3 3-3 6 6"/>
                </svg>
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        @if ($article->category)
            <a href="{{ route('blog.category', $article->category->slug) }}"
               class="self-start rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-100 hover:bg-indigo-100">
                {{ $article->category->name }}
            </a>
        @endif

        <h2 class="mt-3 text-lg font-bold leading-snug tracking-tight text-slate-900">
            <a href="{{ route('blog.show', $article->slug) }}" class="hover:text-indigo-700">{{ $article->title }}</a>
        </h2>

        @if ($article->excerpt)
            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ $article->excerpt }}</p>
        @endif

        <x-site.article-meta :article="$article" class="mt-4 pt-4 border-t border-slate-100" />
    </div>
</article>
