# Connexions Google, Microsoft et Facebook

Les trois fournisseurs permettent de créer un compte et de se reconnecter. Aucun identifiant réel n’est livré. Un fournisseur apparaît sur Connexion et Inscription seulement si son activation, son identifiant client, son secret et son URL de retour sont configurés. La connexion habituelle par e-mail/mot de passe reste disponible. Apple n’est pas inclus dans cette première version.

## Parcours et protection des comptes

- L’utilisateur est redirigé vers le fournisseur, puis revient sur le site dans le même navigateur. Pas de SDK tiers chargé sur les pages ni de popup imposée : le parcours convient à Android Chrome et à la PWA.
- `state` est aléatoire, lié à la session, au fournisseur et à l’utilisateur éventuellement connecté. Le parcours expire après dix minutes et le retour n’est utilisable qu’une fois. Google et Microsoft utilisent également PKCE. Les échanges HTTPS gardent la vérification TLS et des timeouts. Les routes OAuth sont limitées et leurs réponses sont privées, non mises en cache.
- Le lien d’un compte repose sur l’identifiant stable du fournisseur, jamais sur son nom ou seulement son e-mail. Si l’adresse existe déjà, se connecter avec la méthode existante puis associer le fournisseur dans **Mon compte → Méthodes de connexion**, après confirmation récente du mot de passe.
- Un nouveau membre confirme prénom, nom, pseudo facultatif et e-mail. Les préférences de notifications et de partage ne sont jamais activées automatiquement. Google peut fournir une adresse vérifiée ; une adresse saisie manuellement ou fournie par Microsoft/Facebook reste à confirmer avant les alertes e-mail. Le badge Facebook `verified` ne prouve pas la vérification d’une adresse.
- Les jetons OAuth d’accès, de renouvellement et les photos ne sont pas stockés. Seuls le fournisseur et son identifiant sont conservés dans `social_accounts`. Les données minimales du formulaire restent en session au maximum dix minutes. Les erreurs ne journalisent ni codes, jetons, secrets ni réponses personnelles des fournisseurs.
- Un compte créé socialement n’a pas de mot de passe local utilisable. Depuis Mon compte, le lien **définis un mot de passe par e-mail** utilise le processus de réinitialisation sécurisé. Ce mail passe par Brevo si son API est activée dans l’admin, sinon par le mailer Laravel existant ; il est transactionnel et ne dépend pas du choix des alertes. Définir ce mot de passe permet d’associer d’autres fournisseurs, de modifier l’adresse et d’utiliser la confirmation admin. La dernière méthode ne peut pas être retirée sans mot de passe local.
- Une connexion sociale ne confirme pas l’accès admin. Les droits, la confirmation du mot de passe et les éventuelles restrictions IP de l’admin restent requis. Les comptes et données existants, dont les mots de passe, restent inchangés.

## Configuration des fournisseurs

En production, garder `APP_URL=https://app-vod.venoix.fr`. Les URL ci-dessous doivent correspondre **exactement**, sans slash final ajouté, aux valeurs enregistrées chez le fournisseur. Elles utilisent une requête GET et les cookies de session habituels (`SameSite=Lax`, HTTPS). Ne pas activer de challenge interactif ni de cache Cloudflare sur `/auth/*` ou `/oauth/*`.

| Fournisseur | URL de retour production |
| --- | --- |
| Google | `https://app-vod.venoix.fr/auth/google/callback` |
| Microsoft | `https://app-vod.venoix.fr/auth/microsoft/callback` |
| Facebook | `https://app-vod.venoix.fr/auth/facebook/callback` |

### Google

