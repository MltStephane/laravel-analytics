<?php

namespace MltStephane\LaravelAnalytics\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;

class PruneAnalyticsData extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Delete analytics events older than the retention period, then orphaned sessions and visitors';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) config('analytics.prune.days', 365));

        $eventsDeleted = 0;

        Event::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$eventsDeleted) {
                $eventsDeleted += count($events);
                $ids = $events->pluck('id');
                Event::query()->whereIn('id', $ids)->delete();
            });

        $sessionsDeleted = Session::query()
            ->whereNotIn('id', DB::table('analytics_events')->select('session_id'))
            ->delete();

        $visitorsDeleted = Visitor::query()
            ->whereNotIn('id', DB::table('analytics_events')->select('visitor_id'))
            ->whereNotIn('id', DB::table('analytics_sessions')->select('visitor_id'))
            ->delete();

        $this->info(sprintf(
            'Analytics data pruned: %d events, %d orphaned sessions, %d orphaned visitors.',
            $eventsDeleted,
            $sessionsDeleted,
            $visitorsDeleted
        ));

        return self::SUCCESS;
    }
}
