<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Analytics</title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #161a22;
            --panel-border: #232936;
            --text: #e6e9ef;
            --text-muted: #8b93a3;
            --accent: #5b8cff;
            --accent-2: #37c7a6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        header.top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        header.top h1 { font-size: 22px; margin: 0; font-weight: 600; }
        .periods { display: flex; gap: 6px; }
        .periods a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 6px;
            border: 1px solid var(--panel-border);
            background: var(--panel);
        }
        .periods a:hover { color: var(--text); }
        .periods a.active { color: #fff; background: var(--accent); border-color: var(--accent); }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .card .label { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .card .value { font-size: 24px; font-weight: 600; margin-top: 4px; }
        .panel {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .panel h2 { margin: 0 0 12px; font-size: 15px; font-weight: 600; color: var(--text); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--panel-border); }
        th { color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; font-weight: 500; }
        td.num, th.num { text-align: right; }
        td.url { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .muted { color: var(--text-muted); }
        .empty { color: var(--text-muted); text-align: center; padding: 24px 0; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            background: var(--panel-border);
            color: var(--text);
        }
        .badge.pageview { background: rgba(91, 140, 255, .18); color: #a9c2ff; }
        .badge.event { background: rgba(55, 199, 166, .18); color: #7ee0c8; }
        .legend { display: flex; gap: 16px; margin-bottom: 8px; color: var(--text-muted); font-size: 12px; }
        .legend span::before {
            content: "";
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 6px;
        }
        .legend .pv::before { background: var(--accent); }
        .legend .vis::before { background: var(--accent-2); }
        svg { display: block; width: 100%; height: auto; }
        footer { color: var(--text-muted); font-size: 12px; text-align: center; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="top">
            <h1>Analytics</h1>
            @hasSection('period-links')
                <nav class="periods">@yield('period-links')</nav>
            @endif
        </header>
        @yield('content')
        <footer>laravel-analytics — aucune donnée personnelle, aucun cookie.</footer>
    </div>
</body>
</html>
