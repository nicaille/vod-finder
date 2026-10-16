# Déploiement automatique GitHub → OVH

Après configuration, chaque push sur `main` lance **Test et déploiement OVH** dans l’onglet GitHub **Actions**. Le déploiement attend la validation Laravel sur PHP 8.3/8.4/8.5 avec SQLite et MySQL 8.0, les tests navigateur et la répétition Laravel 10 → 13. GitHub construit les assets, teste le script dans un conteneur isolé et transfère une archive du commit testé par SSH. Aucun accès Git au dépôt n’est nécessaire sur OVH.

Le serveur installé doit conserver le projet dans `~/vod-finder/prod`, PHP dans `/usr/local/php8.5/bin/php` et Composer dans `~/vod-finder/prod/composer.phar`. Le site public est `https://app-vod.venoix.fr`. Ces chemins sont définis dans `.github/workflows/deploy-ovh.yml`.

## 1. Créer une clé SSH dédiée sur Windows

Dans **PowerShell sur ton ordinateur**, et non dans le terminal OVH :

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\.ssh" | Out-Null
$VOD_KEY = "$env:USERPROFILE\.ssh\vod-finder-actions"
ssh-keygen -t ed25519 -C "github-actions-vod-finder" -f "$VOD_KEY"
```

À la demande de passphrase, appuyer deux fois sur **Entrée** : cette clé est réservée à l’automatisation. Si ce fichier existe déjà, ne pas l’écraser : utiliser la clé existante ou un nouveau nom.

- `vod-finder-actions.pub` : clé publique à ajouter sur OVH.
- `vod-finder-actions` : clé privée à placer uniquement dans un secret GitHub. **Ne pas la coller dans la conversation ni la committer.**

## 2. Autoriser la clé publique sur OVH

Dans le Manager OVH, ouvrir **Web Cloud → Hébergements → ton hébergement → FTP - SSH** et vérifier le serveur SSH public. Les exemples ci-dessous utilisent `ssh.cluster130.hosting.ovh.net` pour le cluster 130 ; si OVH affiche une autre adresse, utiliser celle affichée dans toutes les commandes et dans `OVH_SSH_HOST`. Le nom affiché dans le prompt d’une session (`ssh01.cluster130.gra.hosting.ovh.net`) peut être interne et ne doit pas être repris comme adresse de connexion.

Toujours dans la même fenêtre PowerShell :

```powershell
("restrict " + (Get-Content "$VOD_KEY.pub" -Raw).Trim()) | ssh venoixu@ssh.cluster130.hosting.ovh.net "umask 077; mkdir -p ~/.ssh; printf '\n' >> ~/.ssh/authorized_keys; cat >> ~/.ssh/authorized_keys; chmod 700 ~/.ssh; chmod 600 ~/.ssh/authorized_keys"
```

Saisir le mot de passe OVH si demandé. La commande ajoute la clé sans remplacer les clés existantes. `restrict` bloque les redirections de ports et les terminaux interactifs pour cette clé ; les commandes de déploiement restent autorisées.

Tester ensuite l’accès **sans mot de passe**, ainsi que les fichiers nécessaires :

```powershell
ssh -i "$VOD_KEY" -o IdentitiesOnly=yes -o BatchMode=yes venoixu@ssh.cluster130.hosting.ovh.net 'cd ~/vod-finder/prod && test -f .env && test -f vendor/autoload.php && test -f composer.phar && /usr/local/php8.5/bin/php -v && /usr/local/php8.5/bin/php composer.phar --version'
```

Cette commande doit afficher les versions PHP et Composer. Si elle échoue, résoudre cette étape avant de continuer. Une connexion SSH ordinaire doit aussi fonctionner et avoir enregistré une clé d’hôte connue. Si SSH signale une clé d’hôte modifiée, vérifier le changement auprès d’OVH avant de remplacer l’entrée.

## 3. Ajouter les quatre secrets GitHub

Ouvrir le dépôt : <https://github.com/nicaille/vod-finder/settings/secrets/actions>.

Chemin : **Settings → Secrets and variables → Actions → New repository secret**.

| Nom exact | Valeur |
| --- | --- |
| `OVH_SSH_HOST` | `ssh.cluster130.hosting.ovh.net` |
| `OVH_SSH_USER` | `venoixu` |
| `OVH_SSH_KEY` | Contenu intégral de la clé privée, lignes BEGIN/END comprises |
| `OVH_SSH_KNOWN_HOSTS` | Entrée de clé d’hôte issue de la connexion SSH déjà vérifiée |

Pour copier la clé privée dans le presse-papiers, puis la coller directement dans **Value** du secret `OVH_SSH_KEY` :

```powershell
Get-Content "$VOD_KEY" -Raw | Set-Clipboard
```

Pour copier l’entrée d’hôte et la coller dans `OVH_SSH_KNOWN_HOSTS` :

```powershell
ssh-keygen -F ssh.cluster130.hosting.ovh.net -f "$env:USERPROFILE\.ssh\known_hosts" | Set-Clipboard
```

Le résultat contient les lignes de clé et peut inclure un commentaire commençant par `#`. Les entrées avec un nom d’hôte haché fonctionnent également. Si le résultat est vide, effectuer d’abord une connexion SSH normale et vérifier l’identité de l’hôte avant d’accepter sa clé. Le workflow vérifie strictement cette identité et ne fait pas confiance à une clé récupérée automatiquement à chaque exécution.

