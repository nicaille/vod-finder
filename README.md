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

HBO, HBO Go, HBO Now, HBO Max et Max sont regroupés sous `hbomax`. Les formules Paramount+ (Premium, Essential et avec publicité) utilisent `paramountplus`. Les offres via Prime Video, Apple TV, Roku ou U-Next conservent leur canal et son lien : une option HBO/Paramount+ payante ne compte pas comme un abonnement Prime Video de base. La disponibilité reste celle annoncée par TMDb pour le pays choisi ; toutes les variantes ne sont pas proposées en France. La migration `2026_10_06_010000_ensure_hbo_and_paramount_platforms` ajoute les deux plateformes manquantes aux formulaires du compte, en conservant les entrées et abonnements existants.

Les disponibilités dépendent du pays et des données renvoyées par les fournisseurs. Un abonnement déclaré dans le compte ne garantit pas qu’un titre soit disponible.


### Durées du cache serveur

| Données | Durée |
|---|---|
| Recherches, dernières sorties, suggestions et filmographies | 24 heures |
| Disponibilités et liens TMDb / Streaming Availability | 24 heures |
| Fiche de film | 30 jours |
| Fiche de série terminée ou annulée | 7 jours |
| Fiche de série en cours | Jusqu’à la prochaine diffusion connue, au plus 24 heures |
| Saison comportant des épisodes à venir | Même limite de prochaine diffusion, au plus 24 heures |
| Informations des personnes et genres | 7 jours |
| Calendrier utilisé pour les alertes | 30 minutes, indépendamment des fiches |

TMDb fournit généralement une date sans heure : les fiches et saisons expirent au début du jour prévu dans le fuseau **Europe/Paris**. Le jour de diffusion, ou si le prochain épisode attendu porte une date passée, la fiche est revérifiée après **30 minutes**. Une date absente ou invalide conserve le plafond de 24 heures. Les disponibilités restent indépendantes du cache long des fiches de film. Les échecs des appels de disponibilités ne sont pas conservés comme une absence d’offre pendant 24 heures.

Les clés serveur et navigateur sont versionnées pour appliquer cette politique sans réutiliser les anciennes entrées. Le navigateur vérifie l’âge à chaque lecture, même si l’onglet reste ouvert, et consulter un résultat ne prolonge pas sa durée. Les préférences de recherche ne sont pas soumises à cette expiration.

Pour vérifier les règles du cache navigateur : `npm run test:cache` (4 tests d’expiration et de purge).

### Filtres et cache navigateur

Un état valide enregistré dans `localStorage` est prioritaire sur les abonnements du compte. Sans état valide, les abonnements actifs d’un utilisateur connecté deviennent ses filtres par défaut ; pour un invité, toutes les plateformes sont cochées. Un choix enregistré sans plateforme cochée est conservé.

Les résultats sont mis en cache dans `sessionStorage`, avec une durée maximale de **24 heures** et une purge limitant le cache à **40 entrées**. Ils restent soumis à la durée de vie du stockage de session du navigateur. Les clés tiennent compte des critères de recherche.

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

Les migrations créent désormais `platforms` avant les abonnements et `lists` avant les éléments/membres de listes. Les anciennes migrations restent enregistrées sous leurs noms historiques ; les tables existantes et leurs données sont conservées. L’installation vierge et le seeder ont été validés sur MySQL 8.0 dans une base isolée.

Si une première installation MySQL s’est interrompue avec **« Failed to open the referenced table 'platforms' »**, récupérer le correctif et relancer `php artisan migrate` (avec `--force` en production). MySQL peut avoir conservé une table `user_platform_subscriptions` partiellement créée : la migration complète les clés étrangères et les index manquants sans supprimer la table ni ses données. Aucun effacement de base n’est nécessaire.

Pour une nouvelle instance de développement isolée, SQLite est aussi possible : créer un fichier de base vide, sélectionner `DB_CONNECTION=sqlite` et indiquer son chemin absolu dans la configuration locale. Ne pas remplacer ni vider une base existante.

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

La dernière validation cloud a exécuté **207 tests, 1185 assertions**, avec PHP 8.4 et SQLite. Des vérifications Chromium ont également couvert les formulaires, les filtres enregistrés, la purge du cache navigateur et le calendrier avec horaires de Paris à 320, 390, 768 et 1440 pixels. Le scénario de sortie nocturne est testé avec des réponses simulées ; les horaires TVmaze réels n’ont pas pu être confirmés depuis cet environnement cloud. Des appels réels TMDb et Streaming Availability ont été validés avec les identifiants de l’environnement ; ces vérifications ne remplacent pas la validation locale sous Windows/WAMP, PHP 8.2 et MySQL.

