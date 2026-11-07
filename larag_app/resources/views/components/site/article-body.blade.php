{{--
    The long-form wrapper for an article body.

    Styling is done with arbitrary variants on this one element, the same way
    <x-site.legal-page> does it, NOT with `prose`: @tailwindcss/typography is
    not installed, and adding it would retroactively restyle the pages that
    already carry dead `prose` classes.

    The id is the anchor an author scopes their per-article CSS to.
--}}
<div id="article-body"
     class="max-w-3xl mx-auto
            [&>*:first-child]:!mt-0
            [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-900 [&_h2]:mt-12 [&_h2]:scroll-mt-24
            [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-900 [&_h3]:mt-8 [&_h3]:scroll-mt-24
            [&_h4]:text-base [&_h4]:font-semibold [&_h4]:text-slate-900 [&_h4]:mt-6
            [&_p]:mt-4 [&_p]:text-slate-600 [&_p]:leading-relaxed
            [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_ul]:text-slate-600
            [&_ol]:mt-4 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2 [&_ol]:text-slate-600
            [&_li]:leading-relaxed [&_li]:marker:text-indigo-400
            [&_a]:text-indigo-600 [&_a]:font-medium [&_a]:underline hover:[&_a]:text-indigo-800
            [&_strong]:text-slate-900 [&_strong]:font-semibold
            [&_blockquote]:mt-6 [&_blockquote]:border-l-4 [&_blockquote]:border-indigo-200 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-slate-600
            [&_img]:mt-6 [&_img]:rounded-xl [&_img]:ring-1 [&_img]:ring-slate-200 [&_img]:max-w-full [&_img]:h-auto
            [&_figure]:mt-6 [&_figcaption]:mt-2 [&_figcaption]:text-sm [&_figcaption]:text-slate-500 [&_figcaption]:text-center
            [&_hr]:my-10 [&_hr]:border-slate-200
            [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-[0.9em] [&_code]:text-slate-800
            [&_pre]:mt-6 [&_pre]:overflow-x-auto [&_pre]:rounded-xl [&_pre]:bg-slate-900 [&_pre]:p-4 [&_pre]:text-sm [&_pre]:text-slate-100
            [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-inherit
            [&_iframe]:mt-6 [&_iframe]:w-full [&_iframe]:rounded-xl [&_iframe]:aspect-video
            [&_table]:mt-6 [&_table]:w-full [&_table]:text-left [&_table]:text-sm [&_table]:text-slate-600
            [&_th]:border-b [&_th]:border-slate-200 [&_th]:py-2 [&_th]:pr-4 [&_th]:font-semibold [&_th]:text-slate-900
            [&_td]:border-b [&_td]:border-slate-100 [&_td]:py-2 [&_td]:pr-4">
    {{-- Wide content (tables, code) scrolls inside itself rather than the page. --}}
    {{ $slot }}
</div>
