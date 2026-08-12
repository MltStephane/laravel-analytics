<?php

namespace MltStephane\LaravelAnalytics;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use MltStephane\LaravelAnalytics\Commands\PruneAnalyticsData;
use MltStephane\LaravelAnalytics\Contracts\LocationResolver;
use MltStephane\LaravelAnalytics\Http\Middleware\CollectMiddleware;
use MltStephane\LaravelAnalytics\Http\Middleware\TrackPageview;
use MltStephane\LaravelAnalytics\Support\ScriptAsset;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'analytics');

        $this->app->singleton('analytics', function (Application $app) {
            return new Analytics(
                $app['request'],
                $app['config']
            );
        });

        $driver = config('analytics.geolocation.driver');

        if (is_string($driver) && class_exists($driver)) {
            $this->app->bind(LocationResolver::class, $driver);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('analytics.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/analytics.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
            ], 'laravel-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/analytics'),
            ], 'laravel-views');
        }

        $this->commands([PruneAnalyticsData::class]);

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('analytics.collect', CollectMiddleware::class);
        $router->aliasMiddleware('analytics.track-pageview', TrackPageview::class);

        Blade::directive('analytics', function () {
            $endpoint = url(config('analytics.collect.uri'));
            $autoTrack = config('analytics.tracker.auto_track', true) ? '' : ' data-auto-track="false"';
            $scriptPath = config('analytics.tracker.script_path', 'js/tracker.js');

            $src = Route::has('analytics.script')
                ? route('analytics.script', ['v' => ScriptAsset::hash('tracker')])
                : url('/'.ltrim($scriptPath, '/')).'?v='.ScriptAsset::hash('tracker');

            return '<script defer src="'.e($src).'" data-endpoint="'.e($endpoint).'"'.$autoTrack.'></script>';
        });

        // Rate limiting of the collection endpoint is handled by CollectMiddleware.
    }
}
