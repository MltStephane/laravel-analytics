<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Illuminate\Support\Carbon;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Services\DashboardService;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    protected function seedOverviewData(): array
    {
        $now = Carbon::now();

        $visitorA = Visitor::query()->create([
            'uuid' => 'visitor-a',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'first_seen_at' => $now->copy()->subDays(8),
            'last_seen_at' => $now,
        ]);

        $visitorB = Visitor::query()->create([
            'uuid' => 'visitor-b',
            'browser' => 'Safari',
            'os' => 'macOS',
            'device_type' => 'smartphone',
            'first_seen_at' => $now->copy()->subDays(2),
            'last_seen_at' => $now,
        ]);

        // Session outside the 7-day window (excluded from period aggregates).
        $oldSession = Session::query()->create([
            'visitor_id' => $visitorA->id,
            'hostname' => 'example.test',
            'path' => '/old',
            'started_at' => $now->copy()->subDays(8),
            'last_activity_at' => $now->copy()->subDays(8),
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);

        Event::query()->create([
            'visitor_id' => $visitorA->id,
            'session_id' => $oldSession->id,
            'type' => 'pageview',
            'url' => '/old',
            'created_at' => $now->copy()->subDays(8),
        ]);

        // In-window session: 2 pages, not bounced, duration 120s.
        $multiPageSession = Session::query()->create([
            'visitor_id' => $visitorA->id,
            'hostname' => 'example.test',
            'path' => '/home',
            'referrer' => 'https://google.com/search?q=test',
            'referrer_domain' => 'google.com',
            'started_at' => $now->copy()->subMinutes(2),
            'last_activity_at' => $now,
            'duration' => 120,
            'bounced' => false,
            'pages_count' => 2,
        ]);

        Event::query()->create([
            'visitor_id' => $visitorA->id,
            'session_id' => $multiPageSession->id,
            'type' => 'pageview',
            'url' => '/home',
            'title' => 'Home',
            'created_at' => $now->copy()->subDay(),
        ]);

        Event::query()->create([
            'visitor_id' => $visitorA->id,
            'session_id' => $multiPageSession->id,
            'type' => 'pageview',
            'url' => '/about',
            'title' => 'About',
            'created_at' => $now->copy()->subDay()->subMinutes(30),
        ]);

        // In-window session: 1 page, bounced, duration 0s.
        $bouncedSession = Session::query()->create([
            'visitor_id' => $visitorB->id,
            'hostname' => 'example.test',
            'path' => '/contact',
            'started_at' => $now->copy()->subDays(2),
            'last_activity_at' => $now->copy()->subDays(2),
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);

        Event::query()->create([
            'visitor_id' => $visitorB->id,
            'session_id' => $bouncedSession->id,
            'type' => 'pageview',
            'url' => '/contact',
            'title' => 'Contact',
            'created_at' => $now->copy()->subDays(2),
        ]);

        return [$visitorA, $visitorB];
    }

    public function test_overview_7d_returns_coherent_aggregates(): void
    {
        $this->seedOverviewData();

        $overview = DashboardService::overview('7d');

        $this->assertSame('7d', $overview['period']);
        $this->assertSame(2, $overview['visitors']);
        $this->assertSame(3, $overview['pageviews']);
        $this->assertSame(2, $overview['sessions']);
        $this->assertSame(1.5, $overview['viewsPerVisit']);
        $this->assertSame(50.0, $overview['bounceRate']);
        $this->assertSame(60, $overview['avgDuration']);

        $this->assertSame(100.0, $overview['comparison']['visitors']['change']);
        $this->assertSame(200.0, $overview['comparison']['pageviews']['change']);
        $this->assertSame(100.0, $overview['comparison']['sessions']['change']);
        $this->assertSame(50.0, $overview['comparison']['viewsPerVisit']['change']);
        $this->assertSame(-50.0, $overview['comparison']['bounceRate']['change']);
        $this->assertNull($overview['comparison']['avgDuration']['change']);

        $topUrls = $overview['topPages']->pluck('url')->all();
        $this->assertContains('/home', $topUrls);
        $this->assertContains('/about', $topUrls);
        $this->assertContains('/contact', $topUrls);
        $this->assertNotContains('/old', $topUrls);

        $home = $overview['topPages']->firstWhere('url', '/home');
        $this->assertSame(1, $home->visitors);
        $this->assertSame(1.0, $home->pagesPerVisitor);

        $this->assertSame('google.com', $overview['topSources']->first()->source);
        $this->assertSame(1, $overview['topSources']->first()->visitors);
        $this->assertSame(2.0, $overview['topSources']->first()->pagesPerVisitor);

        $deviceCounts = $overview['topDevices']->pluck('count', 'label')->all();
        $this->assertSame(1, $deviceCounts['Ordinateur']);
        $this->assertSame(1, $deviceCounts['Mobile']);

        $this->assertCount(7, $overview['timeSeries']);
        $this->assertSame(3, collect($overview['timeSeries'])->sum('pageviews'));
        $this->assertSame(2, collect($overview['timeSeries'])->sum('visitors'));
    }

    public function test_overview_24h_uses_hourly_buckets(): void
    {
        $now = Carbon::now();

        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-24h',
            'first_seen_at' => $now->copy()->subHours(2),
            'last_seen_at' => $now,
        ]);

        $session = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/home',
            'started_at' => $now->copy()->subHours(2),
            'last_activity_at' => $now,
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);

        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => 'pageview',
            'url' => '/home',
            'created_at' => $now->copy()->subHours(2),
        ]);

        $overview = DashboardService::overview('24h');

        $this->assertCount(24, $overview['timeSeries']);
        $this->assertSame(1, collect($overview['timeSeries'])->sum('pageviews'));
        $this->assertSame(1, collect($overview['timeSeries'])->sum('visitors'));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $overview['timeSeries'][0]['label']);
    }

    public function test_previous_period_excludes_the_current_period_boundary(): void
    {
        Carbon::setTestNow('2026-08-12 12:00:00');
        $from = Carbon::now()->subDays(7);
        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-period-boundary',
            'first_seen_at' => $from,
            'last_seen_at' => $from,
        ]);
        $session = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/boundary',
            'started_at' => $from,
            'last_activity_at' => $from,
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);
        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => 'pageview',
            'url' => '/boundary',
            'created_at' => $from,
        ]);

        $overview = DashboardService::overview('7d');

        $this->assertSame(1, $overview['pageviews']);
        $this->assertSame(0, $overview['comparison']['pageviews']['previous']);
        $this->assertNull($overview['comparison']['pageviews']['change']);

        Carbon::setTestNow(null);
    }

    public function test_overview_on_empty_data_returns_zeros(): void
    {
        $overview = DashboardService::overview('7d');

        $this->assertSame(0, $overview['visitors']);
        $this->assertSame(0, $overview['pageviews']);
        $this->assertSame(0, $overview['sessions']);
        $this->assertSame(0.0, $overview['viewsPerVisit']);
        $this->assertSame(0.0, $overview['bounceRate']);
        $this->assertSame(0, $overview['avgDuration']);
        foreach ($overview['comparison'] as $metric) {
            $this->assertNull($metric['change']);
            $this->assertSame(0.0, (float) $metric['current']);
            $this->assertSame(0.0, (float) $metric['previous']);
        }
        $this->assertCount(7, $overview['timeSeries']);
    }

    public function test_comparison_reports_new_when_previous_period_has_no_data(): void
    {
        $now = Carbon::now();
        $visitor = Visitor::query()->create([
            'uuid' => 'visitor-new-period',
            'first_seen_at' => $now->copy()->subDay(),
            'last_seen_at' => $now,
        ]);
        $session = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/new',
            'started_at' => $now->copy()->subDay(),
            'last_activity_at' => $now->copy()->subDay(),
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);
        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => 'pageview',
            'url' => '/new',
            'created_at' => $now->copy()->subDay(),
        ]);

        $overview = DashboardService::overview('7d');

        $this->assertNull($overview['comparison']['visitors']['change']);
        $this->assertNull($overview['comparison']['pageviews']['change']);
        $this->assertNull($overview['comparison']['sessions']['change']);
        $this->assertNull($overview['comparison']['viewsPerVisit']['change']);
        $this->assertNull($overview['comparison']['bounceRate']['change']);
    }
}
