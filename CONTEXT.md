# Laravel Analytics — Contexte du projet

## Ce qu'est le projet

`mltstephane/laravel-analytics` est un **package Composer** (type library, licence MIT), pas une application Laravel. Il fournit de l'analytics privacy-first pour sites Laravel, inspiré d'Umami : tracking de visiteurs et d'actions sans cookies, sans données personnelles, sans IP stockée, avec dashboard intégré.

## Pour qui

- **(a) Les développeurs Laravel qui l'intègrent** : ajout du package via `composer require mltstephane/laravel-analytics`, usage de la facade `Analytics`, de la directive Blade `@analytics`, du tracker JS et des middlewares fournis.
- **(b) Les propriétaires de sites qui lisent le dashboard** : consultation de `/analytics` (protégé par `web` + `auth` par défaut) pour suivre les métriques de trafic.
- **(c) Le mainteneur (MltStephane)** : développe, teste, versionne et déploie ; la production est sur kinot.fr via Ploi, avec des releases semver `1.0.x`.

## Règles d'or

1. **Privacy-first radical** — aucun cookie, aucun fingerprinting, aucune IP stockée, DNT respecté par défaut, données normalisées et bornées.
2. **Robustesse silencieuse** — le manager `Analytics` ne jette jamais vers la requête appelante : try/catch, `Log::warning`, retour `null`.
3. **DX propre** — facade `Analytics::track()` / `Analytics::pageview()`, directive `@analytics`, tracker JS ~2 Ko, endpoint de collecte sécurisé.

## Ce que le package fait / ne fait pas

### FAIT

- Collecte de pageviews et d'événements custom via `POST analytics/collect`.
- Sessions avec fenêtre d'inactivité 30 min, landing page, referrer domain, UTM, bounce, durée.
- Dashboard `/analytics` (périodes `24h` / `7d` / `30d` / `90d`) : visiteurs uniques, pages vues, pages/visite, taux de rebond, durée moyenne, série temporelle SVG server-rendered, top pages / sources / navigateurs / OS / appareils / pays / événements, 20 derniers événements.
- Tracking serveur : facade + middleware `analytics.track-pageview`.
- Géolocalisation optionnelle via le contract `LocationResolver`.
- Rétention des données via la commande `analytics:prune`.
- Détection de bots, rate-limit, allow-list de domaines.

### NE FAIT PAS

- Pas de JSON API publique des données.
- Pas d'exports CSV/PDF.
- Pas d'authentification gérée (dépend des middlewares de l'app hôte).
- Pas de segmentation/funnels avancés.
- Pas de multi-sites : une instance = un site.

## Domaine métier — vocabulaire

| Terme | Définition |
| --- | --- |
| **Visitor** | Visiteur identifié par un uuid client conservé en localStorage ; aucun cookie, aucun fingerprint, aucune IP stockée. |
| **Session** | Période d'activité d'un visiteur ; fenêtre d'inactivité 30 min, landing page, `referrer_domain`, UTM, bounce, durée. |
| **Event** | Pageview ou événement custom, avec `name` / `url` / `title` / `data` JSON normalisé. |
| **Server visitor** | Visiteur partagé `server.visitor_uuid` (défaut `'server'`) utilisé pour les événements serveur ; exclu des métriques de session pour ne pas fausser durée et rebond. |
| **Bounce** | Session à une seule page vue. |
| **Durée de visite** | Première → dernière activité, bornée à la fin de la période analysée. |
| **Pages/visite** | Pageviews / visiteurs uniques. |
| **Source** | Domaine du referrer, ou `(direct)` via `COALESCE`. |
| **Période** | `24h` / `7d` / `30d` / `90d`. |

## Architecture technique

**Stack** : PHP `^8.2` ; `laravel/framework` `^11|^12|^13` en dépendance (pas de code app hôte) ; `matomo/device-detector` `^6.4` (bots + parsing UA) ; dev : `orchestra/testbench` `^9|^10|^11`, `phpunit/phpunit` `^11|^12`, `laravel/pint` `^1`.

