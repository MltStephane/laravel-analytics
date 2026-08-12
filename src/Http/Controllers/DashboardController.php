<?php

namespace MltStephane\LaravelAnalytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use MltStephane\LaravelAnalytics\Services\DashboardService;

class DashboardController
{
    public function index(Request $request): View
    {
        $period = $request->validate([
            'period' => ['sometimes', 'in:24h,7d,30d,90d'],
        ])['period'] ?? '7d';

        $data = DashboardService::overview($period, 10);

        return view('analytics::dashboard', $data);
    }
}
