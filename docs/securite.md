# Sécurité et robots

Revue du 10 octobre 2026. Les recherches et les fiches restent accessibles sans compte, conformément au choix du propriétaire. Aucun second facteur n’est demandé aux utilisateurs ni aux administrateurs.

## Indexation et collecte

`robots.txt` interdit tous les chemins à tous les robots. Les pages portent une balise `noindex` et toutes les réponses Laravel portent `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai`. Apache ajoute également cet en-tête aux fichiers statiques et à ses erreurs lorsque `mod_headers` est disponible. Les directives `noai`/`noimageai` sont complémentaires et ne constituent pas un standard universel ni un contrôle d’accès.

Les robots de recherche, de collecte et d’IA identifiés par leur User-Agent (Googlebot, bingbot, GPTBot, ClaudeBot, CCBot, Bytespider, PerplexityBot, etc.) reçoivent 403 avant tout appel API. Apache applique le même refus aux fichiers statiques ; `robots.txt` reste lisible. Un navigateur ordinaire et le contrôle Firefox du workflow de déploiement restent autorisés. Les robots peuvent mentir sur leur User-Agent : cette défense réduit la collecte sans garantir l’absence de copie. Une page déjà indexée peut rester dans les moteurs ; demander sa suppression dans leurs outils webmasters si nécessaire. `Disallow` empêche la relecture de la page et donc la découverte du `noindex` par certains moteurs.

Les recherches et les popups partagent une limite de 20 requêtes/minute pour un invité et 40 pour un membre, avec un plafond de 60/minute par IP. L’autocomplétion est limitée à 60/minute par visiteur et 120/minute par IP. Les autres limites existantes (fiche, accueil, invitations, recommandations, push) sont conservées. Les opérations du compte ont un plafond commun de 120/minute par utilisateur. Les requêtes bloquées reçoivent 429 et `Retry-After`. Les paramètres de recherche ont une longueur et un nombre d’identifiants bornés : cinq personnes au maximum par recherche.

Les plafonds utilisent le cache serveur. Garder un stockage partagé et persistant (le cache fichier d’OVH actuel convient à une seule instance), pas le driver `array` en production. Vider ce cache réinitialise également les compteurs de limitation. Des IP multiples ou des comptes multiples peuvent contourner des limites individuelles. L’accès privé au catalogue et une protection en amont contre les attaques distribuées figurent dans la roadmap.

## Administration

Toutes les routes `/admin` vérifient l’authentification, les droits `is_admin` relus en base et une confirmation du mot de passe datant de moins de 30 minutes. Une connexion avec mot de passe fournit cette confirmation ; une session existante ou restaurée avec « Se souvenir de moi » doit confirmer le mot de passe. La confirmation est liée au compte et à l’empreinte de son mot de passe. Une modification du mot de passe ou une révocation des droits invalide l’accès. L’expiration ne supprime pas les droits mais impose une nouvelle confirmation ; elle n’est pas prolongée par la consultation des pages.

Une action non confirmée n’est pas exécutée : redirection vers la confirmation pour un formulaire, 423 pour une requête JSON. Refaire l’action après confirmation si un formulaire a expiré. Les tentatives de confirmation sont limitées à cinq/minute par utilisateur. L’admin a un plafond de 120 requêtes/minute et ses écritures un plafond supplémentaire de 20/minute, distinct des lectures.

Les pages admin, leurs redirections et leurs erreurs sont `private, no-store`. Elles refusent les iframes et utilisent une Content Security Policy limitant les scripts aux assets du site, sans script inline ni eval en production. Les images TMDb sont autorisées ; aucune iframe n’est autorisée. Le mode de développement permet uniquement les connexions au Vite local sur le port 5173. Les autres pages possèdent les protections `nosniff`, anti-iframe, referrer même origine et les directives CSP de base ; leur ancien JavaScript inline ne bénéficie pas encore de la politique de scripts stricte de l’admin.

Les consultations et actions admin, y compris les refus et les changements de rôles, sont consignées dans les journaux `storage/logs/security-AAAA-MM-JJ.log`, avec rétention de 30 jours et permissions 0600. Le journal enregistre l’identifiant de l’administrateur, la route, la méthode, le statut et éventuellement l’identifiant de l’utilisateur consulté. Les échecs de connexion/verrous enregistrent l’IP. Aucun mot de passe, clé API, contenu de formulaire ou chaîne de recherche n’est ajouté à ce journal. Ces fichiers sont consultables dans **Administration → Journaux** ; ils ne remplacent pas un journal externe protégé contre une compromission du serveur.

