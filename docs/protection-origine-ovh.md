# Restreindre l’accès direct à l’origine OVH

## Situation et principe

Les essais Windows avec le même User-Agent Firefox donnent HTTP 200 via Cloudflare, mais aussi HTTP 200 directement sur OVH, sur `/about` et `/favicon.ico`. L’origine est donc accessible en contournant Cloudflare. Les essais IPv6 restent non concluants : une erreur de connexion ou de résolution n’est pas une preuve de protection.

La protection préparée couvre les pages, API et fichiers statiques avec Apache. Elle nécessite une configuration manuelle Cloudflare puis une activation explicite sur OVH. **Une release seule ne l’active pas.** Le mode observation permet de vérifier le trajet réel avant tout refus.

Cloudflare remplace l’en-tête de requête `X-VOD-Origin-Key` par un secret aléatoire. Apache vérifie ce secret avant les règles de `public/.htaccess`. Une fausse adresse `CF-Connecting-IP`, un `CF-Ray` ou un User-Agent navigateur ne suffisent plus pour accéder directement à l’origine. Cette mesure dépend de la confidentialité du secret ; elle ne constitue pas un filtrage réseau des adresses Cloudflare.

Le secret reste dans `storage/app/private/origin-guard.json` (permissions 0600). Le fichier Apache `~/vod-finder/prod/.htaccess`, hors de `public` et de Git, ne contient qu’une empreinte de la clé. Ces deux fichiers sont préservés par le déploiement automatique. Ne pas modifier ce `.htaccess` manuellement ; la commande refuse d’écraser une configuration existante ou modifiée.

## 1. Après publication du code : créer la clé sur OVH

En SSH :

```bash
cd ~/vod-finder/prod
VOD_PHP=/usr/local/php8.5/bin/php
"$VOD_PHP" artisan origin:guard init
"$VOD_PHP" artisan origin:guard token
```

Seule la commande `token` affiche le secret. Le copier directement dans Cloudflare et dans un gestionnaire de mots de passe ; ne pas le transmettre dans le chat, une capture, Git ou un journal. Relancer `init` conserve la même clé. Aucun blocage n’est encore installé.

## 2. Configurer Cloudflare Free

Dans la zone `venoix.fr`, ouvrir **Rules → Transform Rules → Modify Request Header** (ou **Rules → Overview → Create rule → Request Header Transform Rule**, selon l’interface).

- Nom : `VOD — authentifier l’origine`.
- Expression : `http.host eq "app-vod.venoix.fr"`.
- Opération : **Set static**, sur l’en-tête de **requête** `X-VOD-Origin-Key`.
- Valeur : la clé créée ci-dessus. Remplacer toute valeur fournie par le visiteur, ne pas utiliser Append.
- Déployer la règle. Vérifier qu’aucune règle ultérieure ne supprime ou remplace cet en-tête.

Les Request Header Transform Rules sont disponibles sur l’offre gratuite (10 règles actives dans la documentation consultée) : <https://developers.cloudflare.com/rules/transform/>.

Conserver les DNS du site en mode proxy et SSL/TLS **Full (strict)**. Ne pas publier les adresses d’origine dans le dépôt. Cette règle n’exclut pas le Worker des règles WAF : son éventuelle exception existante doit être conservée.

## 3. Adapter le Worker de notifications

Le Worker doit porter explicitement la clé, car il ne faut pas présumer que les transformations s’appliquent à toutes ses sous-requêtes.

Dans `vod-notifications` :

1. Mettre à jour le code avec `cloudflare/notifications-worker.mjs` de cette version et le déployer.
2. Ajouter un **secret** nommé `VOD_ORIGIN_TOKEN` contenant la même clé d’origine.
3. Conserver `CLOUDFLARE_CRON_TOKEN` et le déclencheur `* * * * *` existants.

Les deux secrets ont des rôles différents : `VOD_ORIGIN_TOKEN` authentifie le passage à l’origine ; `CLOUDFLARE_CRON_TOKEN` autorise la livraison de notifications. Une release GitHub ne met pas à jour automatiquement ce Worker.

## 4. Observer avant de bloquer

En SSH :

```bash
"$VOD_PHP" artisan origin:guard observe
"$VOD_PHP" artisan origin:guard status
```

Ce mode ajoute des en-têtes de diagnostic sans bloquer l’accès direct. Si le site rencontre une erreur Apache, appliquer immédiatement le retour arrière ci-dessous.

Dans PowerShell, utiliser une URL neuve pour éviter de lire un ancien objet du cache :

```powershell
$vodAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0"
$vodProbe = [guid]::NewGuid().ToString("N")
$vodPattern = "^(HTTP/|server:|cf-ray:|cf-cache-status:|x-vod-origin-)"
curl.exe --noproxy "*" -A $vodAgent -sS --max-time 20 -D - -o NUL "https://app-vod.venoix.fr/about?origin_probe=$vodProbe" | Select-String -Pattern $vodPattern
curl.exe --noproxy "*" -A $vodAgent -sS --max-time 20 -D - -o NUL "https://app-vod.venoix.fr/favicon.ico?origin_probe=$vodProbe" | Select-String -Pattern $vodPattern
```