Après une modification des vues ou de la configuration, si des éléments restent en cache :

```bash
php artisan view:clear
php artisan config:clear
```

Parcours à contrôler manuellement : recherche film/série/personne, autocomplétion, filtres, fiche détaillée, inscription, connexion/déconnexion, modification du compte, abonnements et accès « via », watchlist, favoris et listes.

## Pertinence de la recherche

Le sélecteur de recherche propose **Film/Série** par défaut, ainsi que Film et Série séparément. Le mode combiné interroge les deux catalogues et conserve le type de chaque résultat pour les disponibilités, les fiches et la playlist. Les liens d’acteurs, réalisateurs et producteurs ouvrent aussi une recherche combinée. Les anciens réglages de type passent une fois au nouveau défaut, en conservant les autres filtres ; les choix suivants sont mémorisés.

L’autocomplétion sépare **Films et séries** et **Personnes** en deux colonnes sur ordinateur, puis deux sections sur mobile. Jusqu’à dix contenus et six personnes sont conservés indépendamment. Le métier connu (interprétation, réalisation, production…) accompagne les personnes.

Le tri par défaut des recherches de titres privilégie les titres exacts, puis les titres commençant par la recherche, puis ceux contenant les mots recherchés. La popularité départage les correspondances de même niveau. Les accents et la ponctuation sont normalisés, en séparant les apostrophes : « d’une » ne correspond pas à « Dune ». Les titres originaux restent recherchables avec un poids moindre.

Pour une personne, **Pertinence** privilégie les rôles principaux et la réalisation, puis les rôles secondaires et les autres contributions professionnelles ; les apparitions documentaires, les caméos explicitement signalés et les images d’archives passent plus bas. Au sein d’un même niveau, le nombre de votes TMDb, puis la popularité, favorisent les titres connus. Un crédit de production ne remplace plus un rôle d’acteur pour le même titre. Les résultats indiquent la participation retenue. Les tris par année et par titre sont appliqués avant la pagination pour parcourir la filmographie entière dans cet ordre.

Entrée lance une recherche de titre, sauf si la saisie correspond exactement à une personne et aucun titre exact n’est suggéré. Cliquer sur une personne lance explicitement sa filmographie, incluant ses crédits d’acteur et d’équipe (réalisation, production…). Les anciens résultats mémorisés dans le navigateur sont invalidés pour appliquer le nouveau classement.

Les filmographies et les recherches combinées films/séries sont chargées par portions de **12 titres**, avant la vérification des plateformes, pour éviter de traiter des centaines d’appels API dans une seule requête. **Afficher la suite de la filmographie** conserve les résultats précédents et poursuit le parcours, même si une portion entière est exclue par les filtres. Le cache du navigateur conserve également la position suivante. Une recherche restée en attente est annulée après 30 secondes avec un message permettant de réessayer ; une nouvelle saisie annule la recherche précédente. Un test réel sur Tom Cruise (105 films distincts) a renvoyé la première portion en 5,5 secondes dans l’environnement cloud ; la durée dépend des API et de l’hébergement.

Les fiches de personnes ayant le même nom normalisé sont regroupées lorsqu’elles partagent un identifiant **IMDb ou Wikidata**, ou, à défaut, une **date de naissance complète, valide et identique**. Deux identifiants différents dans le même référentiel empêchent la fusion, même avec une date identique. Les dates absentes, les photos communes et les films communs ne suffisent pas. Chaque membre doit être compatible avec tous les membres du groupe pour éviter une fusion indirecte entre homonymes. Les répétitions d’un même identifiant TMDb sont supprimées.

Les identités sont récupérées uniquement pour les noms répétés et mises en cache sept jours. Un échec laisse les fiches séparées et conserve les suggestions. Une suggestion regroupée recherche les crédits de **tous ses identifiants TMDb**, puis dédoublonne les films et séries. La date de naissance reste un critère probabiliste et dépend de l’exactitude de TMDb.

Les suggestions de personnes affichent leur photo si disponible, deux œuvres connues et la date de naissance si renseignée. La fiche la mieux classée apparaît en premier ; les autres fiches de même nom restent accessibles dans **Voir les autres…**. Une fiche sans données d’identité suffisantes reste distincte.

## Contacts et recommandations entre utilisateurs

