# Convention de publication

Pour ce projet, lorsque l'utilisateur demande « release », il autorise la création des commits, leur push sur GitHub et leur intégration dans `main` après les vérifications appropriées. Réutiliser cette autorisation sans demander une nouvelle confirmation pour ces opérations.

Après chaque release, fournir systématiquement dans la réponse finale les commandes exactes à exécuter dans le dossier WAMP `C:\wamp64\www\app-vod\vod-finder` pour récupérer et appliquer la version publiée. Adapter les commandes aux changements réels (dépendances, migrations, caches, configuration ou tâches), préciser les éventuelles étapes manuelles nécessaires et distinguer les commandes d’installation de celles qui déclenchent des envois réels. Ne pas se limiter au lien du commit.

Fournir également les commandes de redéploiement OVH dans `~/vod-finder/prod`, avec `VOD_PHP=/usr/local/php8.5/bin/php` et le Composer local `composer.phar` si nécessaire. Le site est `https://app-vod.venoix.fr`. Si le déploiement est réalisé par FTP, préciser les fichiers à transférer. Préserver `.env`, `APP_KEY`, `storage` (dont les clés push) et la base ; ne pas répéter les commandes réservées à une première installation. Une commande qui déclenche un véritable e-mail de test doit être présentée séparément des commandes de déploiement.

Le workflow `.github/workflows/deploy-ovh.yml` permet le déploiement OVH automatique de chaque push sur `main`, après tests, lorsque les quatre secrets SSH décrits dans `docs/deploiement-ovh.md` sont configurés. Lors d’une release, vérifier et rapporter son résultat si accessible ; ne pas confondre push réussi et déploiement réussi. Quand le déploiement automatique a réussi, fournir les commandes de vérification OVH plutôt que demander de répéter l’installation. Garder les commandes WAMP et signaler les étapes manuelles réellement nécessaires.

Préserver les modifications distantes et effectuer une intégration normale, sans push forcé. Respecter les protections de branche et les contrôles obligatoires de GitHub. Signaler tout blocage concret.

Ne jamais modifier `.env`, publier de secrets ou réinitialiser une base de données de développement. Exécuter les tests sur une base isolée.
