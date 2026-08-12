<?php

namespace MltStephane\LaravelAnalytics\Tests;

use MltStephane\LaravelAnalytics\AnalyticsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AnalyticsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Keep the rate limiter and caches in-memory for tests.
        $app['config']->set('cache.default', 'array');

        $app['config']->set('app.url', 'http://localhost');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
