@extends('analytics::layout')

@section('period-context')
    Du {{ $from->translatedFormat('d M Y H:i') }} au {{ $to->translatedFormat('d M Y H:i') }}
@endsection

@section('period-links')
    @foreach (['24h' => '24 h', '7d' => '7 jours', '30d' => '30 jours', '90d' => '90 jours'] as $key => $label)
        <a href="{{ route('analytics.dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}" @if ($period === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
@endsection

@php
    $fmtDuration = function (int $seconds): string {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;

        return sprintf('%d:%02d', $m, $s);
    };

    $formatChange = function (array $metric): string {
        if ($metric['change'] === null) {
            return (float) $metric['current'] > 0 && (float) $metric['previous'] === 0.0 ? 'Nouveau' : '—';
        }

        if ((float) $metric['change'] === 0.0) {
            return '0 %';
        }

        return ($metric['change'] > 0 ? '+' : '−').number_format(abs($metric['change']), 1, ',', ' ').' %';
    };

    $countryNames = [
        'FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'CA' => 'Canada',
        'US' => 'États-Unis', 'DE' => 'Allemagne', 'UK' => 'Royaume-Uni', 'GB' => 'Royaume-Uni',
        'ES' => 'Espagne', 'IT' => 'Italie', 'PT' => 'Portugal', 'MA' => 'Maroc',
        'SN' => 'Sénégal', 'CI' => "Côte d'Ivoire",
    ];
    $countryName = fn (string $code): string => $countryNames[$code] ?? $code;

    $kpis = [
        ['key' => 'visitors', 'label' => 'Visiteurs uniques', 'value' => number_format($visitors, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'pageviews', 'label' => 'Pages vues', 'value' => number_format($pageviews, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'sessions', 'label' => 'Sessions utiles', 'value' => number_format($sessions, 0, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'viewsPerVisit', 'label' => 'Pages / visite', 'value' => number_format($viewsPerVisit, 1, ',', ' '), 'suffix' => '', 'invert' => false],
        ['key' => 'bounceRate', 'label' => 'Taux de rebond', 'value' => number_format($bounceRate, 1, ',', ' '), 'suffix' => '%', 'invert' => true],
        ['key' => 'avgDuration', 'label' => 'Durée moyenne', 'value' => $fmtDuration($avgDuration), 'suffix' => '', 'invert' => false],
    ];

    $chart = ['w' => 960, 'h' => 300, 'left' => 42, 'right' => 16, 'top' => 16, 'bottom' => 34];
    $innerW = $chart['w'] - $chart['left'] - $chart['right'];
    $innerH = $chart['h'] - $chart['top'] - $chart['bottom'];
    $n = count($timeSeries);
    $chartPageviews = (int) collect($timeSeries)->sum('pageviews');
    $chartVisitors = (int) collect($timeSeries)->sum('visitors');
    $chartBucketCount = $n;
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
                'label' => $point['label'],
                'value' => (int) $point[$seriesKey],
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
    <div class="section-intro reveal">
        <h2>Vue d'ensemble</h2>
        <p>Les variations comparent cette période à la précédente, de même durée.</p>
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
                <div class="value">{{ $kpi['value'] }}@if ($kpi['suffix'] !== '')<span class="suffix">{{ $kpi['suffix'] }}</span>@endif</div>
                <div class="comparison">
                    <span class="change {{ $changeClass }}">{{ $formatChange($metricComparison) }}</span>
                    <span class="comparison-label">vs période précédente</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="panel reveal">
        <h2>Fréquentation</h2>
        <p class="panel-subtitle">Pages vues et visiteurs uniques par {{ $period === '24h' ? 'heure' : 'jour' }}.</p>
        @if ($hasChartData)
            <div class="chart-toolbar">
                <div class="legend" aria-label="Séries du graphique">
                    <button type="button" class="series-toggle" data-series-toggle="pageviews" aria-controls="chart-series-pageviews" aria-pressed="true">
                        <span class="legend-dot pv" aria-hidden="true"></span> Pages vues
                    </button>
                    <button type="button" class="series-toggle" data-series-toggle="visitors" aria-controls="chart-series-visitors" aria-pressed="true">
                        <span class="legend-dot vis" aria-hidden="true"></span> Visiteurs
                    </button>
                </div>
                <span class="muted">Survolez ou sélectionnez un point</span>
            </div>
            <div class="chart-wrap" data-chart>
                <div class="chart-tooltip" role="status" aria-live="polite" hidden></div>
                <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" preserveAspectRatio="none" role="img" aria-labelledby="traffic-chart-title traffic-chart-description" aria-describedby="traffic-chart-summary">
                    <title id="traffic-chart-title">Fréquentation sur la période</title>
                    <desc id="traffic-chart-description">Évolution des pages vues et des visiteurs uniques.</desc>
                    <g class="chart-grid" aria-hidden="true">
                        @for ($i = 0; $i <= $gridLines; $i++)
                            @php
                                $y = $chart['top'] + $innerH - $i * $innerH / $gridLines;
                                $value = round($maxValue * $i / $gridLines);
                            @endphp
                            <line x1="{{ $chart['left'] }}" y1="{{ $y }}" x2="{{ $chart['w'] - $chart['right'] }}" y2="{{ $y }}" />
                            <text x="{{ $chart['left'] - 8 }}" y="{{ $y + 3 }}" text-anchor="end">{{ $value }}</text>
                        @endfor
                        @if ($n > 1)
                            @foreach ($timeSeries as $index => $point)
                                @if ($index === 0 || $index === $n - 1 || ($index % max(1, intdiv($n, 6))) === 0)
                                    <text class="chart-label" x="{{ $chartPoint($index, 0)[0] }}" y="{{ $chart['h'] - 7 }}" text-anchor="middle">{{ $point['label'] }}</text>
                                @endif
                            @endforeach
                        @endif
                    </g>
                    @foreach (['pageviews' => 'pv', 'visitors' => 'vis'] as $seriesKey => $seriesClass)
                        <g id="chart-series-{{ $seriesKey }}" data-series-layer="{{ $seriesKey }}" aria-hidden="false">
                            <path class="chart-area {{ $seriesClass }}" d="{{ $areaPath($seriesKey) }}" />
                            <polyline class="chart-line {{ $seriesClass }}" points="{{ $linePoints($seriesKey) }}" />
                            @foreach ($chartPoints[$seriesKey] as $point)
                                <circle class="chart-point {{ $seriesClass }}" data-chart-point tabindex="0" role="img" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" aria-label="{{ $point['label'] }} : {{ $point['value'] }} {{ $seriesKey === 'pageviews' ? 'pages vues' : 'visiteurs' }}" data-tooltip="{{ $point['label'] }} — {{ $point['value'] }} {{ $seriesKey === 'pageviews' ? 'pages vues' : 'visiteurs' }}" />
                            @endforeach
                        </g>
                    @endforeach
                </svg>
            </div>
            <p id="traffic-chart-summary" class="chart-summary sr-only" role="status">Résumé vocal : {{ number_format($chartPageviews, 0, ',', ' ') }} {{ $chartPageviews === 1 ? 'page vue' : 'pages vues' }}, {{ number_format($chartVisitors, 0, ',', ' ') }} {{ $chartVisitors === 1 ? 'visiteur unique' : 'visiteurs uniques' }}, sur {{ $chartBucketCount }} {{ $chartBucketCount === 1 ? 'bucket affiché' : 'buckets affichés' }}. Le bucket courant incomplet n'est pas inclus.</p>
            <details class="chart-data-details">
                <summary>Afficher les données en tableau</summary>
                <p class="chart-data-note muted">Le bucket courant incomplet n'est pas inclus dans les données affichées.</p>
                <div class="table-scroll">
                    <table>
                        <caption class="sr-only">Données de fréquentation par {{ $period === '24h' ? 'heure' : 'jour' }} : seuls les buckets affichés sont listés.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Période</th>
                                <th scope="col">Pages vues</th>
                                <th scope="col">Visiteurs uniques</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeSeries as $point)
                                <tr>
                                    <td>{{ $point['label'] }}</td>
                                    <td class="num">{{ $point['pageviews'] }}</td>
                                    <td class="num">{{ $point['visitors'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            <script defer src="{{ route('analytics.dashboard.script') }}"></script>
        @elseif ($hasPeriodData)
            <div class="empty">La période contient des données, mais le bucket en cours n'est pas clôturé. Le graphique l'affichera à sa clôture.</div>
        @else
            <div class="empty">Aucune donnée de fréquentation sur cette période.</div>
        @endif
    </div>

    <div class="grid-2 reveal">
        <div class="panel">
            <h2>Pages les plus consultées</h2>
            <p class="panel-subtitle">Les visiteurs uniques et la profondeur de consultation par page.</p>
            @if ($topPages->isEmpty())
                <div class="empty">Aucune page vue sur cette période.</div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Page</th><th class="num">Vues</th><th class="num">Visiteurs</th><th class="num">Pages / visiteur</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($topPages as $row)
                                <tr>
                                    <td class="url" title="{{ $row->url }}">{{ $row->url ?? '—' }}</td>
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
            <h2>Sources d'acquisition</h2>
            <p class="panel-subtitle">Part des pages vues, visiteurs apportés et engagement associé.</p>
            @if ($topSources->isEmpty())
                <div class="empty">Aucune source identifiée sur cette période.</div>
            @else
                <div class="bar-list">
                    @foreach ($topSources as $row)
                        @php $share = $barShare($row->pageviews, $sourceTotal); @endphp
                        <div class="bar-row">
                            <div class="bar-row-head"><span title="{{ $row->source }}">{{ $row->source }}</span><span>{{ number_format($share, 1, ',', ' ') }} % · {{ $row->pageviews }} vues</span></div>
                            <div class="bar-track" aria-hidden="true"><div class="bar-fill" style="width: {{ $share }}%"></div></div>
                            <div class="bar-row-meta"><span>{{ $row->visitors }} visiteurs uniques</span><span>{{ number_format($row->pagesPerVisitor, 1, ',', ' ') }} pages / visiteur</span></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid-2 reveal">
        <div class="panel">
            <h2>Mix appareils</h2>
            <p class="panel-subtitle">Répartition des visiteurs uniques par appareil détecté.</p>
            @if ($topDevices->isEmpty())
                <div class="empty">Aucun appareil identifié sur cette période.</div>
            @else
                <div class="bar-list">
                    @foreach ($topDevices as $row)
                        @php $share = $barShare($row->count, $deviceTotal); @endphp
                        <div class="bar-row">
                            <div class="bar-row-head"><span>{{ $row->label }}</span><span>{{ $row->count }} visiteurs</span></div>
                            <div class="bar-track" aria-hidden="true"><div class="bar-fill" style="width: {{ $share }}%"></div></div>
                            <div class="bar-row-meta"><span>Audience unique</span><span>{{ number_format($share, 1, ',', ' ') }} %</span></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2>Événements principaux</h2>
            <p class="panel-subtitle">Les actions custom les plus fréquentes sur la période.</p>
            @if ($topEvents->isEmpty())
                <div class="empty">Aucun événement custom sur cette période.</div>
            @else
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Événement</th><th class="num">Occurrences</th></tr></thead>
                        <tbody>
                            @foreach ($topEvents as $row)
                                <tr><td>{{ $row->name }}</td><td class="num">{{ $row->count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="grid-3 reveal">
        @foreach ([['title' => 'Navigateurs', 'rows' => $topBrowsers], ['title' => 'Systèmes', 'rows' => $topOs]] as $breakdown)
            <div class="panel">
                <h2>{{ $breakdown['title'] }}</h2>
                @if ($breakdown['rows']->isEmpty())
                    <div class="empty">Aucune donnée disponible sur cette période.</div>
                @else
                    <table>
                        <tbody>
                            @foreach ($breakdown['rows'] as $row)
                                <tr><td>{{ $row->label }}</td><td class="num">{{ $row->count }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach

        <div class="panel">
            <h2>Pays</h2>
            @if ($topCountries->isEmpty())
                <div class="empty">Aucun pays identifié sur cette période.</div>
            @else
                <table>
                    <tbody>
                        @foreach ($topCountries as $row)
                            <tr><td>{{ $countryName($row->code) }}</td><td class="num">{{ $row->visitors }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel reveal">
        <h2>Flux récent</h2>
        <p class="panel-subtitle">Les 20 dernières interactions enregistrées.</p>
        @if ($recentEvents->isEmpty())
            <div class="empty">Aucun événement récent à afficher.</div>
        @else
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Date</th><th>Type</th><th>Détail</th><th>Navigateur</th></tr></thead>
                    <tbody>
                        @foreach ($recentEvents as $event)
                            <tr>
                                <td>{{ $event->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td><span class="badge {{ $event->type }}">{{ $event->type === 'pageview' ? 'Page vue' : 'Événement' }}</span></td>
                                <td class="url" title="{{ $event->url ?? '' }}">{{ $event->name ?? $event->url ?? '—' }}</td>
                                <td>{{ $event->visitor?->browser ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
