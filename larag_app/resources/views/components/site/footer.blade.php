<footer class="bg-slate-900 text-slate-300 mt-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">

            <div class="col-span-2">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
                    <img src="{{ asset('logo.svg') }}" alt="Hiremium" class="h-8 w-auto bg-white rounded px-2 py-1" />
                </a>
                <p class="mt-4 text-base font-semibold text-white">Smarter Hiring. Powered by AI.</p>
                <p class="mt-2 max-w-sm text-sm text-slate-400">
                    Bulk-upload CVs and get your best-matched candidates in minutes —
                    automatic screening with fair, AI-usage-based pricing.
                </p>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-white">Product</h4>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('features.matching') }}" class="hover:text-white">AI Match Scoring</a></li>
                    <li><a href="{{ route('features.parsing') }}" class="hover:text-white">AI Resume Parsing</a></li>
                    <li><a href="{{ route('docs') }}" class="hover:text-white">API Docs</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                    <li><a href="{{ route('how-it-works') }}" class="hover:text-white">How it works</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">Get started</a></li>
                </ul>
            </div>

            <div>
                <h4 class="text-sm font-semibold text-white">Company</h4>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('privacy') }}" class="hover:text-white">Privacy Policy</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-white">Terms of Service</a></li>
                    <li><a href="{{ route('refund') }}" class="hover:text-white">Refund Policy</a></li>
                    <li><a href="{{ route('help') }}" class="hover:text-white">Help</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white">About</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white">Contact us</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 pt-6 border-t border-slate-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <p class="text-sm text-slate-400">
                &copy; {{ date('Y') }} Hiremium. All rights reserved.
            </p>
            <div class="flex items-center gap-4 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    Built with
                    <svg class="h-4 w-4 text-rose-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                    </svg>
                    love
                </span>
            </div>
        </div>
    </div>
</footer>
