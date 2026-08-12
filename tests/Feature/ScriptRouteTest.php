<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use MltStephane\LaravelAnalytics\Support\ScriptAsset;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class ScriptRouteTest extends TestCase
{
    public function test_script_route_serves_javascript_at_default_path(): void
    {
        $response = $this->get('/js/tracker.js');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');
        $response->assertSee('window.analytics');
    }

    public function test_old_analytics_js_url_is_gone(): void
    {
        $this->get('/analytics/analytics.js')->assertNotFound();
    }

    public function test_blade_directive_renders_named_route_src(): void
    {
        $compiled = Blade::compileString('@analytics');

        $this->assertStringContainsString(route('analytics.script'), $compiled);
        $this->assertStringNotContainsString('/analytics/analytics.js', $compiled);
        $this->assertStringContainsString('data-endpoint', $compiled);
        $this->assertStringContainsString('?v=', $compiled);
        $this->assertStringContainsString('v='.ScriptAsset::hash('tracker'), $compiled);
    }
}
