# VOD Finder

**VOD Finder** permet de rechercher des films et des séries, de consulter leurs fiches et de trouver leurs disponibilités sur les plateformes de streaming, selon le pays et le type d’accès.

Le projet utilise **Laravel 10**, **TMDb** et, pour enrichir les liens et certaines informations de disponibilité, **Streaming Availability via RapidAPI**. L’interface est en français.

## Fonctionnalités

- Recherche par titre de film, de série ou par personne/acteur.
- Autocomplétion des titres et des personnes, avec déduplication.
- Filtres par plateforme, pays et type d’accès : abonnement (`flatrate`), location (`rent`), achat (`buy`) ou tous (`all`).
- Fiches détaillées en fenêtre modale : synopsis, casting, providers et informations de saisons/épisodes lorsque les API les fournissent.
- Watchlist, favoris et listes personnelles.
- Inscription enrichie, connexion et gestion du compte.
- Déclaration des abonnements avec accès direct ou via une autre plateforme, choisi individuellement par l’utilisateur.
- Préférences de notification générales et par abonnement, enregistrées dans le compte.

### Plateformes prises en charge

| Plateforme | Identifiant interne |
| --- | --- |
| Netflix | `netflix` |
| Prime Video | `prime` |
| Disney+ | `disneyplus` |
| Canal+ | `canalplus` |
| Apple TV+ | `appletv` |
| Paramount+ | `paramountplus` |
| HBO Max / Max | `hbomax` |

Les disponibilités dépendent du pays et des données renvoyées par les fournisseurs. Un abonnement déclaré dans le compte ne garantit pas qu’un titre soit disponible.

### Filtres et cache navigateur

Un état valide enregistré dans `localStorage` est prioritaire sur les abonnements du compte. Sans état valide, les abonnements actifs d’un utilisateur connecté deviennent ses filtres par défaut ; pour un invité, toutes les plateformes sont cochées. Un choix enregistré sans plateforme cochée est conservé.

Les résultats sont mis en cache dans `sessionStorage`, avec une durée maximale de **20 jours** et une purge limitant le cache à **40 entrées**. Ils restent soumis à la durée de vie du stockage de session du navigateur. Les clés tiennent compte des critères de recherche.

## Stack et prérequis

- PHP **8.2 ou supérieur**, compatible avec les dépendances de `composer.lock`.
- Composer 2.
- Node.js LTS récent, par exemple 22 ou 24, et npm.
- MySQL pour le développement local WAMP, ou SQLite pour le développement isolé et les tests.
- Extensions PHP usuelles de Laravel et PHPUnit : notamment `curl`, `mbstring`, `dom`, `xml`, `xmlwriter`, `fileinfo`, `openssl`, `PDO` et le driver de base utilisé. `pdo_sqlite` est nécessaire pour les tests ; `zip` facilite l’installation des dépendances.

L’environnement local de référence est **Windows / WAMP, PHP 8.2.x**, avec le VirtualHost **http://app-vod** pointant vers le dossier `public/`.

## Installation

### 1. Récupérer le projet et les dépendances

```bash
git clone https://github.com/nicaille/vod-finder.git
cd vod-finder
composer install
npm ci
```

Utiliser les fichiers de verrouillage existants. `composer update` et `npm update` ne sont pas nécessaires pour installer le projet.

### 2. Préparer la configuration locale

Sur une première installation, créer **manuellement** un fichier `.env` à partir de `.env.example`, puis renseigner les paramètres correspondant à votre machine. Si `.env` existe déjà, le conserver.

Paramètres utiles :

| Variable | Usage |
| --- | --- |
| `APP_NAME` | Nom de l’application, par exemple `"VOD Finder"` |
| `APP_ENV` | `local` pour le développement local |
| `APP_URL` | `http://app-vod` avec le VirtualHost WAMP |
| `APP_DEBUG` | `true` en développement ; `false` en production |
| `APP_KEY` | Clé Laravel propre à l’installation |
| `DB_CONNECTION` | `mysql` ou `sqlite` |
| `DB_HOST`, `DB_PORT` | Adresse et port MySQL |
| `DB_DATABASE` | Nom de base MySQL, ou chemin absolu du fichier SQLite |
| `DB_USERNAME`, `DB_PASSWORD` | Identifiants de connexion MySQL |
| `MAIL_MAILER` | `log` pour le développement sans serveur de messagerie |

Sur une **nouvelle installation uniquement**, si `APP_KEY` est vide :

```bash
php artisan key:generate
```