Dans [Google Cloud](https://console.cloud.google.com/), créer/configurer le projet et **Google Auth Platform** (ou APIs et services → Écran de consentement). Choisir une audience externe si les membres utilisent leurs comptes Google personnels. Renseigner les informations du projet et le domaine autorisé `venoix.fr`. Créer un client OAuth de type **Application Web**, puis enregistrer l’URL de retour Google ci-dessus. Seuls identité, profil de base et e-mail sont nécessaires. Tant que l’application est en mode test, ajouter explicitement les comptes de test ; publier l’application pour l’ensemble des utilisateurs en suivant les éventuelles demandes de vérification Google.

### Microsoft

Dans [Microsoft Entra](https://entra.microsoft.com/), **Applications → Inscriptions d’applications → Nouvelle inscription**. Choisir les comptes de toute organisation **et les comptes Microsoft personnels** si Outlook/Hotmail doivent être acceptés. Enregistrer l’URL de retour comme plateforme **Web**. Copier l’ID d’application client puis créer un secret client et conserver sa **valeur**, pas son identifiant. Le fournisseur utilise `common` et l’autorisation déléguée `User.Read` avec OpenID/profil ; aucune permission d’administration, d’écriture ou de lecture de l’organisation n’est nécessaire. Surveiller la date d’expiration du secret.

### Facebook

Dans [Meta for Developers](https://developers.facebook.com/), créer une application adaptée à Facebook Login pour un site web. Configurer le domaine `venoix.fr`, l’URL HTTPS du site et l’URL de redirection OAuth valide ci-dessus. Utiliser l’ID de l’application et son secret. Vérifier l’accès au profil public et à l’e-mail. Un utilisateur peut ne pas fournir d’adresse : le formulaire VOD Finder la demandera alors. En mode développement, seuls les rôles/testeurs autorisés peuvent se connecter. La politique de confidentialité et les instructions de suppression sont publiées sur `https://app-vod.venoix.fr/about#confidentialite` après déploiement de la migration correspondante ; les conditions d’utilisation se trouvent sur `https://app-vod.venoix.fr/about#conditions-utilisation`. Les textes sont modifiables dans l’administration : vérifier leur adéquation aux pratiques réelles et les informations du responsable avant de les déclarer dans Meta. Terminer les contrôles/publication exigés avant d’activer l’accès public. La suppression du compte existante se trouve dans `/profile`, après définition/confirmation d’un mot de passe.

## Variables `.env`

Renseigner uniquement les fournisseurs prêts. Ne jamais partager leurs secrets dans le chat ni les committer.

```dotenv
GOOGLE_LOGIN_ENABLED=true
GOOGLE_CLIENT_ID=valeur_du_client_web
GOOGLE_CLIENT_SECRET=valeur_du_secret
GOOGLE_REDIRECT_URI=https://app-vod.venoix.fr/auth/google/callback

MICROSOFT_LOGIN_ENABLED=true
MICROSOFT_CLIENT_ID=identifiant_application
MICROSOFT_CLIENT_SECRET=valeur_du_secret
MICROSOFT_REDIRECT_URI=https://app-vod.venoix.fr/auth/microsoft/callback

FACEBOOK_LOGIN_ENABLED=false
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=https://app-vod.venoix.fr/auth/facebook/callback
```

Si une variable `*_REDIRECT_URI` est absente, l’URL est déduite de `APP_URL`. Si elle est présente mais vide, renseigner l’URL explicitement avant activation. Sous WAMP, créer des clients de développement distincts lorsque le fournisseur le permet et enregistrer les URL locales exactes ; les exigences HTTPS et les domaines admis dépendent du fournisseur. Un tunnel HTTPS de développement peut être nécessaire. Ne pas réutiliser une URL production comme retour vers WAMP.

Après publication autorisée : installer le nouveau `composer.lock`, appliquer la migration puis reconstruire les caches. Le workflow OVH le fait automatiquement. Après modification des variables OAuth sur OVH :

```bash
cd ~/vod-finder/prod
VOD_PHP=/usr/local/php8.5/bin/php
"$VOD_PHP" artisan config:cache
```

Tester avec un nouveau compte fournisseur, un compte local déjà existant (liaison explicite), un refus de consentement et un compte admin. Sur mobile installé, vérifier aussi le retour vers la PWA ; certains navigateurs peuvent ouvrir un onglet Chrome, sans modifier le compte ou les abonnements de l’application.

## Vérification automatisée

Les tests couvrent les pilotes réels de redirection, `state`, PKCE, un échange Google avec réponses HTTP simulées, les erreurs et expirations, l’inscription avec/sans e-mail, les collisions et associations, l’absence de privilèges admin implicites, le retrait de la dernière méthode et les mails de définition de mot de passe via Brevo simulé.

La simulation navigateur est activée uniquement dans la copie jetable du harnais :

```bash
VOD_BROWSER_OAUTH_TEST=1 npm run test:browser
```

Ce drapeau n’existe pas dans l’application déployée. Les trois fournisseurs sont configurés avec des valeurs fictives et le retour Google est simulé ; aucune clé réelle ou notification n’est utilisée. GitHub Actions exécute également ce parcours. Les essais automatisés ne remplacent pas la validation réelle des trois applications configurées chez leurs éditeurs.
