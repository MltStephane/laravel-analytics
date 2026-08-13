@extends('analytics::layout')

@section('period-context')
    From {{ $from->translatedFormat('d M Y H:i') }} to {{ $to->translatedFormat('d M Y H:i') }}
@endsection

@section('period-links')
    @foreach ($periods as $key => $label)
        <a
            href="{{ route('analytics.dashboard', ['period' => $key]) }}"
            class="{{ $period === $key ? 'active' : '' }}"
            @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
@endsection

@php
    $fmtDuration = function (int $seconds): string {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    };

    $formatChange = function (array $metric): string {
        if (! $metric['hasPrevious']) {
            return $metric['hasCurrent'] ? 'New' : '—';
        }

        if ($metric['change'] === null) {
            return '—';
        }

        if ((float) $metric['change'] === 0.0) {
            return '0 %';
        }

        return ($metric['change'] > 0 ? '+' : '−').number_format(abs($metric['change']), 1, ',', ' ').' %';
    };

    $changeTitle = function (array $metric): string {
        if (! $metric['hasPrevious']) {
            return $metric['hasCurrent']
                ? 'No data in the previous period — new activity detected'
                : 'No data in this period or the previous one';
        }

        if ($metric['change'] === null) {
            return 'The previous period had data, but the reference value is zero: change cannot be calculated';
        }

        $fmt = fn (int|float $v): string => (float) $v === (float) (int) $v ? (string) (int) $v : (string) round((float) $v, 1);

        return 'Current value: '.$fmt($metric['current']).' — previous period: '.$fmt($metric['previous']);
    };

    $countryNames = [
        'FR' => 'France', 'BE' => 'Belgium', 'CH' => 'Switzerland', 'CA' => 'Canada',
        'US' => 'United States', 'DE' => 'Germany', 'UK' => 'United Kingdom', 'GB' => 'United Kingdom',
        'ES' => 'Spain', 'IT' => 'Italy', 'PT' => 'Portugal', 'MA' => 'Morocco',
        'SN' => 'Senegal', 'CI' => 'Ivory Coast',
    ];
    $countryName = fn (string $code): string => $countryNames[$code] ?? $code;

    $kpis = [
        ['key' => 'visitors', 'label' => 'Unique visitors', 'value' => number_format($visitors, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'pageviews', 'label' => 'Pageviews', 'value' => number_format($pageviews, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'sessions', 'label' => 'Sessions started', 'value' => number_format($sessions, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'viewsPerVisit', 'label' => 'Pageviews / visitor', 'value' => number_format($viewsPerVisit, 1, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'bounceRate', 'label' => 'Bounce rate', 'value' => number_format($bounceRate, 1, ',', ' '), 'suffix' => '%', 'invert' => true],
        ['key' => 'avgDuration', 'label' => 'Average duration', 'value' => $fmtDuration($avgDuration), 'suffix' => '', 'invert' => false],
    ];

    $chart = ['w' => 960, 'h' => 300, 'left' => 42, 'right' => 16, 'top' => 16, 'bottom' => 34];
    $innerW = $chart['w'] - $chart['left'] - $chart['right'];
    $innerH = $chart['h'] - $chart['top'] - $chart['bottom'];
    $n = count($timeSeries);
    $chartPageviews = (int) collect($timeSeries)->sum('pageviews');
    $chartVisitors = (int) collect($timeSeries)->sum('visitors');
    $chartIntervalCount = $n;
    $hasChartData = collect($timeSeries)->contains(fn ($point) => $point['pageviews'] > 0 || $point['visitors'] > 0);
    $hasPeriodData = $pageviews > 0
        || $visitors > 0
        || $sessions > 0
        || $topPages->isNotEmpty()
        || $topSources->isNotEmpty()
        || $topBrowsers->isNotEmpty()
        || $topOs->isNotEmpty()
        || $topDevices->isNotEmpty()
        || $topCountries->isNotEmpty()
        || $topEvents->isNotEmpty();
    $maxValue = $hasChartData ? max(1, (int) collect($timeSeries)->flatMap(fn ($point) => [$point['pageviews'], $point['visitors']])->max()) : 1;
    $chartPoint = function (int $index, int $value) use ($chart, $innerW, $innerH, $n, $maxValue): array {
        $x = $chart['left'] + ($n <= 1 ? 0 : $index * $innerW / ($n - 1));
        $y = $chart['top'] + $innerH - ($value / $maxValue) * $innerH;

        return [$x, $y];
    };
    $chartPoints = [];
    foreach (['pageviews', 'visitors'] as $seriesKey) {
        $chartPoints[$seriesKey] = collect($timeSeries)->map(function ($point, $index) use ($chartPoint, $seriesKey) {
            [$x, $y] = $chartPoint($index, (int) $point[$seriesKey]);

            return [
                'x' => round($x, 1),
                'y' => round($y, 1),
            ];
        })->all();
    }
    $linePoints = fn (string $key): string => collect($chartPoints[$key])->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    $areaPath = function (string $key) use ($chartPoints, $chart): string {
        $points = $chartPoints[$key];

        if ($points === []) {
            return '';
        }

        $baseline = $chart['top'] + ($chart['h'] - $chart['top'] - $chart['bottom']);
        $first = $points[0];
        $last = $points[count($points) - 1];

        return 'M '.$first['x'].' '.$baseline.' L '.collect($points)->map(fn ($point) => $point['x'].' '.$point['y'])->implode(' L ').' L '.$last['x'].' '.$baseline.' Z';
    };
    $gridLines = 4;
    $barShare = function (int|float $value, int|float $total): float {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return min(100, max(0, $value / $total * 100));
    };
    $sourceTotal = $pageviews;
    $deviceTotal = (int) $topDevices->sum('count');
@endphp

@section('content')
    @if ($recentEvents->isEmpty())
        <section class="onboarding reveal" aria-labelledby="onboarding-title">
            <h2 id="onboarding-title">Start collecting data</h2>
            <p>Add the <code>@@analytics</code> directive to your site's <code>&lt;head&gt;</code>. Data will
                appear here after the first visit.</p>
        </section>
    @elseif (! $hasPeriodData)
        <section class="period-empty-notice reveal" aria-labelledby="period-empty-title">
            <h2 id="period-empty-title">No data for this period</h2>
            <p>Interactions have already been collected. Try a longer period to find them.</p>
        </section>
    @endif

    <div class="section-intro reveal">
        <h2>Overview</h2>
        <p>Changes compare this period with the previous one of the same length.</p>
    </div>

    <div class="cards reveal">
        @foreach ($kpis as $kpi)
            @php
                $metricComparison = $comparison[$kpi['key']];
                $change = $metricComparison['change'];
                $isFavorable = $change !== null && $change !== 0.0 && ($kpi['invert'] ? $change < 0 : $change > 0);
                $changeClass = $change === null || $change === 0.0 ? 'neutral' : ($isFavorable ? 'positive' : 'negative');
            @endphp
            <div class="card">
                <div class="label">{{ $kpi['label'] }}</div>
                <div class="value">{{ $kpi['value'] }}@if ($kpi['suffix'] !== '')
                        <span class="suffix">{{ $kpi['suffix'] }}</span>
                    @endif</div>
                <div class="comparison">
                    <span
                        class="change {{ $changeClass }}"
                        title="{{ $changeTitle($metricComparison) }}"
                    >
                        {{ $formatChange($metricComparison) }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>


    <div class="panel reveal">
        <h2>Traffic</h2>
        <p class="panel-subtitle">Pageviews and unique visitors by {{ $period === '24h' ? 'hour' : 'day' }}.</p>
        @if ($hasChartData)
            <div class="chart-toolbar">
                <div class="legend" aria-label="Chart series">
                    <button
                        type="button"
                        class="series-toggle"
                        data-series-toggle="pageviews"
                        aria-controls="chart-series-pageviews"
                        aria-pressed="true"
                    >
                        <span class="legend-dot pv" aria-hidden="true"></span> Pageviews
                    </button>
                    <button
                        type="button"
                        class="series-toggle"
                        data-series-toggle="visitors"
                        aria-controls="chart-series-visitors"
                        aria-pressed="true"
                    >
                        <span class="legend-dot vis" aria-hidden="true"></span> Visitors
                    </button>
                </div>
            </div>
            <div
                class="chart-scroll"
                data-chart
                tabindex="0"
                role="region"
                aria-label="Traffic chart, horizontal scroll"
            >
                <div class="chart-tooltip" role="status" aria-live="polite" hidden></div>
                <svg
                    width="{{ $chart['w'] }}"
                    height="{{ $chart['h'] }}"
                    viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}"
                    role="img"
                    aria-labelledby="traffic-chart-title traffic-chart-description"
                    aria-describedby="traffic-chart-summary"
                >
                    <title id="traffic-chart-title">Traffic over the period</title>
                    <desc id="traffic-chart-description">Visual trend of pageviews and unique visitors. The
                        full interval summary in the table remains independent of hidden series.
                    </desc>
                    <g class="chart-grid" aria-hidden="true">
                        @for ($i = 0; $i <= $gridLines; $i++)
                            @php
                                $y = $chart['top'] + $innerH - $i * $innerH / $gridLines;
                                $value = round($maxValue * $i / $gridLines);
                            @endphp
                            <line
                                x1="{{ $chart['left'] }}"
                                y1="{{ $y }}"
                                x2="{{ $chart['w'] - $chart['right'] }}"
                                y2="{{ $y }}"
                            />
                            <text x="{{ $chart['left'] - 8 }}" y="{{ $y + 3 }}" text-anchor="end">{{ $value }}</text>
                        @endfor
                        @if ($n > 1)
                            @foreach ($timeSeries as $index => $point)
                                @if ($index === 0 || $index === $n - 1 || ($index % max(1, intdiv($n, 6))) === 0)
                                    <text
                                        class="chart-label"
                                        x="{{ $chartPoint($index, 0)[0] }}"
                                        y="{{ $chart['h'] - 7 }}"
                                        text-anchor="middle"
                                    >{{ $point['label'] }}</text>
                                @endif
                            @endforeach
                        @endif
                    </g>
                    @foreach (['pageviews' => 'pv', 'visitors' => 'vis'] as $seriesKey => $seriesClass)
                        <g id="chart-series-{{ $seriesKey }}" data-series-layer="{{ $seriesKey }}" aria-hidden="false">
                            <path class="chart-area {{ $seriesClass }}" d="{{ $areaPath($seriesKey) }}" />
                            <polyline class="chart-line {{ $seriesClass }}" points="{{ $linePoints($seriesKey) }}" />
                            @foreach ($chartPoints[$seriesKey] as $index => $point)
                                <circle
                                    class="chart-hit"
                                    data-chart-point
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="12"
                                    data-label="{{ $timeSeries[$index]['label'] }}"
                                    data-pageviews="{{ $timeSeries[$index]['pageviews'] }}"
                                    data-visitors="{{ $timeSeries[$index]['visitors'] }}"
                                />
                                <circle
                                    class="chart-point {{ $seriesClass }}"
                                    data-chart-point
                                    cx="{{ $point['x'] }}"
                                    cy="{{ $point['y'] }}"
                                    r="4"
                                    data-label="{{ $timeSeries[$index]['label'] }}"
                                    data-pageviews="{{ $timeSeries[$index]['pageviews'] }}"
                                    data-visitors="{{ $timeSeries[$index]['visitors'] }}"
                                />
                            @endforeach
                        </g>
                    @endforeach
                </svg>
            </div>
            <p id="traffic-chart-summary" class="chart-summary sr-only" role="status">Full summary of the table intervals for
                the period, independent of the visual filter
                : {{ number_format($chartPageviews, 0, ',', ' ') }} {{ $chartPageviews === 1 ? 'pageview' : 'pageviews' }}
                , {{ number_format($chartVisitors, 0, ',', ' ') }} {{ $chartVisitors === 1 ? 'unique visitor' : 'unique visitors' }}
                . The table
                covers {{ $chartIntervalCount }} {{ $chartIntervalCount === 1 ? 'interval' : 'intervals' }}.
                The current incomplete interval is not included.</p>
            <details class="chart-data-details">
                <summary>Show data as a table</summary>
                <p class="chart-data-note muted">The current incomplete interval is not included in the displayed
                    data.</p>
                <div
                    class="table-scroll"
                    tabindex="0"
                    role="region"
                    aria-label="Detailed chart data, horizontal scroll"
                >
                    <span class="scroll-hint" aria-hidden="true">Scroll horizontally →</span>
                    <table>
                        <caption class="sr-only">Traffic data by {{ $period === '24h' ? 'hour' : 'day' }}
                            : only the displayed intervals are listed.
                        </caption>
                        <thead>
                        <tr>
                            <th scope="col">Period</th>
                            <th scope="col">Pageviews</th>
                            <th scope="col">Unique visitors</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($timeSeries as $point)
                            <tr>
                                <th scope="row">{{ $point['label'] }}</th>
                                <td class="num">{{ $point['pageviews'] }}</td>
                                <td class="num">{{ $point['visitors'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @elseif ($hasPeriodData)
            <div class="empty">This period has data, but the current interval is not closed yet. The
                chart will show it when it closes.
            </div>
        @else
            <div class="empty">No traffic data for this period.</div>
        @endif
    </div>

    <div class="grid-2 reveal">
        <div class="panel">
            <h2>Top pages</h2>
            <p class="panel-subtitle">Unique visitors and depth of engagement per page.</p>
            @if ($topPages->isEmpty())
                <div class="empty">No pageviews for this period.</div>
            @else
                <div
                    class="table-scroll"
                    tabindex="0"
                    role="region"
                    aria-label="Top pages, horizontal scroll"
                >
                    <span class="scroll-hint" aria-hidden="true">Scroll horizontally →</span>
                    <table>
                        <caption class="sr-only">Top pages for the period</caption>
                        <thead>
                        <tr>
                            <th scope="col">Page</th>
                            <th scope="col" class="num">Views</th>
                            <th scope="col" class="num">Visitors</th>
                            <th scope="col" class="num">Pageviews / visitor</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($topPages as $row)
                            <tr>
                                <th scope="row" class="url" title="{{ $row->url }}">{{ $row->url ?? '—' }}</th>
                                <td class="num">{{ $row->pageviews }}</td>
                                <td class="num">{{ $row->visitors }}</td>
                                <td class="num">{{ number_format($row->pagesPerVisitor, 1, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Acquisition sources</h2>
            <p class="panel-subtitle">Share of pageviews, visitors brought in, and related engagement.</p>
            @if ($topSources->isEmpty())
                <div class="empty">No source identified for this period.</div>
            @else
                <div class="bar-list">
                    @foreach ($topSources as $row)
                        @php $share = $barShare($row->pageviews, $sourceTotal); @endphp
                        <div class="bar-row">
                            <div class="bar-row-head"><span title="{{ $row->source }}">{{ $row->source }}</span><span>{{ number_format($share, 1, ',', ' ') }} % · {{ $row->pageviews }} vues</span>
                            </div>
                            <div class="bar-track" aria-hidden="true">
                                <div class="bar-fill" style="width: {{ $share }}%"></div>
                            </div>
                            <div class="bar-row-meta"><span>{{ $row->visitors }} unique visitors</span><span>{{ number_format($row->pagesPerVisitor, 1, ',', ' ') }} pageviews / visitor</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid-2 reveal">
        <div class="panel">
            <h2>Device mix</h2>
            <p class="panel-subtitle">Breakdown of unique visitors by detected device.</p>
            @if ($topDevices->isEmpty())
                <div class="empty">No device identified for this period.</div>
            @else
                <div class="bar-list">
                    @foreach ($topDevices as $row)
                        @php $share = $barShare($row->count, $deviceTotal); @endphp
                        <div class="bar-row">
                            <div class="bar-row-head">
                                <span>{{ $row->label }}</span><span>{{ $row->count }} visitors</span></div>
                            <div class="bar-track" aria-hidden="true">
                                <div class="bar-fill" style="width: {{ $share }}%"></div>
                            </div>
                            <div class="bar-row-meta"><span>Unique audience</span><span>{{ number_format($share, 1, ',', ' ') }} %</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Top events</h2>
            <p class="panel-subtitle">The most frequent custom actions for the period.</p>
            @if ($topEvents->isEmpty())
                <div class="empty">No custom events for this period.</div>
            @else
                <div
                    class="table-scroll"
                    tabindex="0"
                    role="region"
                    aria-label="Top events, horizontal scroll"
                >
                    <span class="scroll-hint" aria-hidden="true">Scroll horizontally →</span>
                    <table>
                        <caption class="sr-only">Top events for the period</caption>
                        <thead>
                        <tr>
                            <th scope="col">Event</th>
                            <th scope="col" class="num">Occurrences</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($topEvents as $row)
                            <tr>
                                <th scope="row">{{ $row->name }}</th>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="grid-3 reveal">
        @foreach ([['title' => 'Browsers', 'rows' => $topBrowsers], ['title' => 'Operating systems', 'rows' => $topOs]] as $breakdown)
            <div class="panel">
                <h2>{{ $breakdown['title'] }}</h2>
                @if ($breakdown['rows']->isEmpty())
                    <div class="empty">No data available for this period.</div>
                @else
                    <table class="table-compact">
                        <caption class="sr-only">{{ $breakdown['title'] }} of visitors for the period</caption>
                        <thead class="sr-only">
                        <tr>
                            <th scope="col">{{ $breakdown['title'] === 'Browsers' ? 'Browser' : 'Operating system' }}</th>
                            <th scope="col">Interactions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($breakdown['rows'] as $row)
                            <tr>
                                <th scope="row">{{ $row->label }}</th>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach

        <div class="panel">
            <h2>Countries</h2>
            @if ($topCountries->isEmpty())
                <div class="empty">No country identified for this period.</div>
            @else
                <table class="table-compact">
                    <caption class="sr-only">Visitor countries for the period</caption>
                    <thead class="sr-only">
                    <tr>
                        <th scope="col">Country</th>
                        <th scope="col">Unique visitors</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($topCountries as $row)
                        <tr>
                            <th scope="row">{{ $countryName($row->code) }}</th>
                            <td class="num">{{ $row->visitors }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel reveal">
        <h2>Recent activity</h2>
        <p class="panel-subtitle">The last 20 recorded interactions, across all periods.</p>
        @if ($recentEvents->isEmpty())
            <div class="empty">No recent events to show.</div>
        @else
            <div class="table-scroll" tabindex="0" role="region" aria-label="Recent activity, horizontal scroll">
                <span class="scroll-hint" aria-hidden="true">Scroll horizontally →</span>
                <table>
                    <caption class="sr-only">Last 20 interactions, across all periods</caption>
                    <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Type</th>
                        <th scope="col">Details</th>
                        <th scope="col">Browser</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($recentEvents as $event)
                        <tr>
                            <th scope="row">{{ $event->created_at->translatedFormat('d M Y H:i') }}</th>
                            <td>
                                <span class="badge {{ $event->type }}">{{ $event->type === 'pageview' ? 'Pageview' : 'Event' }}</span>
                            </td>
                            <td
                                class="url"
                                title="{{ $event->url ?? '' }}"
                            >{{ $event->name ?? $event->url ?? '—' }}</td>
                            <td>{{ $event->visitor?->browser ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <details class="kpi-help reveal">
        <summary>Understand the metrics</summary>
        <dl class="kpi-definitions">
            <div>
                <dt>Unique visitors</dt>
                <dd>A visitor is identified by a random ID stored in the browser's local storage — no cookies, no
                    fingerprinting. A returning visitor is counted only once for the period.
                </dd>
            </div>
            <div>
                <dt>Pageviews</dt>
                <dd>Total number of page loads recorded during the period. One visitor can generate multiple
                    pageviews.
                </dd>
            </div>
            <div>
                <dt>Sessions started</dt>
                <dd>Sessions whose start date (<code>started_at</code>) falls within the period, excluding the shared
                    server visitor. A session continues as long as inactivity does not exceed 30 minutes.
                </dd>
            </div>
            <div>
                <dt>Pageviews / visitor</dt>
                <dd>Pageviews divided by the number of unique visitors in the period.</dd>
            </div>
            <div>
                <dt>Bounce rate</dt>
                <dd>Share of sessions started in the period that viewed only one page.</dd>
            </div>
            <div>
                <dt>Average duration</dt>
                <dd>Average time between start and last activity for sessions started in the period, capped at the end
                    of the analyzed period.
                </dd>
            </div>
        </dl>
    </details>

    <script defer src="{{ route('analytics.dashboard.script', ['v' => $dashboardScriptHash]) }}"></script>
@endsection
