<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use MltStephane\LaravelAnalytics\Tests\TestCase;

class ScriptRouteCustomPathTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('analytics.tracker.script_path', 'assets/widget.js');
    }

    public function test_custom_script_path_is_served(): void
    {
        $this->get('/assets/widget.js')->assertOk();
    }

    public function test_default_path_not_served_after_override(): void
    {
        $this->get('/js/tracker.js')->assertNotFound();
    }
}
