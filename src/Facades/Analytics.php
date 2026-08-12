<?php

namespace MltStephane\LaravelAnalytics\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \MltStephane\LaravelAnalytics\Models\Event|null track(string $name, array $data = [], ?string $url = null)
 * @method static \MltStephane\LaravelAnalytics\Models\Event|null pageview(?string $url = null)
 * @method static \MltStephane\LaravelAnalytics\Models\Event|null collect(array $payload, array $context)
 *
 * @see \MltStephane\LaravelAnalytics\Analytics
 */
class Analytics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'analytics';
    }
}
