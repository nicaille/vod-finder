# Notifications chaque minute avec Cloudflare Workers

Cette première étape utilise Cloudflare pour **livrer les notifications en attente**. Le cron OVH horaire reste actif : il synchronise les séries, vérifie les disponibilités quotidiennement et assure aussi une reprise des livraisons. Ne pas le désactiver dans cette configuration.

Les recommandations tentent déjà un envoi immédiat. Le Worker accélère les reprises et la livraison des alertes déjà créées ; il ne change pas la fréquence de création des alertes d’épisodes ou de disponibilités. Il ne réveille pas une application arrêtée de force par Android et ne garantit pas l’affichage immédiat sur le téléphone.

## 1. Publier le code avant activation

Ce dispositif nécessite la route `POST /api/internal/notifications/tick` et le Worker fourni dans `cloudflare/notifications-worker.mjs`. Il ne suffit pas d’appeler le fichier `cron-hourly.php` : il est réservé au CLI.

Après une release déployée sur OVH, créer la configuration ci-dessous. Aucun nouveau paquet ni aucune migration ne sont nécessaires pour ce dispositif. Les fonctionnalités restent désactivées par défaut.

## 2. Générer un secret sur votre ordinateur

Dans PowerShell, générer 32 octets aléatoires, convertis en 64 caractères hexadécimaux, puis les copier dans le presse-papiers :

```powershell
$vodBytes = New-Object byte[] 32
$vodRandom = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$vodRandom.GetBytes($vodBytes)
$vodRandom.Dispose()
$vodCronToken = [BitConverter]::ToString($vodBytes).Replace('-', '').ToLowerInvariant()
Set-Clipboard -Value $vodCronToken
```

Conserver cette valeur dans un gestionnaire de mots de passe. Ne pas la mettre dans le code du Worker, dans Git, dans une URL ou dans le chat. Le même secret sera collé dans le `.env` OVH et dans le secret Cloudflare.

## 3. Configurer Laravel sur OVH

Ajouter dans le `.env` existant, sans remplacer les autres paramètres :

```dotenv
CLOUDFLARE_CRON_ENABLED=true
CLOUDFLARE_CRON_TOKEN=COLLER_ICI_LES_64_CARACTERES
```

Puis, en SSH :

```bash
cd ~/vod-finder/prod
VOD_PHP=/usr/local/php8.5/bin/php
"$VOD_PHP" artisan config:cache
"$VOD_PHP" artisan route:list --path=api/internal/notifications
```

La seconde commande doit afficher les routes `status` (GET) et `tick` (POST). Elle n’envoie aucun message.

Une sonde facultative, sans envoi, peut être réalisée en PowerShell en utilisant la variable créée à l’étape 2 :

```powershell
Invoke-RestMethod -Uri 'https://app-vod.venoix.fr/api/internal/notifications/status' -Headers @{ Authorization = "Bearer $vodCronToken"; Accept = 'application/json' }
```

Résultat attendu : `ok = True`, `service = vod-notifications`, `version = 1`. Cette sonde part de votre ordinateur : le traitement Cloudflare d’un appel du Worker peut être différent.

## 4. Créer le Worker

Dans le tableau de bord Cloudflare, ouvrir **Workers & Pages** (généralement dans **Compute / Compute & AI**), puis **Create application / Create Worker**.

1. Créer un Worker nommé `vod-notifications`, avec le modèle Hello World si demandé.
2. Ouvrir **Edit code**.
3. Remplacer le code par le fichier complet [`notifications-worker.mjs`](../cloudflare/notifications-worker.mjs), puis déployer.
4. Ouvrir **Settings → Variables and Secrets → Add**.
5. Choisir le type **Secret**, nommer la variable `CLOUDFLARE_CRON_TOKEN` et coller le secret généré à l’étape 2. Enregistrer/déployer si demandé.
6. Activer les logs du Worker dans **Observability** si nécessaire.

Aucun domaine personnalisé ni déplacement de l’hébergement OVH n’est nécessaire. Le secret appartient au Worker, pas aux variables de compilation d’un projet Pages. L’URL publique `workers.dev` du Worker renvoie volontairement 404 : elle ne sert pas à lancer les notifications manuellement.

Le Worker utilise `redirect: 'manual'` : Cloudflare Workers ne prend pas en charge la valeur `'error'`. Toute réponse 3xx reste refusée, sans suivre la redirection ni transmettre le secret à sa destination. Si une ancienne copie du Worker utilise `'error'`, remplacer cette valeur par `'manual'` dans **Edit code**, puis déployer à nouveau ; le secret et le Cron Trigger restent inchangés.

## 5. Ajouter le déclenchement chaque minute

Dans le Worker, ouvrir **Settings → Triggers / Trigger Events → Cron Triggers → Add** et saisir :

```text
* * * * *
```

