<?php

namespace MltStephane\LaravelAnalytics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MltStephane\LaravelAnalytics\Facades\Analytics;

/**
 * Terminable middleware: records a server-side pageview for GET requests.
 * Apply it manually to the routes you want tracked.
 */
class TrackPageview
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        try {
            Analytics::pageview($request->fullUrl());
        } catch (\Throwable $e) {
            Log::warning('analytics: track-pageview middleware failed', ['error' => $e->getMessage()]);
        }
    }
}
