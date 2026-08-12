<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Analytics</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #090c12;
            --panel: #111722;
            --panel-raised: #151d2b;
            --panel-border: #253044;
            --text: #f2f5fa;
            --text-muted: #91a0b6;
            --accent: #7c9cff;
            --accent-strong: #9db4ff;
            --accent-2: #3ed6b1;
            --danger: #ff8b9d;
            --warning: #f6c76e;
            --shadow: 0 18px 48px rgba(0, 0, 0, .22);
            /* Espacement uniforme entre cards : 2 × la moyenne actuelle (14px) */
            --card-gap: 28px;
        }
        * { box-sizing: border-box; }
        html { min-width: 320px; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at 10% 0%, rgba(124, 156, 255, .10), transparent 32rem),
                var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        a { color: var(--accent-strong); }
        button, a { -webkit-tap-highlight-color: transparent; }
        button:focus-visible, a:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--accent-2);
            outline-offset: 3px;
        }
        .wrap { max-width: 1220px; margin: 0 auto; padding: 28px 18px 52px; }
        header.top {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            padding-bottom: 22px;
            margin-bottom: 22px;
            border-bottom: 1px solid rgba(145, 160, 182, .18);
        }
        .brand { min-width: 220px; }
        header.top h1 { margin: 0; font-size: 25px; font-weight: 700; letter-spacing: -.03em; }
        .brand p { color: var(--text-muted); margin: 4px 0 0; font-size: 13px; }
        .top-actions { display: flex; align-items: flex-end; gap: 18px; flex-wrap: wrap; }
        .period-context { color: var(--text-muted); font-size: 12px; text-align: right; }
        .period-context strong { color: var(--text); display: block; font-weight: 500; margin-top: 2px; }
        .periods { display: flex; gap: 5px; flex-wrap: wrap; }
        .periods a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 7px 11px;
            border: 1px solid var(--panel-border);
            border-radius: 8px;
            background: rgba(17, 23, 34, .72);
            transition: color .16s ease, border-color .16s ease, background .16s ease;
        }
        .periods a:hover { color: var(--text); border-color: #455575; }
        .periods a.active { color: #07101f; background: var(--accent-strong); border-color: var(--accent-strong); font-weight: 700; }
        main { min-width: 0; }
        .section-intro { margin: 0 0 var(--card-gap); }
        .section-intro h2 { margin: 0; font-size: 18px; letter-spacing: -.02em; }
        .section-intro p { margin: 3px 0 0; color: var(--text-muted); }
        .cards {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: var(--card-gap);
            margin-bottom: var(--card-gap);
        }
        .card, .panel {
            background: linear-gradient(145deg, rgba(21, 29, 43, .98), rgba(17, 23, 34, .98));
            border: 1px solid var(--panel-border);
            box-shadow: var(--shadow);
        }
        .card { min-width: 0; border-radius: 12px; padding: 15px; }
        .card .label {
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        .kpi-help {
            margin: 0 0 var(--card-gap);
            padding: 13px 15px;
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            background: rgba(17, 23, 34, .76);
        }
        .kpi-help summary { color: var(--accent-strong); cursor: pointer; font-weight: 650; }
        .kpi-help summary:focus-visible, .chart-data-details summary:focus-visible { outline: 2px solid var(--accent-2); outline-offset: 3px; }
        .kpi-definitions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px 20px;
            margin: 16px 0 2px;
        }
        .kpi-definitions div { min-width: 0; }
        .kpi-definitions dt { color: var(--text); font-weight: 650; }
        .kpi-definitions dd { margin: 4px 0 0; color: var(--text-muted); font-size: 13px; line-height: 1.55; }
        code { color: #c9d5ff; font-size: .94em; }
        .onboarding, .period-empty-notice {
            margin-bottom: var(--card-gap);
            padding: 18px 20px;
            border: 1px solid rgba(62, 214, 177, .42);
            border-radius: 12px;
            background: rgba(62, 214, 177, .08);
        }
        .period-empty-notice { border-color: rgba(124, 156, 255, .35); background: rgba(124, 156, 255, .07); }
        .onboarding h2, .period-empty-notice h2 { margin: 0 0 5px; font-size: 16px; }
        .onboarding p, .period-empty-notice p { margin: 0; color: #bac6d8; }
        .card .value { font-size: clamp(21px, 3vw, 29px); font-weight: 700; margin-top: 8px; letter-spacing: -.04em; }
        .card .suffix { color: var(--text-muted); font-size: 14px; margin-left: 2px; }
        .card .comparison { display: flex; align-items: center; gap: 5px; min-height: 20px; margin-top: 8px; font-size: 12px; }
        .comparison-label { color: var(--text-muted); }
        .change { font-weight: 600; }
        .change.positive { color: var(--accent-2); }
        .change.negative { color: var(--danger); }
        .change.neutral { color: var(--text-muted); }
        .panel { min-width: 0; border-radius: 14px; padding: 18px; margin-bottom: var(--card-gap); }
        .panel h2 { margin: 0 0 13px; font-size: 15px; font-weight: 650; letter-spacing: -.01em; }
        .panel-subtitle { color: #a6b3c6; font-size: 13px; margin: -7px 0 13px; }
        .grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--card-gap); align-items: start; margin-bottom: var(--card-gap); }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--card-gap); align-items: start; margin-bottom: var(--card-gap); }
        .grid-2 > .panel, .grid-3 > .panel { margin-bottom: 0; }
        .chart-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .legend { display: flex; gap: 7px; flex-wrap: wrap; }
        .series-toggle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid transparent;
            border-radius: 7px;
            padding: 5px 8px;
            color: var(--text-muted);
            background: transparent;
            cursor: pointer;
            font: inherit;
            font-size: 12px;
        }
        .series-toggle:hover { color: var(--text); background: rgba(145, 160, 182, .08); }
        .series-toggle[aria-pressed="false"] { opacity: .52; }
        .legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; }
        .legend-dot.pv { background: var(--accent); }
        .legend-dot.vis { background: var(--accent-2); }
        .chart-scroll { position: relative; width: 100%; overflow-x: auto; overflow-y: hidden; }
        .chart-scroll svg { display: block; width: max(720px, 100%); height: auto; aspect-ratio: 16 / 5; }
        .chart-grid line { stroke: #273247; stroke-width: 1; stroke-dasharray: 3 4; }
        .chart-grid text, .chart-label { fill: var(--text-muted); font-size: 10px; }
        .chart-area { opacity: .16; }
        .chart-line { fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .chart-line.pv, .chart-point.pv { stroke: var(--accent); }
        .chart-line.vis, .chart-point.vis { stroke: var(--accent-2); }
        .chart-area.pv { fill: var(--accent); }
        .chart-area.vis { fill: var(--accent-2); }
        .chart-point { fill: var(--panel-raised); stroke-width: 2; cursor: crosshair; }
        .chart-point:hover, .chart-point:focus, .chart-point.is-hovered { fill: currentColor; r: 5px; }
        .chart-hit { fill: transparent; stroke: none; pointer-events: all; cursor: crosshair; }
        [data-series-layer] { opacity: 1; visibility: visible; }
        [data-series-layer].is-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .chart-tooltip {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 5;
            min-width: 150px;
            padding: 8px 10px;
            border: 1px solid var(--panel-border);
            border-radius: 8px;
            background: #0b1019;
            color: var(--text);
            box-shadow: var(--shadow);
            font-size: 12px;
            pointer-events: none;
        }
        .chart-tooltip[hidden] { display: none; }
        .chart-tooltip-title {
            font-weight: 700;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(145, 160, 182, .15);
        }
        .chart-tooltip-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 3px;
            white-space: nowrap;
        }
        .chart-tooltip-dot {
            display: inline-block;
            flex: none;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .chart-tooltip-dot.pv { background: var(--accent); }
        .chart-tooltip-dot.vis { background: var(--accent-2); }
        .chart-tooltip-row .chart-tooltip-value {
            margin-left: auto;
            font-weight: 650;
        }
        .chart-data-details { margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(145, 160, 182, .13); }
        .chart-data-details summary { color: var(--accent-strong); cursor: pointer; font-weight: 600; }
        .chart-data-note { margin: 10px 0 0; font-size: 13px; }
        .empty { color: var(--text-muted); text-align: center; padding: 28px 12px; border: 1px dashed #334057; border-radius: 9px; }
        .table-scroll { overflow-x: auto; }
        .scroll-hint { display: none; color: var(--text-muted); font-size: 12px; text-align: right; margin: 0 2px 6px; }
        table { width: 100%; min-width: 430px; border-collapse: collapse; }
        .table-compact { width: 100%; min-width: 0; }
        th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid rgba(145, 160, 182, .13); }
        tbody tr:last-child td, tbody tr:last-child th { border-bottom: 0; }
        tbody tr:hover { background: rgba(124, 156, 255, .045); }
        th { color: #a6b3c6; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; font-weight: 650; }
        tbody th { color: var(--text); font-size: inherit; text-transform: none; letter-spacing: normal; font-weight: 500; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.url, th.url { max-width: 310px; overflow-wrap: anywhere; word-break: break-word; }
        .muted { color: var(--text-muted); }
        .bar-list { display: grid; gap: 13px; }
        .bar-row-head, .bar-row-meta { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
        .bar-row-head { font-weight: 600; }
        .bar-row-head span:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-row-head span:last-child { color: var(--text-muted); font-size: 12px; white-space: nowrap; }
        .bar-track { height: 7px; background: #222c3e; border-radius: 999px; overflow: hidden; margin-top: 6px; }
        .bar-fill { height: 100%; min-width: 2px; border-radius: inherit; background: linear-gradient(90deg, var(--accent), var(--accent-2)); }
        .bar-row-meta { color: var(--text-muted); font-size: 12px; margin-top: 4px; }
        .bar-row-meta span:last-child { color: var(--accent-strong); }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            background: #263248;
            color: var(--text);
            white-space: nowrap;
        }
        .badge.pageview { background: rgba(124, 156, 255, .18); color: #b9caff; }
        .badge.event { background: rgba(62, 214, 177, .18); color: #8aebd3; }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        footer { color: var(--text-muted); font-size: 12px; text-align: center; margin-top: 34px; }
        @media (prefers-reduced-motion: no-preference) {
            .reveal { animation: dashboard-in .42s ease both; }
            .reveal:nth-child(2) { animation-delay: 45ms; }
            .reveal:nth-child(3) { animation-delay: 90ms; }
            .reveal:nth-child(4) { animation-delay: 135ms; }
            @keyframes dashboard-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }
            [data-series-layer] { transition: opacity .16s ease, visibility .16s ease; }
        }
        @media (max-width: 1060px) {
            .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .wrap { padding: 20px 12px 38px; }
            header.top { align-items: flex-start; gap: 16px; }
            .top-actions { align-items: flex-start; width: 100%; justify-content: space-between; }
            .period-context { text-align: left; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; gap: var(--card-gap); }
            .kpi-definitions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .panel { padding: 14px; }
            .scroll-hint { display: block; }
        }
        @media (max-width: 480px) {
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--card-gap); }
            .card { padding: 12px; }
            .card .value { font-size: 22px; }
            .top-actions { display: block; }
            .periods { margin-top: 12px; }
            .periods a { flex: 1 1 auto; text-align: center; }
            .kpi-definitions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="top">
            <div class="brand">
                <h1>Analytics</h1>
                <p>Une lecture simple de votre audience, sans cookie ni donnée personnelle.</p>
            </div>
            <div class="top-actions">
                @hasSection('period-context')
                    <div class="period-context">@yield('period-context')</div>
                @endif
                @hasSection('period-links')
                    <nav class="periods" aria-label="Période d'analyse">@yield('period-links')</nav>
                @endif
            </div>
        </header>
        <main>
            @yield('content')
        </main>
        @php
            $analyticsPackageVersion = 'dev';

            try {
                if (class_exists(\Composer\InstalledVersions::class)) {
                    $analyticsPackageVersion = \Composer\InstalledVersions::getPrettyVersion('mltstephane/laravel-analytics') ?: 'dev';
                }
            } catch (\Throwable) {
                $analyticsPackageVersion = 'dev';
            }
        @endphp
        <footer>laravel-analytics — aucune donnée personnelle, aucun cookie. Version du package : {{ $analyticsPackageVersion }}</footer>
    </div>
</body>
</html>
