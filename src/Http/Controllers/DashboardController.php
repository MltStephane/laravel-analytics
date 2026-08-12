<?php

namespace MltStephane\LaravelAnalytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use MltStephane\LaravelAnalytics\Services\DashboardService;
use MltStephane\LaravelAnalytics\Support\ScriptAsset;

class DashboardController
{
    public function index(Request $request): View
    {
        $period = $request->validate([
            'period' => ['sometimes', 'in:24h,7d,30d,90d'],
        ])['period'] ?? '7d';

        $data = DashboardService::overview($period, 10);

        // Period links are hidden when their sliding window has no event;
        // the current period stays visible so the active link is never lost.
        $periodsWithData = DashboardService::periodsWithData($data['to']);
        $labels = ['24h' => '24 h', '7d' => '7 jours', '30d' => '30 jours', '90d' => '90 jours'];
        $periods = [];
        foreach ($labels as $key => $label) {
            if ($key === $period || in_array($key, $periodsWithData, true)) {
                $periods[$key] = $label;
            }
        }

        $data['periods'] = $periods;
        $data['dashboardScriptHash'] = ScriptAsset::hash('dashboard');

        return view('analytics::dashboard', $data);
    }
}