Depuis **Mon compte**, ouvrir **Mes contacts et mon QR code** ou **Mes recommandations**. Dans les préférences du compte, chacun choisit séparément d’apparaître dans l’annuaire et de partager son prénom et son nom. Ces deux choix sont désactivés par défaut. L’identité partagée utilise sinon le pseudo, ou « Membre » suivi du numéro du compte si aucun pseudo n’est renseigné. Les adresses e-mail restent privées ; une recherche par nom réel ne trouve que les membres qui ont autorisé ce partage.

Une demande de contact peut être envoyée à l’adresse e-mail d’un **compte déjà inscrit**, depuis l’annuaire des membres volontaires ou depuis un lien/QR de contact. La réponse à une saisie d’adresse reste identique, que le compte existe ou puisse recevoir l’invitation. Le QR est généré **localement**, sans transmettre le lien à un service tiers, et disponible dans les contacts puis dans le compte. Scanner ce code ouvre une page de confirmation pour un utilisateur connecté : aucun contact n’est ajouté automatiquement. Renouveler le lien révoque les anciens QR codes. Sur téléphone, le domaine du site doit être accessible depuis cet appareil.

Seul le destinataire peut accepter ou refuser une demande. Une relation acceptée permet des recommandations dans les deux sens. Les doublons et invitations croisées conservent une seule demande ; l’utilisateur concerné doit toujours accepter explicitement. Chacun peut retirer un contact ou bloquer la relation. Seule la personne qui a bloqué peut débloquer ; cette action n’accepte pas automatiquement la relation. Une demande refusée n’est pas recréée par des invitations répétées.

Depuis la fiche d’un film ou d’une série, utiliser la pill **Recommander**. Une recherche de personne propose aussi un lien vers sa fiche, qui permet de la recommander. Choisir un contact accepté et ajouter éventuellement un message. Le serveur récupère les métadonnées TMDb, l’image, les genres et les offres connues en France ; les informations fournies par le navigateur ne remplacent pas ces données. Les offres sont une photographie au moment de l’envoi, susceptible de changer. Pour une personne, consulter les offres de chaque œuvre de sa filmographie. Un double envoi du même titre au même contact dans la minute est ignoré.

La réception apparaît dans les notifications du site, avec l’expéditeur, une image disponible et un lien vers la recommandation. L’image et le titre ouvrent la fiche reçue ; celle-ci donne accès à la fiche du contenu ou de la personne. Les demandes, acceptations et recommandations peuvent aussi être envoyées par e-mail ou push via **`notifications:deliver`**, avec les mêmes préférences globales/canaux que les épisodes. L’e-mail nécessite une adresse vérifiée et un transport SMTP réel ; le push nécessite la configuration et HTTPS déjà décrites ci-dessous. La tâche existante toutes les cinq minutes suffit : ne pas ajouter un second planificateur. Les livraisons ne concernent que les événements des dernières 24 heures, avec suivi par canal/appareil, cinq tentatives maximum et reprise différée. Une relation bloquée, retirée ou refusée empêche les nouveaux envois externes. Les recommandations précédemment reçues restent dans la boîte du destinataire jusqu’à leur suppression.

La boîte privée permet de filtrer par titre, type, expéditeur, plateforme et état (reçues, non lues, archivées), de trier par date ou titre, puis de marquer lue/non lue, archiver, restaurer ou supprimer. Ouvrir une fiche reçue la marque lue. Chaque accès et action est réservé au destinataire. Les QR, pages privées et réponses API ne sont pas enregistrés dans le cache hors ligne de la PWA.

Après publication et récupération de cette fonctionnalité sous WAMP :

```powershell
composer install
php artisan migrate
php artisan route:clear
php artisan view:clear
```

La migration ajoute les préférences privées, les relations, les recommandations et le suivi des notifications sans réinitialiser les tables existantes. La génération de QR utilise `bacon/bacon-qr-code` et l’extension PHP XMLWriter. Les assets sont déjà compilés dans le dépôt. Pour déclencher réellement les notifications après configuration, utiliser séparément `php artisan notifications:deliver`.

## Images des fiches détaillées

Les fiches de films et séries utilisent un cadre au ratio **1,77:1** (hauteur = largeur / 1,77), avec l’image entière visible sans découpe. Le visuel paysage est préféré ; une affiche de remplacement conserve ses proportions avec des bandes autour si nécessaire. Les portraits de personnes conservent aussi leurs proportions.

Les dimensions de l’original sont récupérées dans les métadonnées TMDb, avec les détails du contenu. Le navigateur sélectionne la première définition disponible supérieure ou égale à la largeur réellement affichée multipliée par la densité de pixels de l’écran. Les formats paysage disponibles sont 300, 780 et 1280 pixels, puis l’original ; les affiches et portraits utilisent leurs formats TMDb respectifs. Une source originale trop petite limite la taille d’affichage pour éviter l’agrandissement artificiel. Si les dimensions manquent, l’original permet de les mesurer après chargement. Le comportement s’applique aux fenêtres de détail dynamiques et aux fiches complètes, avec recalcul au redimensionnement.

