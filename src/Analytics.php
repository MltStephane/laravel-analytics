<?php

namespace MltStephane\LaravelAnalytics;

use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MltStephane\LaravelAnalytics\Contracts\LocationResolver;
use MltStephane\LaravelAnalytics\Enums\EventType;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Support\Uri;
use MltStephane\LaravelAnalytics\Support\UserAgent;

/**
 * Analytics manager: records pageviews and custom events.
 *
 * It never throws toward the calling request: any failure is logged as a
 * warning and null is returned.
 */
class Analytics
{
    public function __construct(
        protected Request $request,
        protected Config $config,
    ) {}

    /**
     * Record a custom event (server side, e.g. via the Analytics facade).
     */
    public function track(string $name, array $data = [], ?string $url = null): ?Event
    {
        try {
            $visitor = $this->resolveServerVisitor();
            $session = $this->resolveServerSession($visitor, $url);

            $now = Carbon::now();
            $this->touchSession($session, $now);

            return $this->record(
                $visitor,
                $session,
                EventType::Event,
                Str::limit($name, 50),
                $url ?? $this->request->path(),
                null,
                $this->normalizeData($data),
                $now->getTimestamp()
            );
        } catch (\Throwable $e) {
            Log::warning('analytics: failed to track event', ['name' => $name, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Record a pageview (server side, used by the TrackPageview middleware).
     */
    public function pageview(?string $url = null): ?Event
    {
        try {
            $visitor = $this->resolveServerVisitor();
            $session = $this->resolveServerSession($visitor, $url);

            $now = Carbon::now();
            $this->touchSession($session, $now);

            return $this->record(
                $visitor,
                $session,
                EventType::Pageview,
                null,
                $url ?? $this->request->path(),
                null,
                [],
                $now->getTimestamp()
            );
        } catch (\Throwable $e) {
            Log::warning('analytics: failed to record pageview', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Record a payload sent by the browser tracker (CollectController).
     *
     * Payload: type, name, url, title, referrer, hostname, language, screen,
     * data, timestamp, uuid (client id). Context: ip, user_agent, request.
     */
    public function collect(array $payload, array $context): ?Event
    {
        try {
            if ($this->shouldIgnore($context, $payload)) {
                return null;
            }

            $visitor = $this->resolveVisitor($payload['uuid'], $context, $payload);
            $session = $this->resolveSession($visitor, $payload);

            $type = $payload['type'] === 'pageview' ? EventType::Pageview : EventType::Event;

            $now = Carbon::now();
            $this->touchSession($session, $now);

            return $this->record(
                $visitor,
                $session,
                $type,
                $type === EventType::Event ? Str::limit((string) $payload['name'], 50) : null,
                $payload['url'] ?? null,
                $payload['title'] ?? null,
                $this->normalizeData($payload['data'] ?? []),
                isset($payload['timestamp']) ? (int) $payload['timestamp'] : $now->getTimestamp()
            );
        } catch (\Throwable $e) {
            Log::warning('analytics: failed to collect payload', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * True when the payload must not be recorded (bot, ignored IP, ignored path).
     */
    protected function shouldIgnore(array $context, array $payload): bool
    {
        if ($this->config->get('analytics.collect.ignore_bots')) {
            $info = UserAgent::parse($context['user_agent'] ?? null);

            if ($info['is_bot']) {
                return true;
            }
        }

        $ip = $context['ip'] ?? null;

        if ($ip !== null) {
            $ignoreIps = (array) $this->config->get('analytics.collect.ignore_ips', []);

            if (in_array($ip, $ignoreIps, true)) {
                return true;
            }
        }

        $url = $payload['url'] ?? null;

        if ($url !== null) {
            foreach ((array) $this->config->get('analytics.collect.ignore_paths', []) as $pattern) {
                if (preg_match($pattern, $url) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find a visitor by client uuid, or create one with UA/locale/geo info.
     */
    protected function resolveVisitor(string $uuid, array $context, array $payload): Visitor
    {
        $visitor = Visitor::query()->firstWhere('uuid', $uuid);

        $now = Carbon::now();

        if ($visitor === null) {
            $info = UserAgent::parse($context['user_agent'] ?? null);

            $visitor = Visitor::query()->create([
                'uuid' => $uuid,
                'browser' => $info['browser'],
                'browser_version' => $info['browser_version'],
                'os' => $info['os'],
                'device' => $info['device'],
                'device_type' => $info['device_type'],
                'language' => $payload['language'] ?? null,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            $this->applyLocation($visitor, $context['ip'] ?? null);
        } else {
            $visitor->update([
                'language' => $payload['language'] ?? $visitor->language,
                'last_seen_at' => $now,
            ]);
        }

        return $visitor;
    }

    /**
     * Latest session of the visitor, or a new one when it expired.
     */
    protected function resolveSession(Visitor $visitor, array $payload): Session
    {
        $session = $visitor->sessions()
            ->latest('last_activity_at')
            ->first();

        $timeout = (int) $this->config->get('analytics.collect.session_timeout', 30);

        if ($session !== null && $session->last_activity_at !== null
            && $session->last_activity_at->lt(Carbon::now()->subMinutes($timeout))) {
            $session = null;
        }

        if ($session === null) {
            $referrer = $payload['referrer'] ?? null;
            $utm = $this->extractUtm($payload);

            $session = Session::query()->create([
                'visitor_id' => $visitor->id,
                'hostname' => Str::limit((string) ($payload['hostname'] ?? ''), 255),
                'path' => Str::limit((string) ($payload['url'] ?? '/'), 2048),
                'referrer' => $referrer !== null ? Str::limit($referrer, 2048) : null,
                'referrer_domain' => $referrer !== null ? Uri::domainFrom($referrer) : null,
                'utm_source' => $utm['utm_source'],
                'utm_medium' => $utm['utm_medium'],
                'utm_campaign' => $utm['utm_campaign'],
                'utm_term' => $utm['utm_term'],
                'utm_content' => $utm['utm_content'],
                'started_at' => Carbon::now(),
                'last_activity_at' => Carbon::now(),
                'duration' => 0,
                'bounced' => true,
                'pages_count' => 0,
            ]);
        }

        return $session;
    }

    /**
     * Create the event row and update the session metrics.
     *
     * For pageviews: pages_count +1, bounced = pages_count < 2, duration =
     * max(duration, last_activity_at - started_at) when pages_count > 1.
     */
    protected function record(
        Visitor $visitor,
        Session $session,
        EventType $type,
        ?string $name,
        ?string $url,
        ?string $title,
        array $data,
        ?int $timestamp
    ): Event {
        if ($type === EventType::Pageview) {
            $session->increment('pages_count');

            $pagesCount = (int) $session->pages_count;

            $session->update([
                'bounced' => $pagesCount < 2,
            ]);

            if ($pagesCount > 1) {
                $elapsed = (int) $session->last_activity_at?->getTimestamp()
                    - (int) $session->started_at->getTimestamp();
                $session->update([
                    'duration' => max((int) $session->duration, max(0, $elapsed)),
                ]);
            }
        }

        $createdAt = $timestamp !== null
            ? Carbon::createFromTimestamp($timestamp)
            : Carbon::now();

        return Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => $type->value,
            'name' => $name,
            'url' => $url !== null ? Str::limit($url, 2048) : null,
            'title' => $title !== null ? Str::limit($title, 255) : null,
            'data' => $data,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Normalize event data following Umami-like rules:
     * max 50 properties, strings truncated to 500 chars, arrays converted to
     * a truncated string, numbers rounded to 4 decimals.
     */
    protected function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (count($normalized) >= 50) {
                break;
            }

            $normalized[Str::limit((string) $key, 100)] = $this->normalizeValue($value);
        }

        return $normalized;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return Str::limit($value, 500);
        }

        if (is_int($value) || is_float($value)) {
            return round($value, 4);
        }

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 500);
        }

        return Str::limit((string) $value, 500);
    }

    /**
     * Extract UTM parameters from the payload url query string.
     */
    protected function extractUtm(array $payload): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $utm = array_fill_keys($keys, null);

        $query = parse_url((string) ($payload['url'] ?? ''), PHP_URL_QUERY);

        if ($query === false || $query === null) {
            return $utm;
        }

        parse_str($query, $params);

        foreach ($keys as $key) {
            $value = $params[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $utm[$key] = Str::limit($value, 255);
            }
        }

        return $utm;
    }

    /**
     * Server-side visitor: a single shared visitor (no client uuid available).
     */
    protected function resolveServerVisitor(): Visitor
    {
        $uuid = (string) $this->config->get('analytics.server.visitor_uuid', 'server');

        $visitor = Visitor::query()->firstWhere('uuid', $uuid);

        if ($visitor === null) {
            $info = UserAgent::parse($this->request->userAgent());

            $visitor = Visitor::query()->create([
                'uuid' => $uuid,
                'browser' => $info['browser'],
                'browser_version' => $info['browser_version'],
                'os' => $info['os'],
                'device' => $info['device'],
                'device_type' => $info['device_type'],
                'language' => $this->request->getPreferredLanguage() ?: null,
                'first_seen_at' => Carbon::now(),
                'last_seen_at' => Carbon::now(),
            ]);

            $this->applyLocation($visitor, $this->request->ip());
        } else {
            $visitor->update(['last_seen_at' => Carbon::now()]);
        }

        return $visitor;
    }

    /**
     * Server-side session: the latest session of the server visitor, or a new
     * one when it expired.
     */
    protected function resolveServerSession(Visitor $visitor, ?string $url): Session
    {
        $session = $visitor->sessions()
            ->latest('last_activity_at')
            ->first();

        $timeout = (int) $this->config->get('analytics.collect.session_timeout', 30);

        if ($session !== null && $session->last_activity_at !== null
            && $session->last_activity_at->lt(Carbon::now()->subMinutes($timeout))) {
            $session = null;
        }

        if ($session === null) {
            $session = Session::query()->create([
                'visitor_id' => $visitor->id,
                'hostname' => Str::limit((string) $this->request->getHost(), 255),
                'path' => Str::limit((string) ($url ?? $this->request->path()), 2048),
                'referrer' => Str::limit((string) $this->request->headers->get('referer'), 2048) ?: null,
                'referrer_domain' => ($referrer = $this->request->headers->get('referer'))
                    ? Uri::domainFrom($referrer)
                    : null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_term' => null,
                'utm_content' => null,
                'started_at' => Carbon::now(),
                'last_activity_at' => Carbon::now(),
                'duration' => 0,
                'bounced' => true,
                'pages_count' => 0,
            ]);
        }

        return $session;
    }

    protected function touchSession(Session $session, Carbon $now): void
    {
        $session->update([
            'last_activity_at' => $now,
        ]);
    }

    /**
     * Fill country/region/city through the configured LocationResolver.
     */
    protected function applyLocation(Visitor $visitor, ?string $ip): void
    {
        if ($ip === null) {
            return;
        }

        $contract = LocationResolver::class;

        if (! app()->bound($contract)) {
            return;
        }

        $resolver = app($contract);

        if ($resolver === null) {
            return;
        }

        $location = $resolver->resolve($ip);

        if (! is_array($location)) {
            return;
        }

        $visitor->update(array_filter([
            'country' => isset($location['country']) ? substr((string) $location['country'], 0, 2) : null,
            'region' => isset($location['region']) ? Str::limit((string) $location['region'], 100) : null,
            'city' => isset($location['city']) ? Str::limit((string) $location['city'], 100) : null,
        ]));
    }
}
