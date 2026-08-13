# Laravel Analytics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mltstephane/laravel-analytics.svg?style=flat-square)](https://packagist.org/packages/mltstephane/laravel-analytics)
[![Total Downloads](https://img.shields.io/packagist/dt/mltstephane/laravel-analytics.svg?style=flat-square)](https://packagist.org/packages/mltstephane/laravel-analytics)
[![PHP Version](https://img.shields.io/packagist/php-v/mltstephane/laravel-analytics.svg?style=flat-square)](https://packagist.org/packages/mltstephane/laravel-analytics)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Privacy-first analytics and observability for Laravel: track visitors and user actions — pageviews, sessions, referrers, environments, bounce rate and visit duration — and read everything on a built-in dashboard. No cookies, no personal data, no IP addresses stored. Inspired by [Umami](https://umami.is).

## Features

- **Built-in dashboard** — key metrics, time-series chart, top pages, sources, browsers, OS, devices, countries and events; server-rendered, no external assets (see [Dashboard](#dashboard)).
- **One-line tracking** — `@analytics` Blade directive, `window.analytics.track()` from a ~2 kB vanilla JS tracker, `Analytics::track()` / `Analytics::pageview()` from PHP, or declarative `data-analytics` attributes.
- **Privacy-first** — no cookies, no fingerprinting, no stored IP; visitors are identified by a client-side uuid kept in `localStorage`; Do-Not-Track is honored by default.
- **Sessions** — landing page, referrer domain, UTM parameters, bounce rate and visit duration, with a 30-minute inactivity window.
- **Secure collection endpoint** — POST-only, domain allow-list, per-IP rate limit, bot detection and strictly validated payloads.
- **Pluggable geolocation** — country / region / city through a `LocationResolver` contract (the IP is never stored).
- **Data retention** — `php artisan analytics:prune` deletes events older than `prune.days` (default 365).

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13

## Installation

You can install the package via composer:

```bash
composer require mltstephane/laravel-analytics
```

The service provider and the `Analytics` facade are auto-discovered. Migrations are loaded automatically by the package — just run:

```bash
php artisan migrate
```

Publishing the config or the views is optional:

```bash
php artisan vendor:publish --provider="MltStephane\LaravelAnalytics\AnalyticsServiceProvider" --tag="laravel-config"
php artisan vendor:publish --provider="MltStephane\LaravelAnalytics\AnalyticsServiceProvider" --tag="laravel-views"
```

## Usage

### Quick start

Add the tracker to your layout `<head>`:

```blade
@analytics
```

That's it: pageviews are sent automatically. Open the dashboard at `/analytics` and watch the data arrive.

- Automatic pageviews cover the initial load and SPA navigation (`pushState`, `popstate`, `hashchange`).
- Disable them on a page with `data-auto-track="false"` on the directive output.
- The browser's Do-Not-Track flag is honored by default (`tracker.respect_do_not_track`).

### Tracking events from the browser

The tracker exposes a small global API:

```js
// Custom event
window.analytics.track('signup', { plan: 'pro' });

// Manual pageview
window.analytics.pageview('/blog/my-post', 'My post');

// Attach a stable id to the visitor (max 64 chars)
window.analytics.identify('user-42');
```

You can also track clicks declaratively with data attributes:

```html
<button data-analytics="signup" data-analytics-plan="pro">Sign up</button>
```

Any extra `data-analytics-*` attribute is sent as event data.

### Tracking events from PHP

Use the `Analytics` facade:

```php
use MltStephane\LaravelAnalytics\Facades\Analytics;

Analytics::track('purchase', ['qty' => 2, 'total' => 99.99]);
Analytics::pageview('/blog/my-post');
```

Server-side events are attached to a single shared visitor (config `server.visitor_uuid`).

### Tracking pageviews with a middleware

Register the alias and apply it to the routes you want tracked server-side:

```php
// routes/web.php
Route::middleware('analytics.track-pageview')->get('/blog/{post}', [PostController::class, 'show']);
```

### Event data limits

Event data is normalized server-side (Umami-like rules):

| Data type | Limit |
| --- | --- |
| Properties | max 50 per event |
| Strings | max 500 chars |
| Arrays | converted to string, max 500 chars |
| Numbers | rounded to 4 decimals |
| Event name | max 50 chars |

### Dashboard

The dashboard lives at `/analytics` (configurable prefix and middleware — default `['web', 'auth']`) with periods `24h`, `7d`, `30d`, `90d`:

- **Key metrics** — unique visitors, pageviews, sessions, pageviews per visitor, bounce rate and average visit duration, each compared to the previous period of the same length.
- **Time-series chart** — pageviews and unique visitors per interval, server-rendered SVG with an accessible table and spoken summary; no chart dependency.
- **Content & acquisition** — top pages and sources (pageviews, unique visitors, pages-per-visitor) and top countries (unique visitors).
- **Audience** — unique visitors by device, browser and OS.
- **Events** — top custom events and the 20 most recent events, with type badge and details.

Periods without data are hidden from the period switcher, and clear empty states guide you from the first visit onward.

### Geolocation (custom driver)

Country / region / city are resolved through a pluggable resolver. Implement the contract and point the config to your class:

```php
use MltStephane\LaravelAnalytics\Contracts\LocationResolver;

class IpApiResolver implements LocationResolver
{
    public function resolve(string $ip): ?array
    {
        // return ['country' => 'FR', 'region' => 'Île-de-France', 'city' => 'Paris'] or null
    }
}
```

```php
// config/analytics.php
'geolocation' => [
    'driver' => \App\Support\IpApiResolver::class,
],
```

The IP is only used for the lookup and is never stored.

### Data retention

```bash
php artisan analytics:prune
```

Deletes events older than `prune.days` (default 365, in chunks), then orphaned sessions and visitors.

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch — no route is registered when `false` |
| `collect.uri` | `analytics/collect` | POST endpoint used by the tracker |
| `collect.domains` | `[]` | Allowed referrer/origin domains. Empty = host of `app.url`, `*` = any domain |
| `collect.ignore_bots` | `true` | Skip bot user agents (device-detector) |
| `collect.ignore_paths` | `[]` | Regex list (`preg_match`) of paths that are not tracked |
| `collect.ignore_ips` | `[]` | IPs that are never tracked (private/monitoring) |
| `collect.rate_limit` | `60` | Max requests/minute/IP to the collection endpoint |
| `collect.session_timeout` | `30` | Minutes of inactivity before a visitor starts a new session |
| `dashboard.enabled` | `true` | Enable the dashboard routes |
| `dashboard.prefix` | `analytics` | Dashboard URL prefix |
| `dashboard.middleware` | `['web', 'auth']` | Dashboard middleware stack |
| `tracker.auto_track` | `true` | Automatic pageviews from the JS tracker |
| `tracker.script_path` | `js/tracker.js` | URL path serving the tracker script. Use a neutral filename: blockers (Brave, uBlock) block any URL ending in `analytics.js` |
| `tracker.respect_do_not_track` | `true` | Honor the browser Do-Not-Track flag |
| `geolocation.driver` | `null` | Class implementing `LocationResolver` |
| `prune.days` | `365` | Event retention in days |
| `server.visitor_uuid` | `server` | Visitor uuid used by facade/middleware events |

## Security model

- The collection endpoint is **not** CSRF-protected by design: it is guarded by a domain allow-list (Origin/Referer) and a per-IP rate limit, and it only stores normalized, bounded data.
- All inputs are strictly validated (422 on failure). No user string is ever interpolated into SQL — all queries are parameterized or use internal constants.
- The `Analytics` manager never throws toward the calling request: failures are logged and `null` is returned.

## Testing

```bash
composer test
```

```bash
composer fix
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please report them privately instead of opening a public issue.

## Credits

- [Stephane](https://github.com/MltStephane)
- Inspired by [Umami](https://umami.is)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
