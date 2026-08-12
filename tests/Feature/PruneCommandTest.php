<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Illuminate\Support\Carbon;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    public function test_prune_removes_only_events_older_than_retention(): void
    {
        $now = Carbon::now();

        $visitor = Visitor::query()->create([
            'uuid' => 'prune-visitor',
            'first_seen_at' => $now->copy()->subDays(400),
            'last_seen_at' => $now,
        ]);

        $oldSession = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/old',
            'started_at' => $now->copy()->subDays(400),
            'last_activity_at' => $now->copy()->subDays(400),
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);

        $newSession = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/new',
            'started_at' => $now->copy()->subDay(),
            'last_activity_at' => $now,
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);

        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $oldSession->id,
            'type' => 'pageview',
            'url' => '/old',
            'created_at' => $now->copy()->subDays(400),
        ]);

        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $newSession->id,
            'type' => 'pageview',
            'url' => '/new',
            'created_at' => $now->copy()->subDay(),
        ]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(1, Event::query()->count());
        $this->assertDatabaseMissing('analytics_events', ['url' => '/old']);
        $this->assertDatabaseHas('analytics_events', ['url' => '/new']);

        // The orphaned session is removed, the recent one and the visitor stay.
        $this->assertSame(1, Session::query()->count());
        $this->assertDatabaseHas('analytics_sessions', ['id' => $newSession->id]);
        $this->assertSame(1, Visitor::query()->count());
    }
}
