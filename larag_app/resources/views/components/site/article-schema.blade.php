@props(['article'])

{{-- JSON-LD is what search engines actually read for article metadata. The
     flags matter: without them a stray "</script>" or quote in a title would
     break out of the script block. --}}
<script type="application/ld+json">
{!! json_encode(
    \App\Support\ArticleSeo::jsonLd($article),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) !!}
</script>