## Sorties récentes sur l’accueil

Avant toute recherche, l’accueil charge en arrière-plan jusqu’à **24 titres** (12 films et 12 séries) récemment sortis et actuellement inclus dans les plateformes actives du compte, en **France**. Les films sont classés selon leur date de sortie et les séries selon leur première diffusion, sur une fenêtre de **90 jours**, avec exclusion des dates futures et des offres limitées à la location ou à l’achat. Les titres sont dédoublonnés, triés du plus récent au plus ancien, et conservent l’accès aux fiches et à la playlist.

Cette sélection représente des **titres récents disponibles**, et non les derniers ajouts au catalogue : TMDb ne fournit pas ici les dates d’arrivée sur une plateforme. Elle ne recense pas les nouvelles saisons d’anciennes séries selon leur date de saison. Les identifiants de plateformes sont récupérés dans le catalogue TMDb, puis la disponibilité de chaque titre est vérifiée avec le filtre d’abonnement du projet. Les données de découverte sont mises en cache 24 heures, par type et ensemble de plateformes ; l’état privé de la playlist est ajouté à chaque réponse pour l’utilisateur courant.

Les filtres temporaires ou restaurés d’une recherche ne remplacent pas les abonnements du compte pour cette sélection. Les fiches ouvertes depuis l’accueil utilisent la région France. Une recherche restaurée masque les sorties récentes ; vider le champ les réaffiche. Sans plateforme active, un lien propose de renseigner les abonnements. Sans connexion, l’accueil invite à se connecter. Une erreur de chargement propose **Réessayer** sans bloquer la recherche habituelle.

## Suivi des séries et alertes d’épisodes

Depuis la fiche d’une série, cliquer sur **Suivre la série** : la fiche reste ouverte, la pill devient « Série suivie » et une confirmation apparaît sur place. Un échec laisse le bouton disponible pour réessayer. Sans JavaScript, le formulaire revient à la page précédente. Le nouvel onglet **Séries suivies** regroupe les séries suivies, les dates annoncées des prochains épisodes et les alertes reçues. Le suivi est indépendant de la playlist et des favoris. Chaque série possède une option d’alerte ; la préférence **Notifications générales** de **Mon compte** doit aussi être activée. Arrêter le suivi conserve les anciennes alertes mais empêche la création de nouvelles alertes pour cette série.

Le calendrier utilise **TMDb**, complété par les horodatages **TVmaze** lorsque la série partage le même identifiant IMDb. Les alertes apparaissent **dans l’application**, avec un compteur de non-lues, et peuvent également être reçues par **e-mail** ou **notification push navigateur**, selon les choix faits dans **Mon compte** et après configuration des services. Les heures connues sont stockées en UTC puis converties en **Europe/Paris**, en tenant compte des changements d’heure. Elles correspondent à une diffusion annoncée et ne garantissent pas la disponibilité sur une plateforme française. Une date TMDb seule est conservée telle quelle et indiquée comme une date source dont l’horaire et la date locale restent à confirmer ; le paramètre de langue TMDb ne convertit pas les dates en fuseau français.

Après récupération de cette version, depuis la racine du projet :

```bash
php artisan migrate
php artisan view:clear
php artisan series:sync
```

`series:sync` actualise les séries qui ont au moins un utilisateur abonné à leur suivi, puis crée les alertes des épisodes dont l’instant de diffusion est échu. Sans heure connue, la date source est utilisée selon le jour à **Paris**, sauf pour un épisode encore déclaré « prochain » dont la date est aujourd’hui ou hier : il reste à confirmer et ne déclenche pas encore d’alerte. Les horodatages techniques restent en UTC. Les saisons spéciales sont exclues du calendrier. Une date inconnue ne déclenche pas d’alerte ; un report de date met à jour le calendrier. Les dates retirées sont effacées du calendrier, sans supprimer l’historique des épisodes. Une alerte dont la date est retirée ou repoussée est masquée jusqu’à une nouvelle date échue.

