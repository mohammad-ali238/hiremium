{{-- Shown ONLY inside an impersonated session: nothing renders for a normal
     user, so the feature stays invisible to anyone who isn't an admin using it. --}}
@php($impersonating = \App\Support\Impersonation::isActive() && auth()->check())

@if ($impersonating)
    {{-- Flow spacer so the fixed bar never covers the bottom of the page. --}}
    <div class="h-16" aria-hidden="true"></div>

    <div class="fixed inset-x-0 bottom-0 z-[100] border-t border-amber-400/60 bg-slate-900 text-white shadow-[0_-4px_16px_rgba(0,0,0,0.25)]">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <p class="text-sm">
                <span class="mr-2 inline-flex items-center rounded-full bg-amber-400/20 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-amber-300">
                    Admin view
                </span>
                You are logged in as
                <strong class="font-semibold">{{ auth()->user()->name }}</strong>
                <span class="text-slate-300">({{ auth()->user()->email }})</span>
            </p>

            <form method="POST" action="{{ route('impersonate.stop') }}" class="shrink-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-400 px-3 py-2 text-sm font-semibold text-slate-900 transition hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-200">
                    Login back as admin
                </button>
            </form>
        </div>
    </div>
@endif
