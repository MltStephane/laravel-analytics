<?php

namespace MltStephane\LaravelAnalytics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MltStephane\LaravelAnalytics\Enums\EventType;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;

/**
 * Aggregated dashboard queries. Static, repository-style.
 */
class DashboardService
{
    /**
     * @return array{
     *     period: string,
     *     from: Carbon,
     *     to: Carbon,
     *     visitors: int,
     *     pageviews: int,
     *     viewsPerVisit: float,
     *     bounceRate: float,
     *     avgDuration: int,
     *     timeSeries: array<int, array{label: string, pageviews: int, visitors: int}>,
     *     topPages: Collection,
     *     topSources: Collection,
     *     topBrowsers: Collection,
     *     topOs: Collection,
     *     topDevices: Collection,
     *     topCountries: Collection,
     *     topEvents: Collection,
     *     recentEvents: Collection
     * }
     */
    public static function overview(string $period, int $limit = 10): array
    {
        [$from, $to] = self::periodRange($period);

        $pageviews = Event::query()
            ->ofType(EventType::Pageview)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $visitors = Event::query()
            ->whereBetween('created_at', [$from, $to])
            ->distinct()
            ->count('visitor_id');

        $bounceStats = Session::query()
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN bounced = 1 THEN 1 ELSE 0 END) as bounced')
            ->first();

        $bounceTotal = (int) ($bounceStats->total ?? 0);
        $bounced = (int) ($bounceStats->bounced ?? 0);

        $avgDuration = (int) (Session::query()
            ->whereBetween('started_at', [$from, $to])
            ->avg('duration') ?? 0);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'visitors' => $visitors,
            'pageviews' => $pageviews,
            'viewsPerVisit' => $visitors > 0 ? round($pageviews / $visitors, 1) : 0.0,
            'bounceRate' => $bounceTotal > 0 ? round($bounced / $bounceTotal * 100, 1) : 0.0,
            'avgDuration' => $avgDuration,
            'timeSeries' => self::timeSeries($period, $from, $to),
            'topPages' => self::topPages($from, $to, $limit),
            'topSources' => self::topSources($from, $to, $limit),
            'topBrowsers' => self::topGrouped('analytics_visitors.browser', $from, $to, $limit),
            'topOs' => self::topGrouped('analytics_visitors.os', $from, $to, $limit),
            'topDevices' => self::topDevices($from, $to, $limit),
            'topCountries' => self::topCountries($from, $to, $limit),
            'topEvents' => self::topEvents($from, $to, $limit),
            'recentEvents' => Event::query()
                ->with(['visitor', 'session'])
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    protected static function periodRange(string $period): array
    {
        $to = Carbon::now();

        $from = match ($period) {
            '24h' => $to->copy()->subHours(24),
            '30d' => $to->copy()->subDays(30),
            '90d' => $to->copy()->subDays(90),
            default => $to->copy()->subDays(7),
        };

        return [$from, $to];
    }

    /**
     * @return array<int, array{label: string, pageviews: int, visitors: int}>
     */
    protected static function timeSeries(string $period, Carbon $from, Carbon $to): array
    {
        $hourly = $period === '24h';
        $expression = self::bucketExpression($hourly ? 'hour' : 'day');

        $pageviews = DB::table('analytics_events')
            ->where('type', 'pageview')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("{$expression} as bucket, COUNT(*) as count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('count', 'bucket');

        $visitors = DB::table('analytics_events')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("{$expression} as bucket, COUNT(DISTINCT visitor_id) as count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('count', 'bucket');

        $series = [];

        // The series ends at the last complete bucket: the current partial
        // hour/day is left out (totals still cover the full period).
        $end = $hourly ? $to->copy()->startOfHour() : $to->copy()->startOfDay();

        if ($hourly) {
            $cursor = $from->copy()->startOfHour();
            $format = 'Y-m-d H:00';

            while ($cursor->lt($end)) {
                $key = $cursor->format($format);
                $series[] = [
                    'label' => $cursor->format('H:i'),
                    'pageviews' => (int) ($pageviews[$key] ?? 0),
                    'visitors' => (int) ($visitors[$key] ?? 0),
                ];
                $cursor->addHour();
            }
        } else {
            $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
            $cursor = $from->copy()->startOfDay();
            $format = 'Y-m-d';

            while ($cursor->lt($end)) {
                $key = $cursor->format($format);
                $series[] = [
                    'label' => $cursor->format('j').' '.$months[$cursor->month - 1],
                    'pageviews' => (int) ($pageviews[$key] ?? 0),
                    'visitors' => (int) ($visitors[$key] ?? 0),
                ];
                $cursor->addDay();
            }
        }

        return $series;
    }

    /**
     * Dialect-aware bucket expression (sqlite vs mysql/postgres).
     */
    protected static function bucketExpression(string $granularity): string
    {
        $driver = DB::connection()->getDriverName();

        if ($granularity === 'hour') {
            return $driver === 'sqlite'
                ? "strftime('%Y-%m-%d %H:00', created_at)"
                : "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')";
        }

        return $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-%d')";
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topPages(Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->where('type', 'pageview')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('url, COUNT(*) as pageviews, COUNT(DISTINCT visitor_id) as visitors')
            ->whereNotNull('url')
            ->groupBy('url')
            ->orderByDesc('pageviews')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topSources(Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->join('analytics_sessions', 'analytics_sessions.id', '=', 'analytics_events.session_id')
            ->where('analytics_events.type', 'pageview')
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->selectRaw("COALESCE(NULLIF(analytics_sessions.referrer_domain, ''), '(direct)') as source, COUNT(*) as pageviews")
            ->groupBy('source')
            ->orderByDesc('pageviews')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topGrouped(string $column, Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->join('analytics_visitors', 'analytics_visitors.id', '=', 'analytics_events.visitor_id')
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->selectRaw("COALESCE(NULLIF({$column}, ''), '(inconnu)') as label, COUNT(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topDevices(Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->join('analytics_visitors', 'analytics_visitors.id', '=', 'analytics_events.visitor_id')
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->selectRaw(
                "CASE analytics_visitors.device_type
                    WHEN 'desktop' THEN 'Ordinateur'
                    WHEN 'smartphone' THEN 'Mobile'
                    WHEN 'tablet' THEN 'Tablette'
                    ELSE '(inconnu)'
                END as label, COUNT(*) as count"
            )
            ->groupBy('analytics_visitors.device_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topCountries(Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->join('analytics_visitors', 'analytics_visitors.id', '=', 'analytics_events.visitor_id')
            ->whereBetween('analytics_events.created_at', [$from, $to])
            ->selectRaw('analytics_visitors.country as code, COUNT(DISTINCT analytics_events.visitor_id) as visitors')
            ->whereNotNull('analytics_visitors.country')
            ->groupBy('analytics_visitors.country')
            ->orderByDesc('visitors')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    protected static function topEvents(Carbon $from, Carbon $to, int $limit): Collection
    {
        return DB::table('analytics_events')
            ->where('type', 'event')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('name, COUNT(*) as count')
            ->whereNotNull('name')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }
}