## 4. Publier et vérifier le premier déploiement

Une fois les secrets enregistrés, demander **release** pour publier le workflow avec les changements du projet. Ce premier push sur `main` déclenchera déjà le déploiement.

Ouvrir <https://github.com/nicaille/vod-finder/actions>, puis **Test et déploiement OVH**. Vérifier que toutes les étapes sont vertes, puis ouvrir <https://app-vod.venoix.fr>.

Pour relancer le commit actuel sans nouveau push : **Actions → Test et déploiement OVH → Run workflow → Branch: main → Run workflow**. Cela effectue un véritable déploiement. Un lancement depuis une autre branche est ignoré.

Les futures releases sur `main` déclencheront automatiquement le même processus. Un push d’une autre personne sur `main` déclenchera également un déploiement. Restreindre les droits d’écriture du dépôt aux personnes autorisées à publier.

## Ce que le script fait sur OVH

1. Vérifie les fichiers requis, l’empreinte SHA256 de l’archive et les chemins autorisés. Refuse un site déjà en maintenance ou un autre déploiement en cours.
2. Sauvegarde le code actuel dans `~/.vod-finder-deploy/backups/`, hors du dossier public.
3. Active la maintenance et installe le code du commit validé. Supprime uniquement les fichiers devenus obsolètes qui figuraient dans un précédent déploiement automatique.
4. Installe les dépendances du `composer.lock`, avec le Composer local et PHP 8.5, puis actualise la découverte des packages.
5. Exécute `migrate --force`, reconstruit les caches de configuration/routes/vues et retire la maintenance après succès.
6. GitHub vérifie que la page publique `/about` répond sans erreur HTTP.

Le contrôle HTTP utilise le User-Agent `Mozilla/5.0` : sur cet hébergement, une requête curl avec son identifiant habituel renvoie 403, tandis que le même test avec cet identifiant renvoie 200. Le contrôle conserve `--fail` et échoue si la page retourne une erreur HTTP, même avec ce User-Agent.

`.env`, `APP_KEY`, `storage`, les clés VAPID, les sessions, les fichiers utilisateurs et la base existante sont conservés. Aucune commande `migrate:fresh`, `key:generate`, de seed global ou d’envoi d’e-mail n’est exécutée. Les réglages Brevo restent dans la base et `.env`.

Cette automatisation ne configure pas les tâches planifiées OVH pour les alertes ; leur configuration reste indépendante.

## Échec et reprise

- **Tests rouges ou problème SSH/précontrôle** : le script ne met pas le site en maintenance.
- **Échec après activation de la maintenance**, notamment pendant une migration : le site reste en maintenance. Les traces sont dans l’étape GitHub **Déployer par SSH** et, pour Laravel, dans `storage/logs/laravel.log`. Aucun rollback de base automatique n’est tenté.
- **Contrôle HTTP final rouge** : le script a déjà retiré la maintenance. Vérifier le site et les logs ; GitHub ne garantit pas alors le bon fonctionnement de la version.

Après un échec pendant l’installation, ne pas retirer la maintenance avant d’avoir corrigé la cause ou rétabli un code fonctionnel avec des dépendances compatibles. Une fois la cause résolue, les commandes OVH de reprise sont :

```bash
cd ~/vod-finder/prod
VOD_PHP=/usr/local/php8.5/bin/php
"$VOD_PHP" composer.phar install --no-dev --optimize-autoloader --no-interaction --no-scripts
"$VOD_PHP" artisan config:clear
"$VOD_PHP" artisan clear-compiled
"$VOD_PHP" artisan package:discover --ansi
"$VOD_PHP" artisan migrate --force
"$VOD_PHP" artisan config:cache
"$VOD_PHP" artisan route:cache
"$VOD_PHP" artisan view:cache
"$VOD_PHP" artisan up
```

Ensuite relancer le workflow pour terminer le suivi du commit déployé. Si une migration a changé le schéma, restaurer seulement une archive de code peut être insuffisant : utiliser une sauvegarde de base compatible si nécessaire. Les archives automatiques contiennent **le code uniquement** ; conserver les sauvegardes de base OVH et sauvegarder séparément `.env` et `storage`.

Le commit enregistré après déploiement est consultable sur OVH :

```bash
cat ~/.vod-finder-deploy/current-commit.txt
```

Un arrêt brutal de la connexion peut laisser `~/.vod-finder-deploy/lock`. Ne retirer ce dossier qu’après avoir vérifié qu’aucun déploiement ne tourne encore. Les archives de code restent conservées ; les nettoyer périodiquement en gardant les versions nécessaires, pour éviter de remplir le quota OVH.

## Validation locale du mécanisme

Le test suivant simule le serveur dans un conteneur jetable ; il ne touche pas une base ou un serveur existant :

```bash
docker run --rm --entrypoint bash -v "$PWD:/source:ro" debian:bookworm-slim /source/tests/Deployment/ovh-deploy.sh
```