Ne pas régénérer la clé d’une installation existante. Ne jamais publier `.env`, les clés API, les identifiants de base ou les journaux contenant des informations sensibles. Les agents doivent respecter les consignes de [AGENTS.md](AGENTS.md), notamment ne pas modifier `.env`.

### 3. Configurer les API

Les variables suivantes sont lues dans [config/services.php](config/services.php), même si elles ne figurent pas toutes dans `.env.example` :

| Variable | Rôle / valeur par défaut |
| --- | --- |
| `TMDB_API_KEY` | Clé API TMDb, nécessaire aux recherches et aux fiches |
| `TMDB_COUNTRY` | Pays par défaut : `FR` |
| `TMDB_LANGUAGE` | Langue par défaut : `fr-FR` |
| `STREAMING_AVAILABILITY_KEY` | Clé RapidAPI pour l’enrichissement Streaming Availability |
| `STREAMING_AVAILABILITY_HOST` | `streaming-availability.p.rapidapi.com` |
| `STREAMING_AVAILABILITY_BASE_URL` | `https://streaming-availability.p.rapidapi.com` |
| `STREAMING_AVAILABILITY_COUNTRY_DEFAULT` | `fr` |
| `STREAMING_AVAILABILITY_LANGUAGE` | `fr` |

Obtenir une clé TMDb depuis les [paramètres API TMDb](https://www.themoviedb.org/settings/api). L’accès Streaming Availability nécessite une clé RapidAPI et l’abonnement adapté à cette API. Sans clé TMDb, les recherches ne renvoient pas de contenus ; sans clé Streaming Availability, son enrichissement est désactivé.

La vérification TLS reste activée. Les services TMDb et Streaming Availability respectent `curl.cainfo` lorsqu’il est configuré, puis recherchent les certificats système avec `composer/ca-bundle`. En l’absence de certificats système utilisables, ils utilisent le bundle de certificats fourni par cette dépendance. Cela permet notamment de fonctionner sous WAMP sans désactiver TLS ni modifier `.env`.

Après une mise à jour incluant cette dépendance, exécuter `composer install` puis `php artisan config:clear`. Si l’erreur **cURL 60** persiste (par exemple avec un proxy d’entreprise), vérifier les paramètres `curl.cainfo` et `openssl.cafile` du PHP utilisé par Apache et par la ligne de commande. Ne pas désactiver la vérification des certificats.

Les journaux `stack`, `single` et `daily` utilisent **`Europe/Paris`**, avec passage automatique à l’heure d’été/hiver. L’application et le stockage des dates restent en UTC. Les anciennes lignes du journal ne sont pas réécrites. Les nouveaux messages d’échec de recherche et d’autocomplétion ne journalisent pas les URL contenant les clés API.

### 4. Préparer la base

**Point connu :** l’ordre de certaines migrations historiques crée des clés étrangères avant les tables référencées, notamment `platforms` et `lists`. Une installation **MySQL vierge** peut donc échouer. Ce point reste à corriger en préservant la compatibilité avec les bases existantes ; l’installation MySQL vierge n’est pas encore validée.

Pour une nouvelle instance de développement isolée, SQLite est une alternative validée : créer un fichier de base vide, sélectionner `DB_CONNECTION=sqlite` et indiquer son chemin absolu dans la configuration locale. Ne pas remplacer ni vider une base existante.

Après configuration d’une base compatible, appliquer les migrations et charger les plateformes :

```bash
php artisan migrate
php artisan db:seed --class=PlatformSeeder
```

Le seeder crée ou met à jour les plateformes par leur slug ; il ne supprime pas les utilisateurs ni leurs abonnements. Sur une base existante, examiner les migrations en attente et sauvegarder les données avant une évolution de schéma. Ne pas utiliser `migrate:fresh`, `db:wipe` ou une commande de réinitialisation sur une base de développement existante.

Si `/account` ou `/register` échoue avec « Champ 'position' inconnu », appliquer les migrations après avoir récupéré le correctif. La migration `2026_10_05_000001_add_position_to_existing_platforms_table` ajoute cette colonne aux anciennes tables `platforms` avec une valeur par défaut de zéro ; elle conserve les plateformes, leurs abonnements et les positions déjà présentes. À position égale, les formulaires trient les plateformes par nom. Son annulation conserve la colonne pour protéger les bases qui la possédaient déjà.

## Démarrage

### Windows / WAMP

Démarrer les services Apache et MySQL de WAMP. Configurer le VirtualHost `app-vod` avec son `DocumentRoot` dans **`vod-finder/public`**, puis ouvrir **http://app-vod**.

Pour les ressources front avec rechargement automatique, lancer depuis la racine du dépôt :

```bash
npm run dev
```

Vérifier que le PHP utilisé en ligne de commande possède les extensions nécessaires, notamment celles utilisées par Composer et les tests.

### Serveur Laravel de développement

En dehors de WAMP, lancer dans deux terminaux :

```bash
php artisan serve
```

```bash
npm run dev
```

L’application est alors accessible à l’adresse indiquée par Artisan, habituellement `http://127.0.0.1:8000`. Adapter `APP_URL` à cette instance.

Pour compiler les ressources sans serveur Vite :

```bash
npm run build
```

Les ressources de `public/build` sont actuellement suivies dans Git : une compilation standard peut donc les modifier. Pour vérifier uniquement la compilation, utiliser un dossier de sortie dédié :

```bash
npm run build -- --outDir ../vod-finder-build-check
```

Certaines vues chargent Tailwind depuis `cdn.tailwindcss.com` : elles nécessitent cet accès réseau pour leur présentation actuelle. Les affiches sont chargées depuis `image.tmdb.org`.

## Tests et vérifications

```bash
php artisan test
php artisan route:list
```

La suite impose **SQLite en mémoire** via `phpunit.xml` et refuse une base non isolée. Elle prépare les tables avec les migrations ordinaires, sans réinitialiser la base locale. Les tests TMDb utilisent des réponses simulées et ne nécessitent pas de clés API réelles.

La dernière validation cloud a exécuté **70 tests, 403 assertions**, avec PHP 8.4 et SQLite. Des vérifications Chromium ont également couvert les formulaires, les filtres enregistrés et la purge du cache navigateur. Des appels réels TMDb et Streaming Availability ont été validés avec les identifiants de l’environnement ; ces vérifications ne remplacent pas la validation locale sous Windows/WAMP, PHP 8.2 et MySQL.

Après une modification des vues ou de la configuration, si des éléments restent en cache :

```bash
php artisan view:clear
php artisan config:clear
```

Parcours à contrôler manuellement : recherche film/série/personne, autocomplétion, filtres, fiche détaillée, inscription, connexion/déconnexion, modification du compte, abonnements et accès « via », watchlist, favoris et listes.

## Suivi des séries et alertes d’épisodes

Depuis la fiche d’une série, cliquer sur **Suivre la série**. Le nouvel onglet **Séries suivies** regroupe les séries suivies, les dates annoncées des prochains épisodes et les alertes reçues. Le suivi est indépendant de la playlist et des favoris. Chaque série possède une option d’alerte ; la préférence **Notifications générales** de **Mon compte** doit aussi être activée. Arrêter le suivi conserve les anciennes alertes mais empêche la création de nouvelles alertes pour cette série.

Cette première version utilise **TMDb** et crée des notifications **dans l’application**, avec un compteur d’alertes non lues et une action pour les marquer comme lues. Elle n’envoie pas d’e-mails ni de notifications push. Les dates correspondent à une diffusion annoncée, sans heure de sortie connue ni confirmation de disponibilité en France sur une plateforme.

Après récupération de cette version, depuis la racine du projet :

```bash
php artisan migrate
php artisan view:clear
php artisan series:sync
```

`series:sync` actualise les séries qui ont au moins un utilisateur abonné à leur suivi, puis crée les alertes des épisodes annoncés au plus tard aujourd’hui, selon le jour à **Paris**. Les horodatages techniques restent en UTC. Les saisons spéciales sont exclues du calendrier. Une date inconnue ne déclenche pas d’alerte ; un report de date met à jour le calendrier. Les dates retirées sont effacées du calendrier, sans supprimer l’historique des épisodes. Une alerte dont la date est retirée ou repoussée est masquée jusqu’à une nouvelle date échue.

Une contrainte unique empêche les doublons pour un même utilisateur et épisode, même après une nouvelle synchronisation ou un arrêt puis une reprise du suivi. Aucun épisode antérieur au jour du début du suivi ne génère d’alerte. Après une interruption de la tâche, les épisodes des sept derniers jours peuvent être rattrapés, à condition que le suivi et les deux préférences soient actifs lors du traitement. Une réactivation des alertes peut donc inclure ce rattrapage. Si une partie des données TMDb échoue, le calendrier existant est conservé et aucune alerte n’est créée pour la série à ce passage ; les autres séries sont traitées. La commande retourne un code d’échec si une série n’a pas pu être synchronisée.

Le calendrier TMDb utilise un cache séparé de **30 minutes**. La tâche Laravel est programmée **chaque heure**, avec protection contre les exécutions simultanées du planificateur. Une série est synchronisée une fois pour tous ses utilisateurs. La page calendrier ne lance pas d’appels API à chaque consultation.

### Activer le planificateur sous WAMP

La publication du code ne configure pas le Planificateur de tâches Windows. Créer une tâche qui lance Laravel **toutes les minutes** ; Laravel décide ensuite quand exécuter la synchronisation horaire :

| Champ du Planificateur de tâches | Valeur |
| --- | --- |
| Programme | Chemin du `php.exe` de WAMP, par exemple `C:\wamp64\bin\php\php8.2.29\php.exe` |
| Arguments | `"C:\wamp64\www\app-vod\vod-finder\artisan" schedule:run` |
| Démarrer dans | `C:\wamp64\www\app-vod\vod-finder` |
| Déclencheur | Répéter toutes les 1 minute, pendant une durée indéfinie |

Adapter le chemin de PHP à la version installée. Le PHP en ligne de commande doit disposer des extensions MySQL et cURL et d’une configuration TLS valide. L’ordinateur, MySQL et la tâche doivent fonctionner pour que les alertes soient créées ; Apache et un navigateur ouvert ne sont pas nécessaires au traitement. Pour un essai temporaire, `php artisan schedule:work` permet de lancer le planificateur dans un terminal qui reste ouvert. `php artisan series:sync` déclenche immédiatement un passage, sans attendre l’heure suivante.

Sur un serveur Linux, ajouter une entrée cron, en adaptant les chemins :

```cron
* * * * * cd /chemin/vod-finder && /usr/bin/php artisan schedule:run >> /chemin/vod-finder/storage/logs/scheduler.log 2>&1
```

Le suivi des séries et les alertes de diffusion ne nécessitent aucune nouvelle clé API ni modification de `.env`. Les recommandations entre utilisateurs restent une fonctionnalité distincte à développer.

## Organisation du code

| Emplacement | Contenu |
| --- | --- |
| `app/Http/Controllers/SearchController.php` | Recherche, autocomplétion et fiches |
| `app/Services/TmdbService.php` | Appels TMDb, cache serveur et providers |
| `app/Services/StreamingAvailabilityService.php` | Enrichissement des disponibilités et liens |
| `app/Models/User.php`, `app/Models/Platform.php` | Utilisateurs, plateformes et relations |
| `app/Support/Platforms.php` | Catalogue des plateformes et ordre d’affichage |
| `resources/views/search.blade.php` | Interface de recherche et logique navigateur |
| `resources/views/partials/platform-subscriptions.blade.php` | Formulaire partagé des abonnements |
| `routes/web.php` | Routes principales |
| `database/migrations`, `database/seeders` | Schéma et initialisation des plateformes |
| `tests/Feature` | Tests des parcours et des régressions |

Le pivot canonique `user_platform_subscriptions` utilise `user_id`, `platform_id`, `subscribed_via_platform_id`, `is_active` et `notify_opt_in`, avec unicité du couple utilisateur/plateforme. Les anciennes colonnes `is_subscribed`, `access_via_platform_id` et `notify` subsistent dans certaines migrations historiques ; elles ne doivent pas être réintroduites dans le code métier.

## Roadmap

| Statut | Travail |
| --- | --- |
| ✅ | Recherche, autocomplétion, filtres et fiches détaillées |
| ✅ | Authentification, inscription enrichie et gestion du compte |
| ✅ | Abonnements individuels, accès « via » et filtres par défaut |
| ✅ | Watchlist, favoris et listes personnelles |
| ✅ | Suivi des séries, calendrier TMDb et alertes de diffusion dans l’application |
| 🟡 | Validation complète sous Windows/WAMP, PHP 8.2 et MySQL |
| ⚠️ | Fiabiliser l’ordre des migrations pour une installation MySQL vierge |
| ⏳ | Concevoir puis implémenter les recommandations entre utilisateurs |
| ⏳ | Concevoir les notifications de recommandations |

Les préférences de notification sont stockées, mais le système de recommandations entre utilisateurs et ses notifications ne sont pas encore implémentés. Les suggestions de titres fournies par TMDb dans les fiches sont distinctes de cette future fonctionnalité. Les alertes d’épisodes concernent les dates de diffusion annoncées ; elles ne confirment pas une disponibilité sur une plateforme française.

## Publication des changements

La convention du projet est : **« release » = vérification, commit, push sur GitHub et intégration dans `main`**, en respectant les protections de branche et sans push forcé. Cette publication Git ne constitue pas un déploiement de l’application.