Une contrainte unique empêche les doublons pour un même utilisateur et épisode, même après une nouvelle synchronisation ou un arrêt puis une reprise du suivi. Aucun épisode antérieur au jour du début du suivi ne génère d’alerte ; lorsqu’une heure est connue, aucun épisode déjà diffusé à l’instant de l’ajout au suivi ne génère d’alerte. Après une interruption de la tâche, les épisodes des sept derniers jours peuvent être rattrapés, à condition que le suivi et les deux préférences soient actifs lors du traitement. Une réactivation des alertes peut donc inclure ce rattrapage. Si une partie des données TMDb échoue, le calendrier existant est conservé et aucune alerte n’est créée pour la série à ce passage ; les autres séries sont traitées. La commande retourne un code d’échec si une série n’a pas pu être synchronisée.

Le calendrier TMDb et les épisodes TVmaze utilisent des caches séparés de **30 minutes** ; la correspondance IMDb/TVmaze est conservée **7 jours**. Aucun compte ou clé TVmaze n’est nécessaire. La liste complète des épisodes datés est importée, y compris ceux absents des saisons TMDb ; le champ TMDb « prochain épisode » complète également les saisons incomplètes. Un épisode annoncé sans date reste visible avec « date à confirmer ». Un prochain épisode TMDb daté de la veille est conservé dans la liste à confirmer pendant ce décalage possible, sans lui inventer une date française. Un échec TVmaze préserve les horaires précis déjà synchronisés et empêche la création d’alertes à ce passage ; sans horaires précis précédents, TMDb reste le repli. Les fiches des séries suivies réutilisent ce calendrier. La tâche Laravel est programmée **chaque heure**, avec protection contre les exécutions simultanées du planificateur. Une série est synchronisée une fois pour tous ses utilisateurs. La page calendrier ne lance pas d’appels API à chaque consultation.

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

## Notifications par e-mail et navigateur

Dans **Mon compte**, activer les notifications générales, puis choisir **E-mails**, **Notifications navigateur**, ou les deux. Les préférences par série s’appliquent également. Les nouveaux canaux sont désactivés par défaut pour tous les comptes existants. Les alertes restent consultables dans l’application, même sans canal externe. Les notifications propres aux abonnements de plateformes sont distinctes de ces alertes d’épisodes.

Après récupération de cette version :

```bash
composer install
php artisan migrate
php artisan view:clear
php artisan config:clear
php artisan notifications:setup-push
```

La commande `notifications:setup-push` crée une paire de clés VAPID dans **`storage/app/private/webpush.json`**, sans modifier `.env`. Le fichier est exclu de Git et ne doit jamais être placé dans `public/`. Le conserver et le sauvegarder lors des déploiements : une nouvelle paire obligerait les appareils à s’abonner à nouveau. Une seconde exécution conserve la paire existante. Seule la clé publique est fournie aux navigateurs authentifiés. La génération utilise OpenSSL. Si le PHP CLI de WAMP ne trouve pas son fichier `openssl.cnf` et échoue avec « Unable to create the key », le générateur réessaie avec la configuration explicite `resources/openssl.cnf` fournie par le projet. La configuration fournit aussi `default_bits`, et la génération définit explicitement ce réglage générique pour les versions WAMP qui le valident avant la sélection de courbe. La clé produite reste une clé EC P-256 de 256 bits. Les avertissements natifs sont gérés localement pour permettre le repli, et les contrôles TLS ne sont pas modifiés. Si les deux tentatives échouent, la commande affiche un diagnostic et ne crée aucun fichier de clés. Vérifier alors l’extension et l’installation OpenSSL du PHP CLI.

### E-mails

Les administrateurs peuvent configurer **Brevo** dans **Administration → Notifications e-mail** (`/admin/notifications/email`) : enregistrer une clé API, l’adresse d’un expéditeur validé dans Brevo et son nom, puis activer l’API pour les notifications. Le service couvre les alertes d’épisodes, les contacts, les recommandations et les demandes de confirmation d’adresse depuis Mon compte. Sans activation de Brevo, utiliser la configuration mail existante de Laravel (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) avec un compte SMTP ou un transport d’envoi opérationnel. Les autres e-mails Laravel, notamment la réinitialisation du mot de passe, conservent leur configuration mail habituelle. Ne pas publier les identifiants. Aucun identifiant SMTP n’est ajouté automatiquement et `.env` n’est pas modifié par ce développement.

L’adresse du compte doit être **confirmée** pour recevoir les alertes par e-mail. Le bouton de confirmation est proposé dans **Mon compte** ; cet envoi utilise également Brevo lorsqu’il est activé, ou le transport mail habituel sinon. Une modification de l’adresse remet sa confirmation à zéro. Les transports `log`, `array` et le `failover` historique ne sont pas considérés comme des livraisons e-mail réelles : ils ne permettent pas de tester une réception dans une boîte mail.

