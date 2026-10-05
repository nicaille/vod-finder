# Convention de publication

Pour ce projet, lorsque l'utilisateur demande « release », il autorise la création des commits, leur push sur GitHub et leur intégration dans `main` après les vérifications appropriées. Réutiliser cette autorisation sans demander une nouvelle confirmation pour ces opérations.

Après chaque release, fournir systématiquement dans la réponse finale les commandes exactes à exécuter dans le dossier WAMP `C:\wamp64\www\app-vod\vod-finder` pour récupérer et appliquer la version publiée. Adapter les commandes aux changements réels (dépendances, migrations, caches, configuration ou tâches), préciser les éventuelles étapes manuelles nécessaires et distinguer les commandes d’installation de celles qui déclenchent des envois réels. Ne pas se limiter au lien du commit.

Préserver les modifications distantes et effectuer une intégration normale, sans push forcé. Respecter les protections de branche et les contrôles obligatoires de GitHub. Signaler tout blocage concret.

Ne jamais modifier `.env`, publier de secrets ou réinitialiser une base de données de développement. Exécuter les tests sur une base isolée.
