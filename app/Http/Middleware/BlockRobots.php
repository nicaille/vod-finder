<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockRobots
{
    public function handle(Request $request, Closure $next)
    {
        // Keep the exclusion policy readable by crawlers. User-Agent is a signal, not authentication.
        if ($request->is('robots.txt')) return $next($request);
        $agent = substr($request->userAgent() ?? '', 0, 2048);
        if (preg_match('/(?:GPTBot|ChatGPT-User|OAI-SearchBot|ClaudeBot|Claude-User|Claude-SearchBot|anthropic-ai|cohere-ai|CCBot|Bytespider|PerplexityBot|Perplexity-User|Amazonbot|Applebot|Googlebot|Google-Extended|GoogleOther|bingbot|BingPreview|DuckDuckBot|YandexBot|Baiduspider|PetalBot|Meta-ExternalAgent|Meta-ExternalFetcher|FacebookBot|facebookexternalhit|SemrushBot|AhrefsBot|MJ12bot|DotBot|DataForSeoBot|Diffbot|ImagesiftBot|omgili)/i', $agent)) {
            return response('Accès automatisé interdit.', 403)->header('Cache-Control', 'private, no-store');
        }
        return $next($request);
    }
}