Attendu sur les deux : HTTP 200, `Server: cloudflare`, `X-VOD-Origin-Guard: observe`, `X-VOD-Origin-Check: match`. Une réponse `HIT` du cache ne valide pas un nouvel appel à l’origine ; purger ces URLs si nécessaire. Si les en-têtes sont absents ou indiquent `missing`, **ne pas activer enforce** : vérifier la règle Cloudflare, le chargement du `.htaccess` parent et `mod_headers` sur OVH.

Dans les logs du Worker, attendre un passage réussi avec `origin: "match"` et `originMode: "observe"`, puis vérifier que `cron:cloudflare` continue à s’actualiser dans l’admin.

Refaire aussi les essais directs avec `--resolve` et les adresses A/AAAA relevées dans les DNS. En observation : HTTP 200 et `X-VOD-Origin-Check: missing`. Cela confirme que le contrôle Apache fonctionne indépendamment de Cloudflare.

## 5. Certificat automatique OVH / Let’s Encrypt

Le site utilise le renouvellement automatique OVH. L’option `--allow-acme-files` ménage une exception limitée aux fichiers **déjà présents**, en GET/HEAD, sous `/.well-known/acme-challenge/`, avec un nom de challenge valide de 20 à 256 caractères. Les autres chemins, fichiers inexistants et méthodes restent protégés. Cela n’ouvre pas tout `.well-known`.

Le frontal OVH peut gérer la validation en dehors du dossier de l’application. Cette exception ne garantit donc pas, à elle seule, le renouvellement futur : vérifier la méthode de validation et le statut du certificat dans OVH, conserver sa surveillance et demander confirmation à OVH si nécessaire. Ne pas ajouter une exemption générale pour résoudre un problème de certificat.

## 6. Activer après validation de l’observation

Préparer une session SSH ouverte, puis :

```bash
"$VOD_PHP" artisan origin:guard enforce --allow-acme-files
"$VOD_PHP" artisan origin:guard status
```

Vérifier immédiatement :

- Via Cloudflare : pages et fichiers HTTP 200, en-têtes `enforce` / `match`, connexion, recherche, fiche, administration, PWA et connexions OAuth configurées.
- Worker : un nouveau passage réussi avec `origin: "match"`, `originMode: "enforce"` et un horodatage récent dans l’admin.
- Accès direct avec le même User-Agent Firefox : **HTTP 403** sur `/about` et `/favicon.ico`, sans `CF-Ray`. Ajouter de faux `CF-Ray` / `CF-Connecting-IP` ne doit pas changer le résultat.
- IPv6 : refaire le test direct depuis un réseau capable de joindre cette origine en IPv6. Un HTTP 000 ne suffit pas pour le valider.
- Après une release ultérieure : mode et fichiers privés conservés.

Ne pas transmettre la vraie clé dans les tests d’accès direct : l’objectif est de vérifier le refus d’un visiteur qui ne la connaît pas.

## Retour arrière par SSH ou FTP

```bash
cd ~/vod-finder/prod
/usr/local/php8.5/bin/php artisan origin:guard off
```

Cette commande retire uniquement le fichier Apache généré et conserve la clé. Si Laravel ne démarre plus ou si la commande refuse un fichier modifié, renommer **le `.htaccess` à la racine de `prod`**, pas celui de `public` :

```bash
mv .htaccess ".htaccess.origin-disabled-$(date +%Y%m%d-%H%M%S)"
```

Le même renommage est possible en FTP. Quand Laravel fonctionne à nouveau, exécuter `origin:guard off` pour réconcilier l’état, puis examiner la cause avant de réinstaller le mode observation. Ne pas supprimer le stockage privé.

## Cron OVH : incident indépendant

Le Worker assure la livraison fréquente, mais le cron OVH horaire reste nécessaire aux séries et disponibilités. Le compte rendu du 21 octobre à 23 h indique que `/usr/local/php8.5/bin/php` est introuvable sur le **lanceur de tâches**, avant toute exécution de `cron-hourly.php` (exitcode 255).

Dans OVH → Tâches planifiées, sélectionner PHP 8.4, ou 8.3 si disponible, conserver la fréquence horaire et le chemin `vod-finder/prod/cron-hourly.php`. Le site web peut rester en PHP 8.5. Si aucun moteur compatible n’est proposé, demander à OVH de corriger le lanceur. Attendre un compte rendu exitcode 0 et de nouveaux horodatages `cron:hourly` / `series:sync`. La protection Apache ne s’applique pas aux commandes CLI.

## Vérifications automatisées

Les tests couvrent la création et conservation de clé, les permissions privées, le refus d’écraser une configuration existante, le retour arrière, le masquage dans les logs et l’en-tête du Worker. `tests/Deployment/origin-guard-apache.py` lance un véritable Apache local et vérifie les pages, fichiers statiques, faux en-têtes Cloudflare, exception ACME limitée et retour arrière. Ces tests ne remplacent pas la phase d’observation sur le frontal OVH réel.

Le challenge Cloudflare rencontré par le contrôle GitHub de déploiement reste un réglage WAF distinct ; cette protection d’origine ne le corrige pas.
