<?php

use Illuminate\Support\Facades\Route;
use MltStephane\LaravelAnalytics\Http\Controllers\AnalyticsScriptController;
use MltStephane\LaravelAnalytics\Http\Controllers\CollectController;
use MltStephane\LaravelAnalytics\Http\Controllers\DashboardController;

// Script tracker (GET, public, hors CSRF)
Route::get(config('analytics.tracker.script_path', 'js/tracker.js'), [AnalyticsScriptController::class, '__invoke'])
    ->name('analytics.script');

// Collecte (POST, public, middleware dédié)
Route::post(config('analytics.collect.uri'), CollectController::class)
    ->middleware('analytics.collect')
    ->name('analytics.collect');

// Dashboard
if (config('analytics.dashboard.enabled')) {
    Route::prefix(config('analytics.dashboard.prefix'))
        ->middleware(config('analytics.dashboard.middleware'))
        ->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('analytics.dashboard');
        });
}
