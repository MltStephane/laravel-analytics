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
    private function seedDashboardEvent(Carbon $createdAt, string $uuid = 'view-smoke-visitor'): void
    {
        $visitor = Visitor::query()->create([
            'uuid' => $uuid,
            'browser' => 'Chrome',
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
        $response->assertSee('<details class="chart-data-details">', false);
        $response->assertSee('Afficher les données en tableau', false);
        $response->assertSee('id="traffic-chart-summary"', false);
        $response->assertSee('class="chart-summary sr-only"', false);
        $response->assertSee('role="status"', false);
        $response->assertSee('aria-describedby="traffic-chart-summary"', false);
        $response->assertSee("Résumé vocal : 1 page vue, 1 visiteur unique, sur 7 buckets affichés. Le bucket courant incomplet n'est pas inclus.", false);
        $response->assertSee('<caption class="sr-only">Données de fréquentation par jour : seuls les buckets affichés sont listés.</caption>', false);
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
            $response->assertSee('<caption class="sr-only">Données de fréquentation par jour : seuls les buckets affichés sont listés.</caption>', false);
            $response->assertSee('<th scope="col">Période</th>', false);
            $response->assertSee('<th scope="col">Pages vues</th>', false);
            $response->assertSee('<th scope="col">Visiteurs uniques</th>', false);

            $html = (string) $response->getContent();
            $normalizedHtml = (string) preg_replace('/\s+/', ' ', $html);

            $this->assertStringContainsString(
                '<tr> <td>10 août</td> <td class="num">1</td> <td class="num">1</td> </tr>',
                $normalizedHtml
            );
            $this->assertStringNotContainsString('<td>12 août</td>', $html);

            $chartPosition = strpos($html, '<div class="chart-wrap"');
            $detailsPosition = strpos($html, '<details class="chart-data-details">');

            $this->assertNotFalse($chartPosition);
            $this->assertNotFalse($detailsPosition);
            $this->assertLessThan($detailsPosition, $chartPosition);
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
            $response->assertSee("La période contient des données, mais le bucket en cours n'est pas clôturé.", false);
            $response->assertDontSee('Aucune donnée de fréquentation sur cette période.', false);
            $response->assertDontSee('id="traffic-chart-summary"', false);
            $response->assertDontSee('<details class="chart-data-details">', false);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    public function test_dashboard_shows_a_dash_for_an_empty_comparison(): void
    {
        $this->withoutMiddleware();

        $response = $this->get(route('analytics.dashboard', ['period' => '7d']));

        $response->assertOk();
        $response->assertSee('—', false);
        $response->assertDontSee('id="traffic-chart-summary"', false);
        $response->assertDontSee('<details class="chart-data-details">', false);
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
    }

    public function test_dashboard_route_keeps_web_and_auth_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('analytics.dashboard');

        $this->assertNotNull($route);
        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth', $route->middleware());
    }
}
