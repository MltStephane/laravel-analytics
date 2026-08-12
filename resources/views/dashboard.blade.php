@extends('analytics::layout')

@section('period-links')
    @foreach (['24h' => '24 h', '7d' => '7 jours', '30d' => '30 jours', '90d' => '90 jours'] as $key => $label)
        <a href="{{ route('analytics.dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
@endsection

@php
    // Format helpers
    $fmtDuration = function (int $seconds): string {
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return sprintf('%d:%02d', $m, $s);
    };

    // Country code -> French name (short built-in list)
    $countryNames = [
        'FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'CA' => 'Canada',
        'US' => 'États-Unis', 'DE' => 'Allemagne', 'UK' => 'Royaume-Uni', 'GB' => 'Royaume-Uni',
        'ES' => 'Espagne', 'IT' => 'Italie', 'PT' => 'Portugal', 'MA' => 'Maroc',
        'SN' => 'Sénégal', 'CI' => "Côte d'Ivoire",
    ];
    $countryName = fn (string $code): string => $countryNames[$code] ?? $code;

    // SVG chart
    $chart = ['w' => 800, 'h' => 220, 'pad' => 10];
    $hasData = collect($timeSeries)->contains(fn ($p) => $p['pageviews'] > 0 || $p['visitors'] > 0);
    $maxValue = $hasData ? max(1, collect($timeSeries)->flatMap(fn ($p) => [$p['pageviews'], $p['visitors']])->max()) : 1;
    $innerW = $chart['w'] - $chart['pad'] * 2;
    $innerH = $chart['h'] - $chart['pad'] * 2;
    $n = count($timeSeries);
    $chartPoint = function (int $index, int $value, int $max) use ($chart, $innerW, $innerH, $n) {
        $x = $chart['pad'] + ($n <= 1 ? 0 : $index * $innerW / ($n - 1));
        $y = $chart['pad'] + $innerH - ($value / $max) * $innerH;
        return [$x, $y];
    };
    $points = fn (string $key): string => implode(' ', collect($timeSeries)->map(function ($p, $i) use ($chartPoint, $maxValue, $key) {
        [$x, $y] = $chartPoint($i, $p[$key], $maxValue);
        return round($x, 1).','.round($y, 1);
    })->all());
    $gridLines = 4;
@endphp

@section('content')
    <div class="cards">
        <div class="card">
            <div class="label">Visiteurs uniques</div>
            <div class="value">{{ $visitors }}</div>
        </div>
        <div class="card">
            <div class="label">Pages vues</div>
            <div class="value">{{ $pageviews }}</div>
        </div>
        <div class="card">
            <div class="label">Pages / visite</div>
            <div class="value">{{ $viewsPerVisit }}</div>
        </div>
        <div class="card">
            <div class="label">Taux de rebond</div>
            <div class="value">{{ $bounceRate }}&nbsp;%</div>
        </div>
        <div class="card">
            <div class="label">Durée moyenne de visite</div>
            <div class="value">{{ $fmtDuration($avgDuration) }}</div>
        </div>
    </div>

    <div class="panel">
        <h2>Fréquentation</h2>
        @if ($hasData)
            <div class="legend">
                <span class="pv">Pages vues</span>
                <span class="vis">Visiteurs</span>
            </div>
            <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" role="img" aria-label="Fréquentation sur la période">
                @for ($i = 0; $i <= $gridLines; $i++)
                    @php
                        $y = $chart['pad'] + $innerH - $i * $innerH / $gridLines;
                        $val = round($maxValue * $i / $gridLines);
                    @endphp
                    <line x1="{{ $chart['pad'] }}" y1="{{ $y }}" x2="{{ $chart['w'] - $chart['pad'] }}" y2="{{ $y }}"
                          stroke="#232936" stroke-width="1" stroke-dasharray="3 3"/>
                    <text x="{{ $chart['pad'] - 4 }}" y="{{ $y + 3 }}" text-anchor="end" fill="#8b93a3" font-size="10">{{ $val }}</text>
                @endfor
                <polyline points="{{ $points('pageviews') }}" fill="none" stroke="#5b8cff" stroke-width="2"/>
                <polyline points="{{ $points('visitors') }}" fill="none" stroke="#37c7a6" stroke-width="2"/>
                @if ($n > 1)
                    @foreach ($timeSeries as $i => $point)
                        @if ($i === 0 || $i === $n - 1 || ($i % max(1, intdiv($n, 6))) === 0)
                            <text x="{{ $chartPoint($i, 0, 1)[0] }}" y="{{ $chart['h'] - 2 }}" text-anchor="middle"
                                  fill="#8b93a3" font-size="10">{{ $point['label'] }}</text>
                        @endif
                    @endforeach
                @endif
            </svg>
        @else
            <div class="empty">Aucune donnée sur la période</div>
        @endif
    </div>

    <div class="grid-2">
        <div class="panel">
            <h2>Top pages</h2>
            @if ($topPages->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="num">Vues</th>
                            <th class="num">Visiteurs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topPages as $row)
                            <tr>
                                <td class="url" title="{{ $row->url }}">{{ $row->url ?? '—' }}</td>
                                <td class="num">{{ $row->pageviews }}</td>
                                <td class="num">{{ $row->visitors }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <h2>Sources</h2>
            @if ($topSources->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th class="num">Pages vues</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topSources as $row)
                            <tr>
                                <td>{{ $row->source }}</td>
                                <td class="num">{{ $row->pageviews }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="grid-3">
        <div class="panel">
            <h2>Navigateurs</h2>
            @if ($topBrowsers->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <tbody>
                        @foreach ($topBrowsers as $row)
                            <tr>
                                <td>{{ $row->label }}</td>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <h2>Systèmes</h2>
            @if ($topOs->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <tbody>
                        @foreach ($topOs as $row)
                            <tr>
                                <td>{{ $row->label }}</td>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <h2>Appareils</h2>
            @if ($topDevices->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <tbody>
                        @foreach ($topDevices as $row)
                            <tr>
                                <td>{{ $row->label }}</td>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h2>Pays</h2>
            @if ($topCountries->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Pays</th>
                            <th class="num">Visiteurs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topCountries as $row)
                            <tr>
                                <td>{{ $countryName($row->code) }}</td>
                                <td class="num">{{ $row->visitors }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="panel">
            <h2>Top événements</h2>
            @if ($topEvents->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th class="num">Occurrences</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topEvents as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td class="num">{{ $row->count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="panel">
        <h2>Derniers événements</h2>
        @if ($recentEvents->isEmpty())
            <div class="empty">—</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Détail</th>
                        <th>Navigateur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentEvents as $event)
                        <tr>
                            <td>{{ $event->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>
                                <span class="badge {{ $event->type }}">{{ $event->type === 'pageview' ? 'Page vue' : 'Événement' }}</span>
                            </td>
                            <td class="url" title="{{ $event->url ?? '' }}">{{ $event->name ?? $event->url ?? '—' }}</td>
                            <td>{{ $event->visitor?->browser ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
