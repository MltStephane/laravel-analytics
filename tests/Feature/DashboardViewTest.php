<?php

namespace MltStephane\LaravelAnalytics\Tests\Feature;

use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use MltStephane\LaravelAnalytics\Models\Event;
use MltStephane\LaravelAnalytics\Models\Session;
use MltStephane\LaravelAnalytics\Models\Visitor;
use MltStephane\LaravelAnalytics\Tests\TestCase;

class DashboardViewTest extends TestCase
{
    private function seedDashboardEvent(Carbon $createdAt, string $uuid = 'view-smoke-visitor', ?string $os = null, ?string $country = null): void
    {
        $visitor = Visitor::query()->create([
            'uuid' => $uuid,
            'browser' => 'Chrome',
            'os' => $os,
            'country' => $country,
            'device_type' => 'desktop',
            'first_seen_at' => $createdAt,
            'last_seen_at' => $createdAt,
        ]);
        $session = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/dashboard',
            'started_at' => $createdAt,
            'last_activity_at' => $createdAt,
            'duration' => 3600,
            'bounced' => false,
            'pages_count' => 2,
        ]);
        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => 'pageview',
            'url' => '/dashboard',
            'created_at' => $createdAt,
        ]);
    }

    public function test_dashboard_renders_interactive_sections_without_dashboard_middleware(): void
    {
        $now = Carbon::now();
        $this->seedDashboardEvent($now->copy()->subDay());

        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('data-series-toggle="pageviews"', false);
        $response->assertSee('data-series-toggle="visitors"', false);
        $response->assertSee('data-chart-point', false);
        $response->assertSee('data-label=', false);
        $response->assertSee('data-pageviews=', false);
        $response->assertSee('data-visitors=', false);
        $response->assertSee('class="chart-hit"', false);
        $response->assertSee('.chart-point.is-hovered', false);
        $response->assertSee('<details class="chart-data-details">', false);
        $response->assertSee('Afficher les données en tableau', false);
        $response->assertSee('id="traffic-chart-summary"', false);
        $response->assertSee('class="chart-summary sr-only"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-describedby="traffic-chart-summary"', false);
        $response->assertSee("Bilan complet des intervalles du tableau sur la période, indépendant du filtre visuel : 1 page vue, 1 visiteur unique. Le tableau couvre 7 intervalles. L'intervalle courant incomplet n'est pas inclus.", false);
        $response->assertSee('Le bilan complet des intervalles du tableau reste indépendant des séries masquées.', false);
        $response->assertSee('<caption class="sr-only">Données de fréquentation par jour : seuls les intervalles affichés sont listés.</caption>', false);
        $response->assertSee('<th scope="col">Période</th>', false);
        $response->assertSee('<th scope="col">Pages vues</th>', false);
        $response->assertSee('<th scope="col">Visiteurs uniques</th>', false);
        $response->assertSee('Pages les plus consultées');
        $response->assertSee("Sources d'acquisition", false);
        $response->assertSee('Mix appareils');
        $response->assertSee('Événements principaux');
        $response->assertSee('Flux récent');
        $response->assertSee('aria-current="page"', false);
        $response->assertSee(route('analytics.dashboard.script'), false);
    }

    public function test_dashboard_table_lists_complete_daily_buckets_only(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 12, 12, 0, 0));

        try {
            $this->seedDashboardEvent(Carbon::create(2026, 8, 10, 15, 30, 0), 'view-table-complete');
            $this->seedDashboardEvent(Carbon::now()->subMinutes(5), 'view-table-current');
            $this->withoutMiddleware();

            $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

            $response->assertOk();
            $response->assertSee('<summary>Afficher les données en tableau</summary>', false);
            $response->assertSee('<caption class="sr-only">Données de fréquentation par jour : seuls les intervalles affichés sont listés.</caption>', false);
            $response->assertSee('<th scope="col">Période</th>', false);
            $response->assertSee('<th scope="col">Pages vues</th>', false);
            $response->assertSee('<th scope="col">Visiteurs uniques</th>', false);

            $html = (string) $response->getContent();
            $normalizedHtml = (string) preg_replace('/\s+/', ' ', $html);

            $this->assertStringContainsString(
                '<tr> <th scope="row">10 août</th> <td class="num">1</td> <td class="num">1</td> </tr>',
                $normalizedHtml
            );
            $this->assertStringNotContainsString('<th scope="row">12 août</th>', $html);

            $chartPosition = strpos($html, '<div class="chart-scroll"');
            $detailsPosition = strpos($html, '<details class="chart-data-details">');

            $this->assertNotFalse($chartPosition);
            $this->assertNotFalse($detailsPosition);
            $this->assertLessThan($detailsPosition, $chartPosition);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_chart_points_carry_merged_tooltip_data_for_the_interval(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 12, 12, 0, 0));

        try {
            // Deux visiteurs distincts, le même jour complet (10 août) : l'intervalle
            // doit porter pageviews=2 ET visitors=2, fusionnés sur chaque cercle du point.
            $this->seedDashboardEvent(Carbon::create(2026, 8, 10, 15, 30, 0), 'view-merged-a');
            $this->seedDashboardEvent(Carbon::create(2026, 8, 10, 16, 0, 0), 'view-merged-b');
            $this->withoutMiddleware();

            $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

            $response->assertOk();

            $html = (string) $response->getContent();
            $normalizedHtml = (string) preg_replace('/\s+/', ' ', $html);

            // Les 4 cercles du point « 10 août » (2 séries × hit + visible) portent
            // les valeurs fusionnées : 2 pages vues, 2 visiteurs, quel que soit le
            // cercle survolé.
            $this->assertSame(
                4,
                preg_match_all('/<circle[^>]*data-label="10 août"[^>]*data-pageviews="2"[^>]*data-visitors="2"[^>]*>/', $normalizedHtml)
            );

            // Chaque intervalle du tableau (7 jours complets) est dessiné par 2 séries,
            // chaque point de série étant rendu par 2 cercles porteurs des attributs
            // fusionnés → 4 cercles data-pageviews par intervalle.
            $detailsStart = strpos($html, '<details class="chart-data-details">');
            $detailsEnd = strpos($html, '</details>', $detailsStart);
            $detailsHtml = substr($html, $detailsStart, $detailsEnd - $detailsStart);
            $intervalCount = substr_count($detailsHtml, '<th scope="row">');

            $this->assertSame(7, $intervalCount);
            $this->assertSame(4 * $intervalCount, substr_count($html, 'data-pageviews="'));
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_explains_when_only_the_current_bucket_has_data(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 12, 12, 0, 0));

        try {
            $this->seedDashboardEvent(Carbon::now()->subMinutes(5));
            $this->withoutMiddleware();

            $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

            $response->assertOk();
            $response->assertSee("La période contient des données, mais l'intervalle en cours n'est pas clôturé.", false);
            $response->assertDontSee('Aucune donnée de fréquentation sur cette période.', false);
            $response->assertDontSee('id="traffic-chart-summary"', false);
            $response->assertDontSee('<details class="chart-data-details">', false);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_hides_comparison_when_no_data_at_all(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertDontSee('vs période précédente', false);
        $response->assertDontSee('id="traffic-chart-summary"', false);
        $response->assertDontSee('<details class="chart-data-details">', false);
    }

    public function test_dashboard_hides_comparison_when_previous_reference_value_is_zero(): void
    {
        $now = Carbon::now();

        // Période courante : 1 session rebondie d'1 page, durée 60 s.
        $visitorCurrent = Visitor::query()->create([
            'uuid' => 'view-zero-ref-current',
            'browser' => 'Chrome',
            'device_type' => 'desktop',
            'first_seen_at' => $now->copy()->subDay(),
            'last_seen_at' => $now->copy()->subDay(),
        ]);
        $sessionCurrent = Session::query()->create([
            'visitor_id' => $visitorCurrent->id,
            'hostname' => 'example.test',
            'path' => '/current',
            'started_at' => $now->copy()->subDay(),
            'last_activity_at' => $now->copy()->subDay()->addSeconds(60),
            'duration' => 60,
            'bounced' => true,
            'pages_count' => 1,
        ]);
        Event::query()->create([
            'visitor_id' => $visitorCurrent->id,
            'session_id' => $sessionCurrent->id,
            'type' => 'pageview',
            'url' => '/current',
            'created_at' => $now->copy()->subDay(),
        ]);

        // Période précédente : 1 session non rebondie, durée 0 → les références
        // de rebond (0/1) et de durée (0) sont nulles (hasPrevious=true, change=null).
        $visitorPrevious = Visitor::query()->create([
            'uuid' => 'view-zero-ref-previous',
            'browser' => 'Chrome',
            'device_type' => 'desktop',
            'first_seen_at' => $now->copy()->subDays(10),
            'last_seen_at' => $now->copy()->subDays(10),
        ]);
        $sessionPrevious = Session::query()->create([
            'visitor_id' => $visitorPrevious->id,
            'hostname' => 'example.test',
            'path' => '/previous',
            'started_at' => $now->copy()->subDays(10),
            'last_activity_at' => $now->copy()->subDays(10),
            'duration' => 0,
            'bounced' => false,
            'pages_count' => 1,
        ]);
        Event::query()->create([
            'visitor_id' => $visitorPrevious->id,
            'session_id' => $sessionPrevious->id,
            'type' => 'pageview',
            'url' => '/previous',
            'created_at' => $now->copy()->subDays(10),
        ]);

        $this->withoutMiddleware();
        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));
        $response->assertOk();

        $html = (string) $response->getContent();

        // Seuls 4 KPI (visitors, pageviews, sessions, viewsPerVisit) affichent le bloc
        // de comparaison ; bounceRate et avgDuration ont une référence précédente nulle.
        $this->assertSame(4, substr_count($html, 'vs période précédente'));
        // Cible le span de variation uniquement (évite les sous-chaînes « 0 % » de « 100,0 % »
        // dans les panneaux Sources et Appareils, présents avec ce seed).
        $this->assertSame(4, substr_count($html, '>0 %</span>'));
        $this->assertStringNotContainsString('La période précédente contenait des données, mais la valeur de référence est nulle', $html);
    }

    public function test_dashboard_shows_new_badge_when_only_current_period_has_data(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay());

        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('Nouveau');
        $response->assertSee('Aucune donnée sur la période précédente — nouvelle activité détectée', false);
    }

    public function test_dashboard_shows_new_badge_for_all_kpis_when_previous_period_is_empty(): void
    {
        $now = Carbon::now();
        $createdAt = $now->copy()->subDay();

        // Seeder identique à seedDashboardEvent mais avec une session bouncée
        // d'1 page (duration 0) : la période courante a des données, la précédente est vide.
        $visitor = Visitor::query()->create([
            'uuid' => 'view-new-badges',
            'browser' => 'Chrome',
            'os' => 'Linux',
            'country' => 'FR',
            'device_type' => 'desktop',
            'first_seen_at' => $createdAt,
            'last_seen_at' => $createdAt,
        ]);
        $session = Session::query()->create([
            'visitor_id' => $visitor->id,
            'hostname' => 'example.test',
            'path' => '/dashboard',
            'started_at' => $createdAt,
            'last_activity_at' => $createdAt,
            'duration' => 0,
            'bounced' => true,
            'pages_count' => 1,
        ]);
        Event::query()->create([
            'visitor_id' => $visitor->id,
            'session_id' => $session->id,
            'type' => 'pageview',
            'url' => '/dashboard',
            'created_at' => $createdAt,
        ]);

        $this->assertSame(1, Session::query()->whereBetween('started_at', [$now->copy()->subDays(7), $now])->count());
        $this->assertSame(0, Session::query()->whereBetween('started_at', [$now->copy()->subDays(14), $now->copy()->subDays(7)])->count());

        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        // Les 6 KPI affichent « Nouveau » : la présence de données courantes prime
        // sur la valeur de la métrique (même nulle pour la durée moyenne).
        $this->assertSame(6, substr_count((string) $response->getContent(), 'Nouveau'));
        $response->assertDontSee('Aucune donnée sur cette période ni la précédente', false);
    }

    public function test_dashboard_comparison_shows_percentage_when_both_periods_have_data(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay(), 'view-both-current-1');
        $this->seedDashboardEvent(Carbon::now()->subDays(2), 'view-both-current-2');
        $this->seedDashboardEvent(Carbon::now()->subDays(10), 'view-both-previous');

        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        // 2 pages vues courantes vs 1 sur la période précédente → +100,0 %.
        $response->assertSee('+100,0 %', false);
    }

    public function test_dashboard_footer_displays_the_package_version_or_safe_fallback(): void
    {
        $expectedVersion = 'dev';

        try {
            if (
                class_exists(InstalledVersions::class)
                && InstalledVersions::isInstalled('mltstephane/laravel-analytics')
            ) {
                $expectedVersion = InstalledVersions::getPrettyVersion('mltstephane/laravel-analytics') ?: 'dev';
            }
        } catch (\Throwable) {
            $expectedVersion = 'dev';
        }

        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $matches = [];
        $this->assertSame(
            1,
            preg_match('/Version du package : ([^<]+)<\/footer>/', (string) $response->getContent(), $matches)
        );
        $this->assertNotEmpty($matches[1]);
        $this->assertSame($expectedVersion, $matches[1]);
    }

    public function test_dashboard_script_route_returns_static_javascript(): void
    {
        $response = $this->get(route('analytics.dashboard.script'));

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('Content-Type'));
        $response->assertSee('textContent', false);
        $response->assertSee('is-hovered', false);
    }

    public function test_dashboard_route_keeps_web_and_auth_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('analytics.dashboard');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth', $route->middleware());
    }

    public function test_dashboard_hides_periods_without_data(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 12, 12, 0, 0));

        try {
            // Données présentes dans les fenêtres 7d/30d/90d mais hors 24h.
            $this->seedDashboardEvent(Carbon::now()->subDays(5));
            $this->withoutMiddleware();

            $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

            $response->assertOk();
            $response->assertSee('7 jours');
            $response->assertSee('30 jours');
            $response->assertSee('90 jours');
            $response->assertDontSee('24 h');
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_keeps_current_period_link_when_empty(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('7 jours');
        $response->assertDontSee('24 h');
        $response->assertDontSee('30 jours');
        $response->assertDontSee('90 jours');
    }

    public function test_dashboard_keeps_periods_with_data_when_current_is_older(): void
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::create(2026, 8, 12, 12, 0, 0));

        try {
            // Données présentes dans les fenêtres 30d/90d mais hors 24h/7d.
            $this->seedDashboardEvent(Carbon::now()->subDays(10));
            $this->withoutMiddleware();

            $response = $this->get(route('analytics.dashboard', ['period' => '30d']));

            $response->assertOk();
            $response->assertSee('30 jours');
            $response->assertSee('90 jours');
            $response->assertDontSee('24 h');
            $response->assertDontSee('7 jours');
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_explains_kpis_inline_without_a_modal(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay());
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('<details class="kpi-help reveal">', false);
        $response->assertSee('<summary>Comprendre les indicateurs</summary>', false);
        $response->assertSee('<dl class="kpi-definitions">', false);
        $this->assertSame(6, substr_count((string) $response->getContent(), '<dt>'));
        $this->assertSame(6, substr_count((string) $response->getContent(), '<dd>'));
        $response->assertDontSee('<dialog', false);
        $response->assertDontSee('data-modal-open', false);
        $response->assertSee('aucun fingerprinting', false);
        $response->assertSee('Sessions démarrées');
        $response->assertSee('started_at', false);
        $response->assertSee('Pages vues / visiteur');
        $response->assertSee('plafonné à la fin de la période analysée', false);
    }

    public function test_dashboard_shows_onboarding_when_nothing_has_ever_been_collected(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('Commencer à collecter des données');
        $response->assertSee('@analytics', false);
        $response->assertSee('après la première visite');
        $response->assertSee('<details class="kpi-help reveal">', false);
        $response->assertSee(route('analytics.dashboard.script'), false);
    }

    public function test_dashboard_distinguishes_an_empty_period_from_no_collection(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDays(10), 'view-outside-period');
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('Aucune donnée sur cette période');
        $response->assertDontSee('Commencer à collecter des données');
    }

    public function test_dashboard_chart_preserves_its_ratio_and_keeps_points_out_of_the_accessibility_tree(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay(), 'view-chart-semantics');
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('class="chart-scroll" data-chart tabindex="0" role="region" aria-label="Graphique de fréquentation, défilement horizontal"', false);
        $response->assertSee('<svg width="960" height="300" viewBox="0 0 960 300"', false);
        $response->assertDontSee('preserveAspectRatio="none"', false);
        $response->assertSee('Survolez un point pour afficher les pages vues et les visiteurs ; ouvrez le tableau pour le détail.');

        preg_match_all('/<circle[^>]*data-chart-point[^>]*>/', (string) $response->getContent(), $points);
        $this->assertNotEmpty($points[0]);
        foreach ($points[0] as $point) {
            $this->assertStringNotContainsString('tabindex=', $point);
            $this->assertStringNotContainsString('role=', $point);
            $this->assertStringNotContainsString('aria-label=', $point);
            $this->assertStringContainsString('data-pageviews=', $point);
            $this->assertStringContainsString('data-visitors=', $point);
        }
    }

    public function test_dashboard_tables_expose_captions_headers_and_named_scroll_regions(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay(), 'view-tables', 'Linux', 'FR');
        $session = Session::query()->firstOrFail();
        Event::query()->create([
            'visitor_id' => $session->visitor_id,
            'session_id' => $session->id,
            'type' => 'event',
            'name' => 'signup',
            'created_at' => Carbon::now()->subDay(),
        ]);
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertSame(4, substr_count($html, 'class="table-scroll" tabindex="0" role="region" aria-label='));
        $this->assertGreaterThanOrEqual(7, substr_count($html, '<caption class="sr-only">'));
        $this->assertGreaterThanOrEqual(11, substr_count($html, 'scope="col"'));
        $this->assertGreaterThanOrEqual(7, substr_count($html, 'scope="row"'));
        $this->assertSame(3, substr_count($html, '<thead class="sr-only">'));
        $response->assertSee('Faites défiler horizontalement', false);
    }

    public function test_dashboard_breakdown_tables_are_compact(): void
    {
        $this->seedDashboardEvent(Carbon::now()->subDay(), 'view-compact', 'Linux', 'FR');
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertSame(3, substr_count($html, 'class="table-compact"'));
        $response->assertSee('Navigateurs');
        $response->assertSee('Systèmes');
        $response->assertSee('Pays');
    }

    public function test_dashboard_cards_share_a_single_uniform_spacing(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $html = (string) $response->getContent();

        // Token d'espacement uniforme entre cards : 2 × la moyenne actuelle (14px).
        $this->assertStringContainsString('--card-gap: 28px;', $html);

        // Toutes les grilles et blocs cards utilisent le token, pas des valeurs disparates.
        $this->assertGreaterThanOrEqual(5, substr_count($html, 'gap: var(--card-gap);'));
        $this->assertGreaterThanOrEqual(
            7,
            substr_count($html, 'margin-bottom: var(--card-gap);')
                + substr_count($html, 'margin: 0 0 var(--card-gap);')
        );

        // Plus aucune card collée ni ancienne valeur divergente.
        $this->assertStringNotContainsString('margin: -4px 0 18px;', $html);
        $this->assertStringNotContainsString('gap: 10px;', $html);
        $this->assertStringNotContainsString('gap: 8px;', $html);
        $this->assertStringNotContainsString('gap: 14px;', $html);
        $this->assertStringNotContainsString('margin-bottom: 18px;', $html);
    }
}
