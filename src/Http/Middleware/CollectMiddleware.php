<?php

namespace MltStephane\LaravelAnalytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Guards the public collection endpoint.
 *
 * - POST only.
 * - Rate limited (analytics-collect limiter, per IP, 429 when exceeded).
 * - Domain check against the Origin/Referer header:
 *     allowed domains  = config('analytics.collect.domains')
 *                        or the host of config('app.url') when the list is empty.
 *     Origin/Referer present and not allowed          -> 403
 *     Origin/Referer absent (curl, other servers)     -> allowed when domains
 *                        is empty, 403 when a domain list is configured.
 */
class CollectMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! $request->isMethod('POST')) {
            return response()->json(['message' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $key = 'analytics-collect:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) config('analytics.collect.rate_limit', 60))) {
            return response()->json(['message' => 'Too many requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 60);

        if (! $this->isAllowedOrigin($request)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    protected function isAllowedOrigin(Request $request): bool
    {
        $configuredDomains = array_filter(array_map('trim', (array) config('analytics.collect.domains', [])));

        $allowedDomains = $configuredDomains !== []
            ? $configuredDomains
            : [parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost()];

        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');

        // No Origin/Referer (curl, server-to-server calls): allowed only when
        // no explicit domain list is configured.
        if ($origin === null || $origin === '') {
            return $configuredDomains === [];
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if ($host === false || $host === null) {
            return false;
        }

        foreach ($allowedDomains as $domain) {
            if (Str::lower($host) === Str::lower($domain)) {
                return true;
            }
        }

        return false;
    }
}