Il couvre le succès, la conservation des fichiers privés, la sauvegarde de code, la suppression limitée des fichiers obsolètes, les échecs de migration, la maintenance existante, les archives invalides, les liens symboliques et les déploiements concurrents.

### Tâche planifiée OVH (une fois par heure)

Dans le Manager OVH, choisissez PHP 8.5, une exécution toutes les heures et le chemin relatif au répertoire home :

```text
vod-finder/prod/cron-hourly.php
```

Aucun argument. Ce fichier est livré avec les releases. Il synchronise les séries, vérifie les disponibilités une fois par jour (au premier passage, avec reprise après échec) puis envoie les notifications autorisées. Les notifications peuvent donc attendre jusqu'à une heure. Le passage apparaît sous `cron:hourly` dans l'administration. Ne configurez pas simultanément un autre cron `schedule:run`. Le fichier ignore les passages pendant la maintenance et empêche deux exécutions horaires de se chevaucher.

Prérequis et résultats de la montée de version : [migration Laravel 13](migration-laravel-13.md). WAMP doit utiliser PHP 8.3 ou supérieur ; OVH conserve PHP 8.5.

## Cloudflare devant OVH

Cloudflare peut gérer les DNS et le proxy du site sans déplacer l’hébergement ni transférer le domaine hors du registrar OVH. Commencer avec les enregistrements en **DNS uniquement**, recopier tous les enregistrements existants (notamment MX, SPF, DKIM, DMARC et validations Brevo), puis activer le proxy uniquement sur `app-vod.venoix.fr` après publication du support. SSH, FTP et services mail restent en DNS uniquement ; le workflow continue d’utiliser le serveur SSH OVH. Si DNSSEC est déjà activé, coordonner la désactivation de l’ancien DS avant le changement de serveurs DNS puis l’activation de Cloudflare et l’enregistrement de son nouveau DS chez le registrar.

Réglages de départ :

- SSL/TLS **Full (strict)**, certificat OVH valide ; ne pas utiliser Flexible.
- Garder `APP_URL=https://app-vod.venoix.fr` et l’origine actuelle de la PWA. Ne pas changer APP_KEY, les clés VAPID ou les sessions pour cette opération.
- Conserver le cache standard, sans règle Cache Everything pour le site. Ne jamais mettre en cache les pages et JSON personnalisés, l’admin ou l’authentification. Respecter `Cache-Control: private, no-store`.
- Prévoir une règle de cache Bypass pour `/service-worker.js` afin de faciliter les mises à jour de la PWA. Après une release, si le proxy a déjà servi les fichiers statiques, purger les URL `/vod.css`, `/assets/vod-ui.css` et `/assets/vod-ui.js` pour éviter une ancienne version conservée en bordure.
- Laisser Rocket Loader désactivé. Ne pas imposer de challenge interactif aux appels AJAX/JSON, au service worker ou au contrôle GitHub de `/about`. Un User-Agent Firefox ne permet pas à curl de résoudre un challenge JavaScript. Adapter une règle ciblée si nécessaire, sans désactiver globalement les protections ou ouvrir l’administration.
- Les tâches OVH exécutent le fichier local `cron-hourly.php` : elles ne traversent pas Cloudflare. Les appels sortants vers TMDb, TVmaze, Brevo et les services push restent directs.

`config/trustedproxy.php` contient exclusivement les plages IPv4 et IPv6 officielles Cloudflare, vérifiées le 17 octobre 2026 sur <https://www.cloudflare.com/ips-v4> et <https://www.cloudflare.com/ips-v6>. Laravel accepte `X-Forwarded-For` et `X-Forwarded-Proto` uniquement si le pair appartient à ces plages. Les en-têtes de nom d’hôte et de port ne sont pas approuvés. Une requête directe ne peut pas choisir son IP avec `CF-Connecting-IP` ou `X-Forwarded-For`. Cette configuration conserve le fonctionnement en DNS uniquement, sans nouveau paramètre `.env`. Les plages doivent être revues si Cloudflare les modifie.

Après activation du proxy, vérifier la connexion, une recherche, une fiche, une action de playlist, la confirmation admin, un test push, une recommandation et le contrôle GitHub. Contrôler aussi que les événements de connexion `auth.failed` / `auth.lockout` dans le journal de sécurité portent l’IP attendue du visiteur, puis les restrictions `ADMIN_ALLOWED_IPS` si utilisées. Ces vérifications réelles complètent les tests automatisés IPv4/IPv6, HTTPS, usurpation d’en-têtes et contrôle IP admin. Selon le traitement d’IP effectué par le frontal OVH, l’application peut déjà recevoir l’IP réelle ; si un intermédiaire OVH apparaît à la place, identifier la chaîne avant toute modification de confiance. Ne pas faire confiance à tous les proxys (`*`) pour contourner ce diagnostic.

Le proxy réduit les abus en amont ; il ne garantit pas l’absence de collecte ni ne rend inaccessible une origine OVH encore publiquement joignable. Une réinstallation de la PWA n’est pas requise lorsque l’URL reste identique.
