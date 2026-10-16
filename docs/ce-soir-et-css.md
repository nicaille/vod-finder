# Suggestions pour ce soir et chaîne CSS

## Parcours Ce soir

Depuis l’accueil, un membre connecté peut ouvrir **Pas d’idée ? Trouver quoi regarder ce soir** (`/tonight`). Aucune API n’est appelée avant la soumission. Le formulaire GET conserve les critères dans l’URL : 30, 60, 90, 120 ou 180 minutes maximum ; film, série ou les deux ; genre ; envie. Les critères sont validés côté serveur. Les envies correspondent à des groupes de genres explicites, sans IA ni promesse de classification par âge.

La sélection concerne la France et uniquement les abonnements actifs du compte. Elle exclut les contenus marqués déjà vus, en distinguant film et série même si leurs identifiants sont identiques. La durée du film doit être connue et ne pas dépasser le budget ; pour une série, le maximum des durées d’épisode annoncées est retenu, ou à défaut la durée du dernier épisode. Cette durée reste indicative et ne garantit pas celle de chaque épisode. Une durée inconnue est exclue, ainsi qu’une fiche future ou signalée adulte.

TMDb propose des candidats populaires avec au moins 50 votes. La liste est limitée à une page par format. Les critères genre et envie sont tous deux contrôlés ; des critères trop étroits peuvent ne produire aucun résultat. Les disponibilités sont vérifiées par la stratégie existante Streaming Availability puis complément TMDb. Une offre n’est retenue que si elle est incluse, correspond à un abonnement actif et à son canal exact (« via Prime Video », par exemple). Locations, achats et abonnements supplémentaires non déclarés sont exclus. Les résultats ouvrent la fiche du contenu avec les actions habituelles.

Les candidats publics sont en cache 24 heures, partagés entre membres pour les mêmes critères, plateformes, langue et journée de Paris. Les échecs ne deviennent pas une absence de résultat en cache. Les exclusions personnelles sont recalculées à chaque demande ; aucune sélection personnelle n’est placée dans ce cache. Les fiches et offres réutilisent leurs caches existants. Au maximum 12 fiches et 6 disponibilités sont examinées, avec alternance films/séries, pour afficher jusqu’à 6 idées. Un budget de 15 secondes est vérifié entre ces lectures ; un appel en cours garde son propre timeout. Ce n’est ni un inventaire exhaustif ni une promesse de six résultats. La limite dédiée est de six sélections par minute et par compte, en plus du plafond général membre.

La page distingue absence d’abonnement, aucun résultat confirmé, indisponibilité des sources et résultats partiels. Elle ne relâche pas silencieusement les critères. Les tests utilisent des API simulées, sans consommer de quota ni envoyer de notifications.

## Tailwind 4

La chaîne utilise **Tailwind 4.3.3**, `@tailwindcss/postcss` 4.3.3 et `@tailwindcss/forms` 0.5.11. La configuration est dans `resources/css/app.css` : import Tailwind, sources Blade et pagination Laravel explicites, plugin formulaires et thème. Le fichier `tailwind.config.js` de v3 est supprimé. Autoprefixer n’est plus nécessaire ; le compilateur officiel effectue les transformations requises.

`npm run build` conserve Vite et ses assets avec empreintes. `npm run build:ui` compile le même point d’entrée vers `public/vod.css`, via `scripts/build-ui.cjs`. Ce script utilise directement le plugin officiel et n’introduit pas le CLI ni sa dépendance de surveillance `@parcel/watcher` / micromatch / braces. L’audit **complet** npm, dépendances de développement incluses, est désormais bloquant dans GitHub Actions. Les fichiers CSS/JS générés restent publiés dans le dépôt et dans l’archive OVH.

Utiliser Node 22 comme dans GitHub Actions, puis :

```bash
npm ci
npm run build
npm run build:ui
npm audit
npm run test:cache
npm run test:push
npm run test:browser
```

Tailwind 4 cible les navigateurs modernes (Chrome 111+, Safari 16.4+, Firefox 128+). La compatibilité Android Chrome est contrôlée avec Chromium ; le test ne remplace pas une vérification sur téléphone réel. Les styles personnalisés et le cadre d’affiche 2:3 sont conservés. Aucun changement de base, de PHP ou de `.env` n’est requis. Le déploiement automatique reconstruit les assets ; une récupération WAMP peut utiliser les fichiers déjà compilés sans installer Node.
