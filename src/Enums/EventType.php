<?php

namespace MltStephane\LaravelAnalytics\Enums;

enum EventType: string
{
    case Pageview = 'pageview';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Pageview => 'Page vue',
            self::Event => 'Événement',
        };
    }
}
