# Personas Cible — laravel-analytics

Ce document est la référence pour toute décision UX ou fonctionnelle : chaque évolution du package doit être validée à l'aune de ces profils. Il sert de guide au développeur comme à l'agent de code.

## 1. Le développeur Laravel intégrateur

**Ex. Julien, 38 ans** — développeur back-end freelance, en charge de plusieurs sites Laravel (vitrines, e-commerce léger, SaaS).

**Situation** : il a déjà un projet Laravel 11/12/13 avec une base de données, des utilisateurs authentifiés et un design existant. Il cherche un outil d'analytics simple à brancher sur ses sites.

**Contexte** : sa précédente expérience avec Google Analytics l'a convaincu de chercher une alternative sans cookie : les popups de consentement, le poids du script et la lenteur perçue de la page lui ont posé problème. Il veut comprendre le trafic sans complexifier son code ni fragiliser la conformité RGPD de ses clients.

**Objectifs** :

- Intégrer le package en quelques minutes : `composer require mltstephane/laravel-analytics`, puis la directive `@analytics` dans le head.
- Suivre des événements custom propres : via la facade serveur, les data-attributs HTML ou `window.analytics.track` côté client.
- Publier et ajuster la config (`config/analytics.php`) sans plonger dans le code du package.

**Frustrations** :

- Les scripts de tracking lourds type GA qui ralentissent la page et exigent un consentement.
- Les configurations obscures ou les endpoints qui cassent la sécurité du site (CSRF, CORS, exposition de routes).
- Une doc qui suppose de connaître l'architecture interne du package.

**Usage de l'application** :

- Ajout du head snippet et vérification du script servi (`js/tracker.js`).
- Tracking des événements clés : signup, purchase, clics sur `[data-analytics]`.
- Publication de la config via le tag `laravel-config`, lecture du README.
- Suivi des premiers pageviews dans le dashboard pour valider l'intégration.

**Critères de décision** : DX de la facade et de la directive, clarté de la documentation, légèreté du tracker JS, sécurité par défaut (pas de route exposée, pas de donnée personnelle collectée).

## 2. Le propriétaire de site qui lit le dashboard

**Ex. Anne, 45 ans** — dirige un petit SaaS ou un site vitrine ; pas de compétence technique poussée, elle administre son site via une interface simple.

**Situation** : le développeur lui a installé le package. Elle se connecte avec son compte (middlewares `web` + `auth`) et ouvre `/analytics` pour suivre son activité.

**Contexte** : elle veut savoir si ses efforts (articles, pages produit, campagnes) portent leurs fruits, sans jargon.

**Objectifs** :

- Comprendre d'où viennent les visiteurs (sources, pays).
- Identifier les pages qui marchent et celles qui ne prennent pas.
- Savoir si un article récent a "pris".

**Frustrations** :

- Les chiffres abstraits sans signification concrète.
- Les dashboards surchargés ou les graphiques illisibles.
- Un écran vide sans explication en début de collecte ("Aucune donnée sur la période").

**Usage de l'application** :

- Consultation quotidienne ou hebdomadaire.
- Bascule entre les périodes `24h` / `7d` / `30d` / `90d`.
- Lecture des top pages, top sources, top appareils et des derniers événements.

**Critères de décision** : lisibilité des chiffres et des graphiques, empty states clairs, données actionnables (pages, sources, appareils).

## 3. Le mainteneur du package

**MltStephane** — développe, teste, versionne et déploie `mltstephane/laravel-analytics`.

**Situation** : seul mainteneur ; la production est sur kinot.fr via Ploi. Chaque release suit le flux : tag semver, `composer update` côté hôte, redéploiement.

**Contexte** : le package doit rester portable (sqlite/mysql/pgsql), léger et sûr. Les métriques du dashboard sont le cœur produit : une régression silencieuse (rebond, durée) est un bug critique.

**Objectifs** :

