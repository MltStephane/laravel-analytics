<?php

namespace MltStephane\LaravelAnalytics\Http\Controllers;

use Illuminate\Http\Response;
use MltStephane\LaravelAnalytics\Support\ScriptAsset;

class AnalyticsScriptController
{
    public function __invoke(): Response
    {
        $contents = ScriptAsset::contents('tracker');

        return response($contents, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