**Structure** (`src/`, PSR-4 `MltStephane\LaravelAnalytics\`) :

```
src/
├── Analytics.php                # Manager : track(), pageview(), collect() — ne jette jamais vers le caller
├── AnalyticsServiceProvider.php # Singleton analytics, mergeConfigFrom, loadViewsFrom/MigrationsFrom, routes si enabled
├── Commands/
│   └── PruneAnalyticsData.php   # analytics:prune, rétention prune.days (365), chunkById(500), sessions/visitors orphelins
├── Contracts/
│   └── LocationResolver.php     # resolve(string $ip): ?array (country/region/city)
├── Enums/
│   └── EventType.php            # Pageview / Event, label() en français
├── Facades/
│   └── Analytics.php            # Accessor 'analytics'
├── Http/
│   ├── Controllers/             # CollectController (422), AnalyticsScriptController, DashboardController
│   └── Middleware/              # CollectMiddleware (POST-only, rate limit, allow-list), TrackPageview (terminable)
├── Models/                      # Visitor, Session, Event — ULIDs, casts(), scopes
├── Services/
│   └── DashboardService.php     # Agrégations statiques repository-style, SQL dialect-aware
└── Support/
    ├── Uri.php                  # hostname, domainFrom, pathAndQuery, truncate
    └── UserAgent.php            # Wrapper matomo/device-detector
```

**Patterns** :

- ULIDs partout (`HasUlids`), méthode `casts()` (pas la propriété `$casts`).
- Migrations sans méthode `down()` (convention), index sur les colonnes de requêtage.
- Services statiques repository-style (ex. `DashboardService::overview()`), sans état ni injection.
- SQL dialect-aware : `durationExpression()` et `bucketExpression()` portables sqlite/mysql/pgsql.
- Enums backed string avec `label()` en français.
- Normalisation bornée : max 50 propriétés/event, strings tronquées 500 chars, clés 100 chars, arrays → string 500, nombres arrondis 4 décimales, nom d'event 50 chars, url 2048, title 255 ; `screen` accepté mais jamais stocké ; 422 sur validation.
- Requêtes toujours paramétrées (aucun string utilisateur interpolé).

**Modèle de données** : 3 tables — `analytics_visitors`, `analytics_sessions`, `analytics_events` — relations visitor 1-N session 1-N event.

**Surface HTTP** (routes/analytics.php) :

| Nom | Méthode | Path | Middleware |
| --- | --- | --- | --- |
| `analytics.script` | GET | `js/tracker.js` (config `tracker.script_path`) | public, hors CSRF |
| `analytics.collect` | POST | `analytics/collect` (config `collect.uri`) | `analytics.collect` |
| `analytics.dashboard` | GET | `analytics` (config `dashboard.prefix`) | middleware dashboard (`web`, `auth`) |

**Modèle de sécurité** : l'endpoint de collecte est volontairement sans CSRF (by design), compensé par une allow-list de domaines via Origin/Referer, un rate-limit `analytics-collect:<ip>` de 60/min → 429, une validation stricte → 422 et du SQL 100 % paramétré. Les bots sont ignorés. Aucune IP n'est persistée (utilisée uniquement pour la géolocalisation optionnelle via `LocationResolver`).

## Anti-patterns à éviter

- ❌ Ajouter cookies ou fingerprinting.
- ❌ Stocker une IP.
- ❌ Affaiblir les gardes du endpoint de collecte (un "fix" CSRF, une allow-list relâchée) sans revue.
- ❌ Écrire du SQL mono-driver.
- ❌ Faire thrower le manager vers le caller.
- ❌ Supprimer les tests de régression des métriques dashboard.
- ❌ Ajouter `down()` dans les migrations.
- ❌ Ajouter une dépendance au tracker JS (doit rester vanilla ES5, ~2 Ko).
- ❌ Interpoler un input utilisateur dans une requête.
- ❌ Commit/push à la place de l'utilisateur.
