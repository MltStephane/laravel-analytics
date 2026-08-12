<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Illuminate\Testing\TestResponse;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class CollectTest extends TestCase
{
    protected function postPayload(array $payload, array $headers = []): TestResponse
    {
        return $this->postJson(route('analytics.collect'), $payload, $headers);
    }

    public function test_pageview_creates_visitor_session_and_event(): void
    {
        $response = $this->postPayload([
            'type' => 'pageview',
            'uuid' => 'abc',
            'url' => '/home',
            'title' => 'Home',
            'hostname' => 'example.test',
        ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('analytics_events', ['type' => 'pageview', 'url' => '/home', 'title' => 'Home']);
        $this->assertDatabaseHas('analytics_visitors', ['uuid' => 'abc']);

        $this->assertSame(1, Event::query()->count());
        $this->assertSame(1, Visitor::query()->count());

        $session = Session::query()->firstOrFail();
        $this->assertTrue($session->bounced);
        $this->assertSame(1, $session->pages_count);
    }

    public function test_second_pageview_reuses_the_session(): void
    {
        $this->postPayload(['type' => 'pageview', 'uuid' => 'abc', 'url' => '/home', 'hostname' => 'example.test']);
        $this->postPayload(['type' => 'pageview', 'uuid' => 'abc', 'url' => '/about', 'hostname' => 'example.test']);

        $this->assertSame(1, Session::query()->count());
        $this->assertSame(2, Event::query()->count());

        $session = Session::query()->firstOrFail();
        $this->assertSame(2, $session->pages_count);
        $this->assertFalse($session->bounced);
    }

    public function test_custom_event_is_recorded_with_data(): void
    {
        $response = $this->postPayload([
            'type' => 'event',
            'name' => 'signup',
            'data' => ['plan' => 'pro'],
            'url' => '/home',
        ]);

        $response->assertStatus(204);

        $event = Event::query()->firstOrFail();
        $this->assertSame('event', $event->type);
        $this->assertSame('signup', $event->name);
        $this->assertSame(['plan' => 'pro'], $event->data);
    }

    public function test_bot_user_agent_is_ignored(): void
    {
        $response = $this->postPayload(
            ['type' => 'pageview', 'uuid' => 'bot', 'url' => '/home', 'hostname' => 'example.test'],
            ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']
        );

        $response->assertStatus(204);
        $this->assertSame(0, Event::query()->count());
        $this->assertSame(0, Visitor::query()->count());
    }

    public function test_forbidden_domain_is_rejected(): void
    {
        config(['analytics.collect.domains' => ['allowed.test']]);

        $response = $this->postPayload(
            ['type' => 'pageview', 'uuid' => 'abc', 'url' => '/home', 'hostname' => 'allowed.test'],
            ['Referer' => 'http://evil.test/some-page']
        );

        $response->assertStatus(403);
        $this->assertSame(0, Event::query()->count());
    }

    public function test_event_name_too_long_is_rejected(): void
    {
        $response = $this->postPayload([
            'type' => 'event',
            'name' => str_repeat('a', 51),
            'url' => '/home',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Event::query()->count());
    }

    public function test_data_with_too_many_properties_is_rejected(): void
    {
        $data = [];

        for ($i = 0; $i < 51; $i++) {
            $data['key_'.$i] = $i;
        }

        $response = $this->postPayload([
            'type' => 'event',
            'name' => 'big',
            'data' => $data,
            'url' => '/home',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Event::query()->count());
    }

    public function test_ignored_path_is_not_tracked(): void
    {
        config(['analytics.collect.ignore_paths' => ['^/admin']]);

        $response = $this->postPayload([
            'type' => 'pageview',
            'uuid' => 'abc',
            'url' => '/admin/dashboard',
            'hostname' => 'example.test',
        ]);

        $response->assertStatus(204);
        $this->assertSame(0, Event::query()->count());
    }
}
