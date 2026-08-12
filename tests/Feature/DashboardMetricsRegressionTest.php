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

    public function test_shared_server_visitor_does_not_distort_avg_duration(): void
    {
        $now = Carbon::now();

        // Visiteur partagé "server" (config analytics.server.visitor_uuid) :
        // toutes les requêtes serveur (middleware TrackPageview, facade) lui
        // sont rattachées, ce qui maintient sa session ouverte en continu.
        // Cette session "zombie" de 5 jours dominerait la moyenne.
        $server = Visitor::query()->create([
            'uuid' => 'server',
            'first_seen_at' => $now->copy()->subDays(5),
            'last_seen_at' => $now,
        ]);

        Session::query()->create([
            'visitor_id' => $server->id,
            'hostname' => 'example.test',
            'path' => '/',
            'started_at' => $now->copy()->subDays(5),
            'last_activity_at' => $now,
            'duration' => 0,
            'bounced' => false,
            'pages_count' => 3,
        ]);

        // 100 sessions humaines réelles de 60 s chacune.
        $human = Visitor::query()->create([
            'uuid' => 'human-1',
            'first_seen_at' => $now->copy()->subDay(),
            'last_seen_at' => $now,
        ]);

        for ($i = 0; $i < 100; $i++) {
            Session::query()->create([
                'visitor_id' => $human->id,
                'hostname' => 'example.test',
                'path' => '/p'.$i,
                'started_at' => $now->copy()->subMinutes(30)->addSeconds($i * 10),
                'last_activity_at' => $now->copy()->subMinutes(29)->addSeconds($i * 10),
                'duration' => 60,
                'bounced' => false,
                'pages_count' => 2,
            ]);
        }

        $overview = DashboardService::overview('7d');

        // Sans exclusion : (432000 + 6000) / 101 ≈ 4336 s ≈ 72 min.
        // Avec exclusion du visiteur "server" : 60 s.
        $this->assertLessThan(120, $overview['avgDuration']);
    }

    public function test_avg_duration_is_bounded_to_the_period_end(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');

        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-bounded',
            'first_seen_at' => Carbon::now()->subHours(5),
            'last_seen_at' => Carbon::now(),
        ]);

        // Session démarrée à 10:00, dont l'activité se prolonge au-delà de la
        // fenêtre analysée (dernière activité 14:00, soit après "maintenant").
        Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/home',
            'started_at' => Carbon::now()->subHours(2),
            'last_activity_at' => Carbon::now()->addHours(2),
            'duration' => 0,
            'bounced' => false,
            'pages_count' => 2,
        ]);

        $overview = DashboardService::overview('7d');

        // La durée créditée ne doit pas dépasser la fin de la période :
        // 12:00 - 10:00 = 7200 s (au lieu de 14400 s sans bornage).
        $this->assertSame(7200, $overview['avgDuration']);

        Carbon::setTestNow(null);
    }
}