La clé API Brevo est conservée chiffrée dans la base et n’est ni réaffichée ni reprise dans les anciens champs après une erreur de validation. Un champ vide conserve la clé enregistrée ; une nouvelle clé la remplace. Le chiffrement dépend de la clé Laravel `APP_KEY` existante : ne pas la régénérer lors d’une mise à jour. Aucun fichier `.env` n’est modifié. Une migration ajoute les paramètres ; exécuter `php artisan migrate` après récupération du code.

Le bouton **Envoyer un e-mail de test à mon adresse** envoie réellement un message à l’adresse de l’administrateur connecté, avec la configuration enregistrée, même avant activation pour les utilisateurs. La réussite indique l’acceptation par Brevo ; vérifier la réception et les courriers indésirables. L’enregistrement seul n’envoie aucun test. Le planificateur existant conserve les préférences individuelles, l’exigence d’adresse vérifiée, le suivi des livraisons et les reprises après échec. Les tests automatisés simulent l’API et n’envoient aucun message réel.

Les e-mails d’épisodes, de contacts/recommandations, de confirmation d’adresse et de test disposent de versions **HTML et texte**. Brevo reçoit `htmlContent` et `textContent` ; le transport Laravel utilise les mêmes vues pour produire un message multipart. Chaque message comporte une signature et un lien vers le site. Les alertes expliquent pourquoi elles sont reçues et proposent un lien vers **Mon compte → Notifications** pour modifier les préférences ou désactiver les e-mails ; le changement nécessite une connexion au compte. Ces améliorations ne garantissent pas le classement en boîte principale : vérifier SPF, DKIM, DMARC et la réputation d’envoi si les messages restent en spam.

### Push navigateur et mobile

Un **contexte sécurisé** est requis : HTTPS, ou `localhost` pour certains tests de développement. **`http://app-vod` ne permet pas d’activer le push navigateur**, même si la navigation du site fonctionne. Pour un usage mobile, exposer le site via une adresse HTTPS accessible au téléphone, avec un certificat reconnu et une URL publique configurée correctement dans `APP_URL`. Le sujet VAPID utilise cette URL par défaut ; `WEBPUSH_SUBJECT` peut définir une URL de contact HTTPS ou une adresse `mailto:` si nécessaire.

Sur chaque appareil : ouvrir **Mon compte**, cliquer sur **Autoriser sur cet appareil**, accepter la demande du navigateur, cocher le canal navigateur et **enregistrer les préférences**. Le bouton **Désactiver sur cet appareil** retire uniquement cet abonnement ; décocher le canal puis enregistrer suspend les envois vers tous les appareils du compte. La déconnexion désabonne l’appareil courant lorsque le navigateur le permet, sans désabonner les autres appareils. Une permission refusée doit être rétablie dans les paramètres du navigateur avant une nouvelle tentative.

Sur **iPhone/iPad**, utiliser Safari et ajouter l’application à l’écran d’accueil ; les notifications web nécessitent iOS/iPadOS **16.4 ou plus récent** et l’application installée. Sur Android et ordinateur, le support dépend du navigateur. Un service worker reçoit les messages même si la page est fermée ; le système ou le navigateur peut néanmoins retarder leur affichage. L’état « appareil connecté » indique que l’abonnement est enregistré, sans garantir l’affichage d’une notification par le système.

### Livraison et reprise des échecs

La tâche `notifications:deliver` tourne toutes les **cinq minutes** via le même planificateur Laravel que `series:sync`. Ne pas créer une deuxième tâche Windows si `schedule:run` est déjà lancé chaque minute. Pour déclencher manuellement les livraisons après configuration :

```bash
php artisan notifications:deliver
```

Cette commande **envoie réellement** les notifications éligibles. Les tests automatisés simulent les transports et n’envoient pas de messages réels.

Chaque alerte récente (créée depuis moins de 24 heures), datée au plus tard du jour courant à Paris, est éligible si le suivi et les préférences sont toujours actifs. Chaque canal/appareil possède un enregistrement de livraison ; les passages suivants ignorent les livraisons réussies. Les échecs sont repris jusqu’à cinq tentatives, avec une attente croissante de 15, 30, 45 puis 60 minutes. Les abonnements push expirés sont supprimés. Une date repoussée ou retirée suspend la livraison. Activer un canal peut envoyer des alertes déjà créées dans les dernières 24 heures, mais pas un ancien historique complet.

