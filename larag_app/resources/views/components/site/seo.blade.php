@props(['title' => null, 'seo' => null])

@php
    // A caller may hand in its own meta (free tool pages, whose SEO is stored
    // per tool rather than per route; blog pages, whose SEO is per article);
    // otherwise look it up by route name.
    $seo = $seo ?? \App\Support\SiteSettings::seoForRoute(request()->route()?->getName());

    $pageTitle = $seo['title']
        ?? ($title ? $title.' — '.config('app.name') : config('app.name', 'Hiremium'));

    $description = $seo['description'] ?? '';
    $keywords = $seo['keywords'] ?? '';

    $image = $seo['image'] ?? '';
    if ($seo !== null && $image === '') {
        $image = asset('images/og-default.png');
    }

    // Everything below is optional and defaults to what this component has
    // always emitted, so the pages that pass no meta of their own are unchanged.
    $type = $seo['type'] ?? 'website';
    $canonical = $seo['canonical'] ?? url()->current();
    $robots = $seo['robots'] ?? '';
    $twitterCard = $seo['twitter_card'] ?? 'summary_large_image';
    $ogTitle = $seo['og_title'] ?? $pageTitle;
    $ogDescription = $seo['og_description'] ?? $description;
@endphp

<title>{{ $pageTitle }}</title>

@if ($seo !== null)
    {{-- An empty description tag is worse than no description tag. --}}
    @if ($description !== '')
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($keywords !== '')
        <meta name="keywords" content="{{ $keywords }}">
    @endif
    @if ($robots !== '')
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:site_name" content="{{ config('app.name', 'Hiremium') }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">

    @if ($type === 'article')
        @if (($seo['published_time'] ?? '') !== '')
            <meta property="article:published_time" content="{{ $seo['published_time'] }}">
        @endif
        @if (($seo['modified_time'] ?? '') !== '')
            <meta property="article:modified_time" content="{{ $seo['modified_time'] }}">
        @endif
        @if (($seo['section'] ?? '') !== '')
            <meta property="article:section" content="{{ $seo['section'] }}">
        @endif
    @endif

    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $image }}">
@endif
