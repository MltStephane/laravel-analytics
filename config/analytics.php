<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global switch
    |--------------------------------------------------------------------------
    |
    | When disabled, no route is registered and nothing is tracked.
    |
    */

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Collection
    |--------------------------------------------------------------------------
    |
    | uri          : POST endpoint used by the tracker script.
    | domains      : allowed referrer/origin domains. Empty = the host of
    |                config('app.url') only. Add "*" to allow any domain.
    | ignore_bots  : skip requests detected as bots (device-detector).
    | ignore_paths : regex list (preg_match) of paths that are not tracked.
    | ignore_ips   : IP addresses that are never tracked (private/monitoring).
    | rate_limit   : max requests per minute per IP to the collection endpoint.
    | session_timeout : minutes of inactivity before a visitor starts a new session.
    |
    */

    'collect' => [
        'uri' => 'analytics/collect',
        'domains' => [],
        'ignore_bots' => true,
        'ignore_paths' => [],
        'ignore_ips' => [],
        'rate_limit' => 60,
        'session_timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    */

    'dashboard' => [
        'enabled' => true,
        'prefix' => 'analytics',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser tracker
    |--------------------------------------------------------------------------
    |
    */

    'tracker' => [
        'auto_track' => true,
        'respect_do_not_track' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation
    |--------------------------------------------------------------------------
    |
    | Class implementing MltStephane\LaravelAnalytics\Contracts\LocationResolver.
    | Null = no geolocation (country/region/city stay null).
    |
    */

    'geolocation' => [
        'driver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | analytics:prune removes events older than this many days, then the
    | orphaned sessions and visitors.
    |
    */

    'prune' => [
        'days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side events
    |--------------------------------------------------------------------------
    |
    | visitor_uuid : stable uuid used as the visitor id for events recorded
    |                through the Analytics facade / TrackPageview middleware.
    |
    */

    'server' => [
        'visitor_uuid' => 'server',
    ],

];
