<?php

namespace MltStephane\LaravelAnalytics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MltStephane\LaravelAnalytics\Enums\EventType;
use MltStephane\LaravelAnalytics\Models\Event;

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
     *     sessions: int,
     *     viewsPerVisit: float,
     *     bounceRate: float,
     *     avgDuration: int,
     *     comparison: array<string, array{current: int|float, previous: int|float, hasPrevious: bool, change: float|null}>,
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

        $current = self::periodMetrics($from, $to);
        $periodSeconds = $from->diffInSeconds($to);
        $previousTo = $from->copy();
        $previousFrom = $previousTo->copy()->subSeconds($periodSeconds);
        $previous = self::periodMetrics($previousFrom, $previousTo, false);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'visitors' => $current['visitors'],
            'pageviews' => $current['pageviews'],
            'sessions' => $current['sessions'],
            'viewsPerVisit' => $current['viewsPerVisit'],
            'bounceRate' => $current['bounceRate'],
            'avgDuration' => $current['avgDuration'],
            'comparison' => [
                'visitors' => self::comparison($current['visitors'], $previous['visitors'], $previous['visitors'] > 0),
                'pageviews' => self::comparison($current['pageviews'], $previous['pageviews'], $previous['pageviews'] > 0),
                'sessions' => self::comparison($current['sessions'], $previous['sessions'], $previous['sessions'] > 0),
                'viewsPerVisit' => self::comparison($current['viewsPerVisit'], $previous['viewsPerVisit'], $previous['visitors'] > 0),
                'bounceRate' => self::comparison($current['bounceRate'], $previous['bounceRate'], $previous['sessions'] > 0),
                'avgDuration' => self::comparison($current['avgDuration'], $previous['avgDuration'], $previous['sessions'] > 0),
            ],
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
     * @return array{visitors: int, pageviews: int, sessions: int, viewsPerVisit: float, bounceRate: float, avgDuration: int}
     */
    protected static function periodMetrics(Carbon $from, Carbon $to, bool $includeTo = true): array
    {
        $pageviewsQuery = Event::query()->ofType(EventType::Pageview);
        self::applyPeriod($pageviewsQuery, 'created_at', $from, $to, $includeTo);
        $pageviews = $pageviewsQuery->count();

        $visitorsQuery = Event::query();
        self::applyPeriod($visitorsQuery, 'created_at', $from, $to, $includeTo);
        $visitors = $visitorsQuery->distinct()->count('visitor_id');

        // Sessions of the shared "server" visitor (Analytics facade /
        // TrackPageview middleware) are excluded from session-level metrics:
        // every server request is attached to that single visitor, which keeps
        // its session open continuously and would dominate duration/bounce.
        $excludeServerVisitor = function ($query) use ($from, $to, $includeTo) {
            self::applyPeriod($query, 'started_at', $from, $to, $includeTo);

            $query->whereNotIn('visitor_id', function ($sub) {
                $sub->select('id')
                    ->from('analytics_visitors')
                    ->where('uuid', (string) config('analytics.server.visitor_uuid', 'server'));
            });
        };

        $sessionsQuery = DB::table('analytics_sessions');
        $excludeServerVisitor($sessionsQuery);

        $sessionStats = $sessionsQuery
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN bounced = 1 THEN 1 ELSE 0 END) as bounced')
            ->first();

        $sessions = (int) ($sessionStats->total ?? 0);
        $bounced = (int) ($sessionStats->bounced ?? 0);

        $durationQuery = DB::table('analytics_sessions');
        $excludeServerVisitor($durationQuery);

        $avgDuration = max(0, (int) round(
            $durationQuery->avg(DB::raw(self::durationExpression($to))) ?? 0
        ));

        return [
            'visitors' => $visitors,
            'pageviews' => $pageviews,
            'sessions' => $sessions,
            'viewsPerVisit' => $visitors > 0 ? round($pageviews / $visitors, 1) : 0.0,
            'bounceRate' => $sessions > 0 ? round($bounced / $sessions * 100, 1) : 0.0,
            'avgDuration' => $avgDuration,
        ];
    }

    /**
     * @return array{current: int|float, previous: int|float, hasPrevious: bool, change: float|null}
     */
    protected static function comparison(int|float $current, int|float $previous, bool $hasPreviousData): array
    {
        $change = ! $hasPreviousData || (float) $previous === 0.0
            ? null
            : round(($current - $previous) / $previous * 100, 1);

        return [
            'current' => $current,
            'previous' => $previous,
            'hasPrevious' => $hasPreviousData,
            'change' => $change,
        ];
    }

    protected static function applyPeriod($query, string $column, Carbon $from, Carbon $to, bool $includeTo): void
    {
        if ($includeTo) {
            $query->whereBetween($column, [$from, $to]);

            return;
        }

        $query
            ->where($column, '>=', $from)
            ->where($column, '<', $to);
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
            return match ($driver) {
                'sqlite' => "strftime('%Y-%m-%d %H:00', created_at)",
                'pgsql' => "to_char(created_at, 'YYYY-MM-DD HH24:00')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')",
            };
        }

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM-DD')",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
        };
    }

    /**
     * Dialect-aware session duration expression (sqlite vs mysql/postgres).
     * Duration in seconds between the first activity and the end of the
     * analyzed period (a session still active at the cutoff is not credited
     * beyond it).
     */
    protected static function durationExpression(Carbon $to): string
    {
        $end = $to->toDateTimeString();

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday(MIN(last_activity_at, '{$end}')) - julianday(started_at)) * 86400",
            'pgsql' => "EXTRACT(EPOCH FROM (LEAST(last_activity_at, '{$end}') - started_at))",
            default => "TIMESTAMPDIFF(SECOND, started_at, LEAST(last_activity_at, '{$end}'))",
        };
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
            ->get()
            ->each(function ($row) {
                $row->pagesPerVisitor = self::pagesPerVisitor($row->pageviews, $row->visitors);
            });
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
            ->selectRaw("COALESCE(NULLIF(analytics_sessions.referrer_domain, ''), '(direct)') as source, COUNT(*) as pageviews, COUNT(DISTINCT analytics_events.visitor_id) as visitors")
            ->groupBy('source')
            ->orderByDesc('pageviews')
            ->limit($limit)
            ->get()
            ->each(function ($row) {
                $row->pagesPerVisitor = self::pagesPerVisitor($row->pageviews, $row->visitors);
            });
    }

    protected static function pagesPerVisitor(int|float $pageviews, int|float $visitors): float
    {
        return (float) $visitors > 0 ? round($pageviews / $visitors, 1) : 0.0;
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
                END as label, COUNT(DISTINCT analytics_events.visitor_id) as count"
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