Enregistrer. Cloudflare interprète les expressions en UTC ; cette expression s’exécute chaque minute quel que soit le fuseau. La création ou modification d’un Cron Trigger peut prendre jusqu’à 15 minutes pour se propager.

**Activer ce déclencheur peut envoyer de véritables notifications et e-mails en attente**, selon les préférences et les délais de reprise existants.

Documentation Cloudflare : <https://developers.cloudflare.com/workers/configuration/cron-triggers/>.

## 6. Valider le fonctionnement

Après propagation :

- Dans les logs du Worker, vérifier `Cloudflare cron terminé` avec `processed`. `already-triggered` signifie qu’un appel a déjà été traité dans la même minute.
- Dans VOD Finder → **Administration → État du service**, vérifier que `cron:cloudflare` se met à jour chaque minute et que `notifications:deliver` continue de passer. Si une livraison OVH est déjà en cours, le passage Cloudflare peut être accepté sans nouvelle livraison.
- Vérifier que `cron:hourly` reste récent et que les disponibilités continuent leur cycle quotidien.
- Envoyer une recommandation entre deux comptes de test, application destinataire fermée normalement, et comparer les journaux d’envoi à la réception sur Android.

Le Worker déclenche au maximum huit tentatives par passage, tous canaux confondus, et vérifie un budget de 20 secondes entre les envois. Un envoi déjà commencé garde le timeout de son transport ; ce budget ne l’interrompt pas au bout de 20 secondes. Les éléments restants sont repris lors d’un passage suivant. Le Worker abandonne l’attente HTTP après 55 secondes ; un appel OVH peut néanmoins continuer côté serveur jusqu’à son propre délai d’exécution. Les verrous et traces de livraison servent à éviter les doublons.

Les tentatives déjà planifiées à plus tard (par exemple après 15 minutes) gardent ce délai. Le Worker ne relance pas la recherche, TMDb, TVmaze ou Streaming Availability et ne renouvelle pas leurs caches.

## 7. Si Cloudflare renvoie 403

Consulter **Sécurité → Événements** pour le chemin exact `/api/internal/notifications/tick`. Les logs du Worker indiquent le statut et, s’ils sont présents, `cf-ray` et `cf-mitigated`, sans enregistrer le secret ni le corps des réponses.

Identifier le service ou la règle avant de changer les protections :

- Si une règle personnalisée impose le challenge, exclure de **cette règle** uniquement l’appel authentifié, avec l’expression ci-dessous.
- Si le challenge vient d’une fonctionnalité qui accepte une action Skip, utiliser une règle Skip placée avant les protections concernées, limitée à la même expression et aux seules fonctionnalités nécessaires.
- Le **Bot Fight Mode gratuit ne permet pas d’exception par règle WAF**. Ne pas désactiver la protection générale pour faire passer ce Worker. Dans ce cas, conserver le cron OVH et revoir la solution ou l’offre Cloudflare avant activation permanente.

Expression de sélection (remplacer la valeur localement dans Cloudflare, jamais dans le dépôt) :

```text
(http.host eq "app-vod.venoix.fr"
 and http.request.method eq "POST"
 and http.request.uri.path eq "/api/internal/notifications/tick"
 and any(http.request.headers["authorization"][*] eq "Bearer COLLER_ICI_LES_64_CARACTERES"))
```

L’en-tête Authorization est vérifié également dans Laravel. Aucun simple User-Agent, nom de chemin, en-tête `CF-Connecting-IP` ou origine prétendue ne suffit à lancer le traitement. Le secret présent dans une expression est accessible aux administrateurs autorisés à consulter ces règles.

Références : <https://developers.cloudflare.com/waf/custom-rules/skip/options/> et <https://developers.cloudflare.com/bots/get-started/free/>.

## Cron OVH et retour arrière

**Garder la tâche OVH actuelle**, avec le chemin :

```text
vod-finder/prod/cron-hourly.php
```

Les deux déclencheurs partagent le verrou de livraison et les mêmes enregistrements d’envoi. Le passage horaire reste nécessaire pour les données ; cette livraison ne doit pas être remplacée par des synchronisations de catalogue toutes les minutes.

Pour arrêter le Worker, supprimer/désactiver son Cron Trigger, mettre `CLOUDFLARE_CRON_ENABLED=false` dans le `.env` OVH, puis refaire `"$VOD_PHP" artisan config:cache`. Le cron OVH continue de fonctionner normalement. Conserver les données et les clés push.

## Vérifications automatisées

`npm run test:cron` vérifie le Worker avec des réponses HTTP simulées (authentification, refus des redirections, échec sur challenge/HTML, erreurs de livraison et idempotence). Les tests PHP couvrent l’authentification, HTTPS, l’exécution limitée aux notifications, le découpage des lots, la coexistence avec OVH et l’absence de double envoi. Ils ne prouvent pas que votre compte Cloudflare laisse passer les appels réels : le contrôle de l’étape 6 reste nécessaire.
