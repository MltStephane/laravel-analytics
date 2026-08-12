<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Illuminate\Support\Carbon;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Services\DashboardService;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class DashboardMetricsRegressionTest extends TestCase
{
    public function test_bounce_rate_counts_every_bounced_session(): void
    {
        $now = Carbon::now();

        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-bounce',
            'first_seen_at' => $now->copy()->subDays(3),
            'last_seen_at' => $now,
        ]);

        // 4 sessions in-window, 2 bouncées, 2 non.
        foreach (['/a', '/b', '/c', '/d'] as $i => $path) {
            Session::query()->create([
                'visitor_id' => $visitor->id,
                'hostname' => 'example.test',
                'path' => $path,
                'started_at' => $now->copy()->subDays($i),
                'last_activity_at' => $now->copy()->subDays($i),
                'duration' => 0,
                'bounced' => $i < 2,
                'pages_count' => $i < 2 ? 1 : 2,
            ]);
        }

        $overview = DashboardService::overview('7d');

        $this->assertSame(50.0, $overview['bounceRate']);
    }

    public function test_avg_duration_uses_first_to_last_activity(): void
    {
        $now = Carbon::now();

        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-duration',
            'first_seen_at' => $now->copy()->subMinutes(10),
            'last_seen_at' => $now,
        ]);

        // Session démarrée il y a 10 min, dernière activité il y a 5 min.
        // La colonne duration est figée (valeur erronée) mais les timestamps
        // reflètent la vraie activité : 300 secondes.
        Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/home',
            'started_at' => $now->copy()->subMinutes(10),
            'last_activity_at' => $now->copy()->subMinutes(5),
            'duration' => 30,
            'bounced' => false,
            'pages_count' => 2,
        ]);

        $overview = DashboardService::overview('7d');

        $this->assertSame(300, $overview['avgDuration']);
    }

    public function test_collected_session_duration_reflects_real_activity(): void
    {
        Carbon::setTestNow('2026-08-10 10:00:00');
        $this->postJson(route('analytics.collect'), [
            'type' => 'pageview', 'uuid' => 'u1', 'url' => '/a', 'hostname' => 'example.test',
        ]);

        Carbon::setTestNow('2026-08-10 10:03:00');
        $this->postJson(route('analytics.collect'), [
            'type' => 'pageview', 'uuid' => 'u1', 'url' => '/b', 'hostname' => 'example.test',
        ]);

        // L'utilisateur reste actif (événement custom) jusqu'à 10:07.
        Carbon::setTestNow('2026-08-10 10:07:00');
        $this->postJson(route('analytics.collect'), [
            'type' => 'event', 'name' => 'engage', 'uuid' => 'u1', 'url' => '/b', 'hostname' => 'example.test',
        ]);

        Carbon::setTestNow(null);

        $overview = DashboardService::overview('7d');

        // Première activité 10:00, dernière 10:07 → 420 s.
        $this->assertSame(420, $overview['avgDuration']);
        $this->assertSame(0.0, $overview['bounceRate']);
    }
}
