# Migration vers Laravel 13

Version cible vérifiée le 10 octobre 2026 : **Laravel 13.35.0**, dernière version stable publiée le 6 octobre. Sources : [versions Packagist](https://packagist.org/packages/laravel/framework), guides officiels [11](https://laravel.com/docs/11.x/upgrade), [12](https://laravel.com/docs/12.x/upgrade) et [13](https://laravel.com/docs/13.x/upgrade).

## Prérequis et choix de compatibilité

PHP **8.3 minimum**. Le `composer.lock` est résolu avec une plateforme PHP 8.3.0 pour éviter d’imposer une version supérieure par une dépendance transitive. PHP 8.3, 8.4 et 8.5 sont contrôlés. OVH utilise PHP 8.5.7 ; WAMP doit passer de PHP 8.2.29 à **8.3 ou supérieur**, pour Apache et pour le terminal (`php -v`). Les extensions requises sont contrôlées par `composer check-platform-reqs`.

La structure existante avec les kernels HTTP/console et les providers est conservée, conformément au guide Laravel 11. Sanctum passe à 4, Tinker à 3, Breeze à 2, Carbon à 3 et PHPUnit à 12. Les annotations des fournisseurs de données deviennent des attributs : les 268 scénarios existants continuent tous à être exécutés.

La protection CSRF utilise `PreventRequestForgery`. Les écritures sans jeton restent refusées lorsqu’elles ne disposent pas d’une origine valide. L’invalidation d’une session après un changement de mot de passe redirige vers la connexion pour les pages HTML ; les appels JSON restent en 401. Les hôtes non autorisés sont refusés en 400 par Laravel 13.

Le format de session **PHP** est explicitement conservé pour relire les sessions et les messages de validation Laravel 10, sans déconnexion générale forcée. Les noms de cookies et préfixes de cache explicites restent identiques. La nouvelle option `cache.serializable_classes=false` bloque la restauration d’objets PHP depuis le cache ; les données API en tableaux et scalaires restent lisibles. Le passage des sessions à JSON pourra faire l’objet d’une migration dédiée, avec gestion des sessions actives.

Une migration ajoute uniquement `personal_access_tokens.expires_at`, nullable, pour Sanctum 4. Les jetons existants sont conservés. Aucune réinitialisation de base, génération d’APP_KEY ou suppression des clés push n’est nécessaire.

Les tests MySQL ont également permis de corriger le quoting de la colonne réservée `usage` dans les statistiques de cache, l’identifiant fixe de la configuration Brevo et deux rollbacks historiques (index déjà supprimé et index de surnom avant suppression de colonne). Les anciennes migrations déjà appliquées ne sont pas rejouées en production.

## Validation effectuée

| PHP effectivement testé | SQLite en mémoire | MySQL 8.0 isolé |
| --- | --- | --- |
| 8.3.35 | 274 tests, 1 687 assertions, zéro erreur | 274 tests, 1 687 assertions, zéro erreur |
| 8.4.24 | 274 tests, 1 687 assertions, zéro erreur | 274 tests, 1 687 assertions, zéro erreur |
| 8.5.11 | 274 tests, 1 687 assertions, zéro erreur | 274 tests, 1 687 assertions, zéro erreur |

Les tests échouent aussi sur les warnings, dépréciations et notices PHPUnit. Ils couvrent l’authentification, l’inscription et les surnoms, les droits et confirmations admin, les fiches et recherches combinées/personnes, les plateformes, les playlists/listes/favoris/déjà vu, les contacts et recommandations, les calendriers et changements d’heure de Paris, les notifications et préférences, Brevo/push, les caches, les quotas API et le cron.

Huit tests Playwright exécutent de vraies requêtes HTTP et actions JavaScript avec sessions fichier et configuration/routes/vues mises en cache, dans une copie jetable en mode production : navigation à 320, 390, 768 et 1440 px, recherche et autocomplete personne, ouverture des fiches, playlist/favori/suivi/annulation sans redirection, administration/statistiques interactives et inscription avec erreur de surnom. Aucun débordement horizontal ou erreur JavaScript inattendue dans les parcours contrôlés. Quatre tests du cache JavaScript passent ; Vite et Tailwind compilent.

Une répétition réelle part du code **Laravel 10.50.0**, commit `08e734136772f44a3d9d4f510073a796783eab06`, avec MySQL et données fictives. Après installation de Laravel 13 en `--no-dev`, elle compare intégralement 14 tables, `.env`, les clés VAPID et les secrets Brevo déchiffrés ; vérifie les mots de passe, les caches existants et la construction des caches de production. Une seconde exécution des migrations ne change rien. Les neuf scénarios du script de déploiement OVH passent également, dont la conservation des fichiers privés, les erreurs de migration, les archives interdites et le verrou de concurrence.

`composer audit` : **aucune vulnérabilité ou dépendance abandonnée**. `npm audit --omit=dev` : aucune alerte. L’audit npm complet conserve les **cinq alertes hautes connues de la chaîne braces/Tailwind 3** ; elles ne sont pas masquées. La migration CSS reste dans la roadmap. Ces résultats ne constituent pas un test d’intrusion du serveur.

Les réponses TMDb, Streaming Availability, TVmaze et Brevo sont simulées pour couvrir aussi les pannes/quotas et éviter les effets réels. Aucun e-mail, push, accès à la base OVH ou déploiement public pendant cette validation. Les tests PHP 8.5 portent sur 8.5.11, pas sur le binaire exact 8.5.7 d’OVH. WAMP/Windows et Safari/iOS devront être contrôlés sur leurs environnements réels.

## Reproduire les contrôles

```bash
composer install
composer check-platform-reqs
php vendor/bin/phpunit --fail-on-warning --fail-on-deprecation --fail-on-notice
npm ci
npm run test:cache
npm run build
npm run build:ui
npx playwright install --with-deps chromium
npm run test:browser
composer audit
npm audit --omit=dev
```

Les tests PHP par défaut imposent SQLite `:memory:`. Les tests navigateur créent puis suppriment leur propre copie sous le répertoire temporaire, sans toucher `.env` ou la base du checkout. Si Chromium est déjà installé, `VOD_CHROMIUM_PATH=/chemin/chromium npm run test:browser` utilise ce binaire.

La configuration `phpunit.mysql.xml` est réservée à une **base MySQL jetable locale** nommée `vod_finder_test`, utilisateur `vod_test`, avec activation explicite `VOD_TEST_MYSQL=1`, `DB_PORT` et `DB_PASSWORD`. Elle efface cette seule base au début de la suite. Ne pas pointer cette configuration vers une base de travail. La répétition `bash tests/Deployment/framework-upgrade.sh /chemin/checkout-laravel10` exige une deuxième base jetable vide `vod_finder_upgrade` avec le même utilisateur et le mot de passe fictif `isolated-test-only` ; `VOD_UPGRADE_DB_PORT` règle son port.

`.github/workflows/validate.yml` reproduit les six combinaisons PHP/base, le navigateur, les builds, audits runtime et la répétition de migration. Le déploiement OVH dépend de la réussite de ce workflow. La mise en ligne n’a pas été déclenchée par ce travail.

## Application après une release autorisée

Mettre d’abord WAMP sur PHP 8.3 ou supérieur. Conserver `.env`, APP_KEY, la base et `storage`. Après publication de cette version, dans PowerShell :

```powershell
cd C:\wamp64\www\app-vod\vod-finder
php -v
php artisan down
git pull --ff-only origin main
composer install --prefer-dist --no-interaction
composer check-platform-reqs
php artisan optimize:clear
php artisan migrate
php artisan up
```

Ne pas utiliser `migrate:fresh`, `db:wipe` ou `key:generate` sur une installation existante. L’automatisation OVH installe le lock en `--no-dev`, applique `migrate --force` et reconstruit les caches ; une erreur maintient le site en maintenance. Après succès du déploiement automatique :

```bash
cd ~/vod-finder/prod
VOD_PHP=/usr/local/php8.5/bin/php
"$VOD_PHP" artisan about
"$VOD_PHP" artisan migrate:status
```

Les commandes ci-dessus n’envoient pas d’e-mails de test. Le cron horaire existant continue à fonctionner ; aucune nouvelle tâche OVH n’est requise.
