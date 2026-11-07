<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\AiSetting;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Tools\ToolRegistry;
use App\Support\BlogSettings;
use App\Support\SiteSettings;
use App\Support\ToolSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function home(): View
    {
        return view('welcome', [
            'plans' => $this->activePlans(),
            'parsingCosts' => $this->parsingCosts(),
            'currentSubscription' => $this->currentSubscription(),
            'billingEnabled' => BillingSetting::current()->enabled,
            // The home page ends with the same contact form as the contact page.
            'topics' => ContactRequest::topics(),
            'recaptchaEnabled' => SiteSettings::contactRecaptchaEnabled(),
            'recaptchaSiteKey' => SiteSettings::recaptchaSiteKey(),
        ]);
    }

    public function howItWorks(): View
    {
        return view('pages.how-it-works');
    }

    public function featuresMatching(): View
    {
        return view('pages.features-matching');
    }

    public function featuresParsing(): View
    {
        return view('pages.features-parsing', [
            'parsingCosts' => $this->parsingCosts(),
        ]);
    }

    public function docs(): View
    {
        $settings = AiSetting::current();

        return view('pages.docs', [
            'parsingCosts' => $this->parsingCosts(),
            'parsingMaxKb' => (int) $settings->parsing_max_file_size_kb,
        ]);
    }

    public function pricing(): View
    {
        return view('pages.pricing', [
            'plans' => $this->activePlans(),
            'parsingCosts' => $this->parsingCosts(),
            'currentSubscription' => $this->currentSubscription(),
            'billingEnabled' => BillingSetting::current()->enabled,
        ]);
    }

    /**
     * The current user's active subscription (with plan) — used by the pricing
     * CTAs to show Current / Upgrade / Lower states per billing interval.
     */
    private function currentSubscription(): ?Subscription
    {
        return request()->user()?->activeSubscription()->with('plan')->first();
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function refund(): View
    {
        return view('pages.refund');
    }

    public function help(): View
    {
        return view('pages.help', [
            'parsingCosts' => $this->parsingCosts(),
        ]);
    }

    public function contact(): View
    {
        return view('pages.contact', [
            'topics' => ContactRequest::topics(),
            'recaptchaEnabled' => SiteSettings::contactRecaptchaEnabled(),
            'recaptchaSiteKey' => SiteSettings::recaptchaSiteKey(),
        ]);
    }

    /**
     * XML sitemap of all public pages, kept in sync with the SEO page list.
     */
    public function sitemap(): Response
    {
        $priorities = [
            'home' => '1.0',
            'features.matching' => '0.8',
            'features.parsing' => '0.8',
            'docs' => '0.8',
            'pricing' => '0.8',
            'about' => '0.5',
            'contact' => '0.5',
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach (array_keys(SiteSettings::SEO_ROUTES) as $routeName) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e(route($routeName))."</loc>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= '    <priority>'.($priorities[$routeName] ?? '0.5')."</priority>\n";
            $xml .= "  </url>\n";
        }

        // Free tool pages belong in the index; their RESULT pages never do —
        // each one is somebody's private report behind a shareable link.
        if (ToolSettings::enabled()) {
            $tools = array_merge(
                [route('tools.index')],
                array_map(
                    fn ($tool) => route('tools.show', $tool->slug()),
                    array_values(app(ToolRegistry::class)->enabled())
                ),
            );

            foreach ($tools as $url) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.e($url)."</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.7</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        // The blog: the index, the archives someone could land on, and every
        // published article. Articles carry <lastmod> because they are the only
        // URLs here that actually change after they are first published.
        if (BlogSettings::enabled()) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.e(route('blog.index'))."</loc>\n";
            $xml .= "    <changefreq>daily</changefreq>\n";
            $xml .= "    <priority>0.7</priority>\n";
            $xml .= "  </url>\n";

            $categories = ArticleCategory::query()
                ->whereHas('articles', fn ($query) => $query->published())
                ->orderBy('name')
                ->get(['slug']);

            foreach ($categories as $category) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.e(route('blog.category', $category->slug))."</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.5</priority>\n";
                $xml .= "  </url>\n";
            }

            $articles = Article::published()
                ->select(['slug', 'updated_at'])
                ->latest('published_at')
                ->get();

            foreach ($articles as $article) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.e(route('blog.show', $article->slug))."</loc>\n";
                $xml .= '    <lastmod>'.$article->updated_at->toAtomString()."</lastmod>\n";
                $xml .= "    <changefreq>monthly</changefreq>\n";
                $xml .= "    <priority>0.6</priority>\n";
                $xml .= "  </url>\n";
            }
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt pointing crawlers at the sitemap and away from private areas.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /profile',
            'Disallow: /horizon',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /api/',
            // Tool pages are public; the result links behind them are not.
            'Disallow: /tools/result',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function activePlans()
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_public', true)   // hidden plans are admin-assign only
            ->orderBy('price')
            ->get();
    }

    /**
     * Live per-call credit prices shown on public pricing blocks.
     *
     * @return array{text: int, vision: int}
     */
    private function parsingCosts(): array
    {
        $settings = AiSetting::current();

        return [
            'text' => (int) $settings->parsing_credit_cost_text,
            'vision' => (int) $settings->parsing_credit_cost_vision,
        ];
    }
}