Un verrou protège les exécutions simultanées de la commande. Comme pour tout transport externe, un arrêt brutal après acceptation par le fournisseur mais avant enregistrement local peut entraîner une répétition lors de la reprise. Les notifications navigateur utilisent une balise par alerte pour remplacer une notification répétée. Les journaux d’échec ne contiennent ni clé privée, ni endpoint push, ni adresse e-mail, ni message brut du transport.

## Interface responsive et installation mobile

L’interface adopte un thème sombre, des accents rouges, des affiches au format portrait et des grilles qui utilisent la largeur disponible jusqu’à 1680 pixels. Les filtres secondaires sont repliables. Sur mobile, les cinq destinations principales restent accessibles dans une barre fixe en bas, avec prise en compte des zones de sécurité de l’écran. Les fiches d’épisodes réorganisent les miniatures, les informations et les pills sur petit écran. Le site respecte la préférence de réduction des animations et propose un lien d’accès direct au contenu et des indicateurs de focus.

Le bouton **Installer** utilise l’invite native lorsqu’elle est disponible, ou explique comment ajouter le site à l’écran d’accueil. Le manifeste définit l’ouverture en application autonome, les icônes et les raccourcis vers le suivi et la playlist. Le service worker conserve uniquement les ressources publiques nécessaires à une page hors connexion. Les pages privées, résultats API et fiches AJAX ne sont pas mis en cache ; les anciens caches de l’application sont purgés lors de la mise à jour.

Les pages principales utilisent désormais des styles **locaux**, sans dépendance au CDN Tailwind pour leur affichage. Le fichier `public/vod.css` est livré dans Git pour WAMP. Après une modification de classes Tailwind :

```bash
npm ci
npm run build:ui
```

`npm run build` conserve la compilation Vite pour les composants Breeze. Il n’est pas nécessaire pour une simple récupération des fichiers CSS déjà publiés. Les vérifications Chromium couvrent les pages principales à **320, 390, 768, 1440 et 1920 pixels**, la recherche, les fiches d’épisodes, l’autorisation push et le mode hors connexion. Une validation réelle de réception SMTP, des services push et des appareils iOS/Android reste à effectuer sur l’installation HTTPS cible.

**AMP n’est pas retenu** : cette application authentifiée, avec recherches, listes, préférences et push, bénéficie davantage d’une PWA responsive que d’une seconde version AMP à maintenir.

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
| ✅ | Préférences e-mail/push navigateur, livraisons et reprise des échecs |
| ✅ | Interface responsive, navigation mobile et installation PWA |
| 🟡 | Validation complète sous Windows/WAMP, PHP 8.2 et MySQL |
| ⚠️ | Fiabiliser l’ordre des migrations pour une installation MySQL vierge |
| ⏳ | Concevoir puis implémenter les recommandations entre utilisateurs |
| ⏳ | Concevoir les notifications de recommandations |

Les préférences de notification sont stockées, mais le système de recommandations entre utilisateurs et ses notifications ne sont pas encore implémentés. Les suggestions de titres fournies par TMDb dans les fiches sont distinctes de cette future fonctionnalité. Les alertes d’épisodes concernent les dates de diffusion annoncées ; elles ne confirment pas une disponibilité sur une plateforme française.

## Publication des changements

La convention du projet est : **« release » = vérification, commit, push sur GitHub et intégration dans `main`**, en respectant les protections de branche et sans push forcé. Le workflow **Test et déploiement OVH** déploie ensuite chaque push sur `main` après ses tests, une fois les quatre secrets SSH configurés. Consulter le [guide de configuration et de reprise OVH](docs/deploiement-ovh.md). Le déploiement conserve `.env`, `storage`, les clés push et la base existante ; les migrations sont appliquées sans réinitialisation.


### À propos et administration

La page publique `/about` présente le projet, la playlist, les listes, le suivi des séries et les recommandations entre contacts. Un lien « À propos » est présent sur toutes les pages utilisant la navigation principale. Les mentions de TMDb et de Streaming Availability API — Movie of the Night sont conservées dans une section de crédits indépendante du contenu éditable.

Après avoir appliqué les migrations, attribuer explicitement les droits initiaux à un compte existant dans le terminal WAMP :

```powershell
php artisan app:admin votre-adresse@example.com
```

Le lien **Administration** devient visible pour ce compte. `/admin` permet de modifier le titre et le contenu de la page avec du Markdown simple. Le HTML et les liens dangereux sont neutralisés à l’affichage. L’onglet **Administrateurs** (`/admin/users`) permet d’accorder les mêmes droits à un autre compte existant par son adresse e-mail, ou de les retirer. Le dernier administrateur ne peut pas être retiré depuis le site. Les nouveaux utilisateurs ne reçoivent aucun droit d’administration à l’inscription.