Un nouveau compte administrateur doit avoir confirmé son adresse avant l’attribution de droits depuis l’interface. Le dernier administrateur ne peut ni perdre ses droits via l’admin ni supprimer son compte. Les changements d’e-mail, y compris sur l’ancien profil, exigent le mot de passe actuel. Le rôle admin n’est jamais modifiable par les formulaires d’inscription/compte. Les protections CSRF et d’appartenance des contenus sont conservées. Les sessions web sont liées au hash du mot de passe via `AuthenticateSession`, pour invalider les autres sessions après un changement de mot de passe.

Option supplémentaire dans `.env` si les administrateurs disposent d’IP stables :

```dotenv
ADMIN_ALLOWED_IPS=192.0.2.10,2001:db8::/64
```

Ce sont des exemples à remplacer ; **ne pas les activer tels quels**. Une valeur vide, par défaut, permet l’administration depuis un mobile ou une IP changeante. Une IP non autorisée reçoit 403, même avec le bon compte et mot de passe. Après modification : `php artisan config:cache`. En cas de verrouillage, retirer cette variable par SSH et refaire `config:cache`. Les en-têtes `X-Forwarded-For` ne sont pas acceptés comme preuve d’IP ; ne pas faire confiance à tous les proxies. Si un proxy est ajouté, configurer uniquement les adresses réellement vérifiées.

## Configuration OVH

Conserver le dossier multisite sur `vod-finder/prod/public`, jamais à la racine Laravel. La configuration `.htaccess` refuse les fichiers cachés (sauf `.well-known`), les dossiers internes et les exports `.log`, `.sql`, `.sqlite`, `.env`, `.bak`, `.old` accidentellement déposés dans le dossier public. Aucun accès public à `storage` n’est nécessaire aujourd’hui ; cette règle devra être revue si des fichiers utilisateurs publiables sont ajoutés.

Vérifier dans `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app-vod.venoix.fr
SESSION_SECURE_COOKIE=true
```

Les cookies de session sont HTTPS par défaut en production, HttpOnly et SameSite=Lax. Une valeur explicite `SESSION_SECURE_COOKIE=false` prend le dessus : la supprimer ou la remplacer en production. HSTS est envoyé sur les réponses HTTPS en production, sans imposer cette politique aux autres sous-domaines. Le nom d’hôte doit correspondre exactement à `APP_URL` : un Host falsifié est refusé. La configuration OVH doit aussi rediriger HTTP vers HTTPS pour éviter de servir une page en clair avant l’application de HSTS.

Le pare-feu OVH constitue une protection complémentaire, à tester avec les navigateurs et le contrôle GitHub ; il avait auparavant retourné 403 à certains User-Agents curl. Les limites Laravel agissent après l’arrivée sur PHP : elles ne protègent pas à elles seules contre un déni de service réseau. Garder les mises à jour PHP, des mots de passe administrateurs longs et uniques, un nombre réduit d’administrateurs, et les sauvegardes existantes.

## Dépendances et limites de cette revue

Le contrôle PHP a été effectué sur `composer.lock` avec la base publique FriendsOfPHP/security-advisories (Git HEAD `fa2d4409f13d0dea0feb647ab2f3ac31716c6c01`), en comparant les contraintes de versions avec Composer Semver. Le service direct d’audit Packagist était inaccessible depuis l’environnement cloud ; il ne faut donc pas présenter cette vérification comme un `composer audit` réussi. Guzzle, PSR-7, Symfony et PHPUnit ont été actualisés dans les contraintes compatibles existantes. La comparaison ne remonte ensuite que **CVE-2026-48019 sur Laravel 10**, dont les entrées e-mail de l’application utilisent désormais `email:rfc,filter` pour refuser les adresses injectant des caractères de contrôle. L’alerte du framework reste présente : migrer Laravel vers une version maintenue est prioritaire, et nécessite sa propre validation.

Le contrôle npm après mise à jour ne remonte aucune alerte pour les dépendances classées production. Axios, également intégré au bundle navigateur malgré son classement dev, et les autres dépendances du bundle ont été actualisés. Vite passe de 4 à 6 et son plugin Laravel de 0 à 1. La compilation utilise toujours Tailwind 3 : il reste cinq entrées d’audit de sévérité haute provenant de **braces 3.0.3** et de sa chaîne d’outils, sans version corrigée de braces disponible lors de la revue. Ce risque concerne le traitement de motifs profondément imbriqués pendant la compilation, qui traite ici des sources du dépôt, et non un endpoint déployé. Ne pas exposer Vite/Tailwind en production ni leur fournir des sources non fiables. La migration de la chaîne CSS figure également dans la roadmap. Ne pas masquer ces alertes dans les outils d’audit.

Cette revue couvre le code, les dépendances et des tests sur une base isolée ; elle ne constitue pas un test d’intrusion du serveur OVH. Aucun secret ni compte de production n’a été utilisé pour les essais.
