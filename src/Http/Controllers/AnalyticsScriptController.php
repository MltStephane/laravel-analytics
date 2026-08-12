<?php

namespace MltStephane\LaravelAnalytics\Http\Controllers;

use Illuminate\Http\Response;

class AnalyticsScriptController
{
    public function __invoke(): Response
    {
        $contents = file_get_contents(__DIR__.'/../../../resources/js/analytics.js');

        return response($contents === false ? '' : $contents, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
