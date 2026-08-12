<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use MltStephane\LaravelAnalytics\Facades\Analytics;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class AnalyticsFacadeTest extends TestCase
{
    public function test_track_records_a_custom_event_with_the_server_visitor(): void
    {
        Analytics::track('purchase', ['qty' => 1]);

        $event = Event::query()->firstOrFail();
        $this->assertSame('event', $event->type);
        $this->assertSame('purchase', $event->name);
        $this->assertSame(['qty' => 1], $event->data);

        $visitor = Visitor::query()->firstOrFail();
        $this->assertSame('server', $visitor->uuid);
        $this->assertSame($visitor->id, $event->visitor_id);
    }

    public function test_pageview_records_a_pageview_event(): void
    {
        Analytics::pageview('/x');

        $event = Event::query()->firstOrFail();
        $this->assertSame('pageview', $event->type);
        $this->assertSame('/x', $event->url);
    }
}