- Garder une suite verte (`composer test`), un code Pint propre (`composer fix`) et un `composer validate` sans erreur.
- Maintenir la portabilité SQL (expressions dialect-aware `durationExpression()` / `bucketExpression()`).
- Garantir des métriques fiables, y compris avec le visiteur partagé "server".
- Publier des releases semver propres et un historique git lisible.

**Frustrations** :

- Les régressions de métriques difficiles à repérer à l'œil (bounce, durée).
- Le biais du visiteur "server" qui fausse durée et rebond s'il rentre dans les sessions.
- Les dialectes SQL qui divergent entre sqlite, mysql et pgsql.

**Usage de l'application** :

- `composer test`, `composer fix`, revue du diff avant commit.
- Tag + push pour chaque fix (`feat:` / `fix:`), puis déploiement via Ploi.
- Surveillance du dashboard sur kinot.fr en conditions réelles.

**Critères de décision** : tests de régression, stabilité, historique git propre, portabilité.

## 4. Le visiteur tracké (confiance)

**Ex. Clara, 31 ans** — consulte un site équipé du tracker, sans le savoir ni avoir consenti (aucun cookie n'est posé).

**Situation** : elle navigue sur le site d'un client du package, préoccupée par la vie privée en ligne.

**Contexte** : elle a une conscience aiguë du tracking : bloqueurs, fuite de données, revente de profils. Elle n'a rien à accepter ici — et c'est précisément ce qu'elle attend.

**Objectifs (implicites)** :

- Que sa vie privée soit préservée : anonymat, aucun suivi inter-sites, aucun profil constitué.
- Que rien de personnel ne soit transmis ni stocké.

**Frustrations potentielles** :

- Les trackers invisibles qui collectent sans transparence.
- La revente de données de navigation.
- Les popups de consentement agressives.

**Critères** : aucun cookie, aucun fingerprint, DNT respecté, aucune IP stockée, données bornées et anonymes.

## Synthèse des Personas

| Dimension | Dev intégrateur | Propriétaire site | Mainteneur | Visiteur tracké |
| --- | --- | --- | --- | --- |
| **Rôle** | Installe et configure le package | Lit les métriques de son site | Développe, teste, versionne, déploie | Navigue sur un site équipé du tracker |
| **Maturité technique** | Élevée (Laravel, PHP) | Faible (non-technique) | Élevée (package, tests, portabilité) | Nulle (aucune interaction directe) |
| **Usage** | Facade, directive, data-attributs, config | `/analytics` : périodes, top pages/sources | `composer test`, `composer fix`, tags, Ploi | Aucun usage ; subit le tracker passivement |
| **Attente clé** | DX simple et docs claires | Lisibilité et données actionnables | Métriques fiables, tests de régression | Privacy : anonymat, pas de profil |
| **Risque principal** | Endpoint qui affaiblit la sécurité du site | Données illisibles ou écran vide | Régression silencieuse des métriques | Tracking invisible ou revente de données |

## Implications pour le Développement

1. **La privacy est un argument produit ET une contrainte technique non négociable** — elle conditionne l'adoption par le dev (persona 1) et la confiance du visiteur (persona 4). Aucune fonctionnalité ne doit la dégrader.
2. **Le dashboard doit rester lisible même avec peu de données** (empty states explicites, "—" pour les valeurs manquantes) et rester protégé par les middlewares de l'app hôte (persona 2).
3. **Le DX prime** : toute nouvelle feature doit être exposable via la facade, la directive Blade, le JS (`window.analytics.*`) et les data-attributs (persona 1).
4. **Les métriques sont des contrats** : tout changement de calcul (bounce, durée, bornage de période, exclusion du visiteur server) exige un test de régression (persona 3).
5. **Le tracker JS reste vanilla ES5, sans dépendance, ~2 Ko** — toute dépendance ou grossissement du script est un recul pour la performance (persona 1) et pour la privacy (persona 4).

*Document généré en août 2026 — à maintenir en cohérence avec README.md et config/analytics.php*
