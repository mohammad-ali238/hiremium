<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a page out of search results.
 *
 * The meta tag alone is not enough for a page that gets shared around: a
 * crawler that reaches it through a link, a preview service, or a cached copy
 * may never parse the HTML. The header says the same thing at the transport
 * level, where every crawler is obliged to look.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