La commande `php artisan app:admin adresse@example.com --revoke` permet aussi une gestion explicite des droits depuis le terminal. Elle ne crée pas de compte.

### Cartes de contenus

Les résultats de recherche, la playlist, les listes personnelles et les coups de cœur partagent un cadre d’affiche **2:3**, une grille responsive et des styles communs de titre, type et année. Les images utilisent `object-fit: contain` pour conserver l’affiche entière, y compris lorsqu’un visuel possède un autre ratio. Les affiches TMDb disposent de sources responsive de 185 à 780 pixels ; les listes n’utilisent plus une vignette de 185 pixels pour une carte de grande taille. Les images absentes conservent le même cadre. Les actions restent propres à chaque contexte : playlist, coup de cœur ou retrait d’une liste.


### Priorité des sources de disponibilité

Les informations détaillées, recherches de titres, images et filmographies restent fournies par **TMDb**. Les offres de visionnage proviennent en priorité de **Streaming Availability** : abonnement, achat, location et options payantes sont normalisés avant les filtres de recherche. Les résultats, les fiches, les vérifications de disponibilité de l’accueil et les recommandations entre utilisateurs partagent cette règle.

La couverture est récupérée par pays depuis `/countries/{country}` et conservée 24 heures. Sur une plateforme couverte, une réponse valide sans offre n’est pas remplacée par une ancienne offre TMDb. TMDb complète les services non couverts (notamment Canal+ en France selon le catalogue actuel) et sert de secours si Streaming Availability est désactivé, indisponible ou renvoie une réponse inutilisable. Une réponse 429 suspend temporairement les tentatives de la source prioritaire pendant une minute ; les erreurs ne sont pas conservées comme des absences d’offres pendant 24 heures.

Les liens directs et les prix de l’offre correspondante sont conservés. Une option HBO Max ou Paramount+ via Prime Video n’est pas présentée comme incluse dans l’abonnement Prime de base. Les offres d’un autre pays, expirées ou avec un lien dangereux sont exclues. Les données brutes Streaming Availability restent en cache 24 heures ; l’enrichissement est séparé des fiches TMDb conservées plus longtemps. Les anciennes recherches du navigateur sont invalidées à cette évolution.

La découverte initiale des titres récents de l’accueil reste effectuée via TMDb ; leur disponibilité est ensuite vérifiée avec cette priorité des sources. Ce n’est pas un inventaire exhaustif des nouveautés de tous les catalogues.

## Actions et recherches depuis une fiche

Les noms des acteurs, réalisateurs et producteurs ouvrent une recherche par identifiant TMDb, avec le type de contenu et le pays de la fiche. Les filtres de plateformes enregistrés restent appliqués.

Pour les comptes connectés, les actions apparaissent sur une ligne : réveil pour le suivi de série, cœur pour les favoris, liste avec un signe plus, puis flèche pour recommander. Les libellés se révèlent au survol et au focus clavier ; sur mobile, les noms restent accessibles aux technologies d’assistance. Une série suivie a un fond indigo, un favori un fond rose et un titre présent dans une liste un fond vert. Les favoris et les ajouts aux listes actualisent leur état sans recharger la fiche.

## Chargement des fiches de séries

Les saisons annoncées sans liste d’épisodes sont normalisées en listes vides avant le tri. Cela corrige notamment l’erreur serveur de la fiche The Gentlemen (TMDb 236235). Les fenêtres de détail de la recherche, de la playlist et des listes vérifient les réponses HTTP et le contenu reçu : en cas d’échec, elles affichent **Réessayer** et **Fermer** au lieu d’un overlay vide. Une vérification avec les données réelles a confirmé le rendu de The Gentlemen ; les tests Chromium couvrent aussi une erreur HTTP 500, une réponse vide, la reprise et la fermeture.

## Icônes de l’application

La favicon et les icônes installables reprennent le **V** du logo, sa police déclarée (`Inter, ui-sans-serif, system-ui, sans-serif`, graisse 900), sa couleur actuelle **#ff3346** et le fond **#101014**. Le SVG source est `public/icons/vod-mark.svg`. Les PNG existent aux tailles 16, 32, 48, 180 (iOS), 192 et 512 pixels ; une variante Android maskable garde le V dans la zone protégée. La favicon ICO contient les tailles 16/32/48. Le manifeste, les pages et les notifications push référencent ces nouvelles icônes. Le cache public du service worker est versionné pour appliquer la mise à jour. Une icône déjà installée peut nécessiter une mise à jour par le navigateur ou une réinstallation du raccourci.
