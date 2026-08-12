# AGENTS.md — laravel-analytics

## Nature du repo

Ce repo est un **package Composer** (`mltstephane/laravel-analytics`, type library, MIT), pas une application Laravel : pas de `app/`, pas d'`artisan`, pas de routes de l'hôte. Le code vit dans `src/` (PSR-4 `MltStephane\LaravelAnalytics\`), les tests dans `tests/Feature/`. Ne pas confondre avec un projet app : les conventions d'app Laravel (Livewire, Pest, etc.) ne s'appliquent pas ici.

## Commandes

- `composer test` — suite PHPUnit complète.
- `vendor/bin/phpunit --filter=NomDuTest` — ciblage d'un test précis.
- `composer fix` — Pint, pour formater le code modifié.
- `composer validate` — validation du `composer.json`.

## Environnement de test

Tests PHPUnit (pas Pest) via Testbench : `tests/TestCase.php` étend Orchestra Testbench — sqlite `:memory:`, cache array (rate limiter in-memory), `app.url = http://localhost`, migrations chargées dans `setUp`. Le time travel est autorisé via `Carbon::setTestNow()` (pattern déjà utilisé dans les tests de régression).

## Conventions de code

- ULIDs (`HasUlids`) pour toutes les clés primaires.
- Méthode `casts()` (pas la propriété `$casts`).
- Migrations sans méthode `down()`.
- Enums backed string PascalCase avec `label()` en français.
- Services statiques repository-style (ex. `DashboardService::overview()`), sans état ni injection.
- **SQL dialect-aware** : passer par `durationExpression()` / `bucketExpression()`, ne jamais écrire de SQL mono-driver.
- Normalisation bornée : `Str::limit`, max 50 propriétés/event, nombres arrondis 4 décimales.
- Le manager ne jette jamais vers le caller : try/catch + `Log::warning` + retour `null`.
- Ne pas réintroduire le visiteur "server" dans les métriques de session (exclusion `whereNotIn`), ne pas déborner la durée moyenne (bornage à la fin de la période).
- Vues Blade sombres avec CSS inline et chart SVG server-rendered, aucun asset externe.
- Tracker JS vanilla ES5, sans dépendance, ~2 Ko, uuid en localStorage (pas de cookies), DNT respecté, endpoint via `data-endpoint`.

## Sécurité (non négociable)

- Pas de cookies, pas de fingerprinting, pas d'IP stockée (la géolocalisation ne fait que lire l'IP, elle ne la persiste jamais).
- Endpoint de collecte : POST-only, allow-list de domaines (Origin/Referer), rate-limit 60/min/IP, 422 sur validation, SQL 100 % paramétré.
- L'absence de CSRF sur le endpoint de collecte est **par design** — ne pas la "corriger" sans discussion documentée.
- Tout changement du modèle de sécurité = revue + tests.
- `screen` accepté mais jamais stocké (privacy).

## Git

- L'agent ne commit ni ne push jamais : l'utilisateur s'en charge.
- Style de commits existant : `feat:` / `fix:` courts (ex. `fix: base session duration on last activity time`).
- Historique rewrité récemment (4 commits sur `main`).
- Tags semver `1.0.x` : la prod (kinot.fr via Ploi) se met à jour par tag + `composer update` + redéploiement, donc un fix livré = nouveau tag.
- `composer.lock` non versionné (convention libraries).

## Conventions du package

- Auto-discovery via `extra.laravel` (provider + alias `Analytics`).
- Publishing tags `laravel-config` et `laravel-views`.
- Config mergée avec `mergeConfigFrom` ; routes, vues et migrations chargées par le provider.
- Toute clé de config est documentée dans `config/analytics.php` ET dans le README (les deux à mettre à jour ensemble).
- Le tracker sert `resources/js/analytics.js` via `AnalyticsScriptController`.

## Tests obligatoires

- Feature tests via `$this->postJson(route('analytics.collect'), ...)`.
- Ne jamais supprimer ni casser les tests de régression des métriques (`DashboardMetricsRegressionTest` : bounce multi-sessions, durée timestamps, bornage de période, exclusion du visiteur server).
- Toute feature ou fix = test dédié (ou mise à jour d'un test existant).
- Avant finalisation : `composer test` vert + `composer fix` Pint propre.

## Fichiers de référence

- `README.md` — doc utilisateur.
- `config/analytics.php` — contrat de config.
- `routes/analytics.php` — surface HTTP.
- `src/Analytics.php` — cœur de la collecte.
- `src/Services/DashboardService.php` — métriques.
