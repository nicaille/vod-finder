<?php

namespace App\Support;

class LegalContent
{
    public static function terms(): string
    {
        return <<<'MD'
Version du 18 octobre 2026.

### Objet et accès au service

VOD Finder est un outil de découverte de films et de séries : recherche, informations sur les contenus et leurs disponibilités, playlist, listes, suivi de séries et recommandations entre contacts. L’application ne diffuse pas les œuvres et ne fournit pas d’abonnement aux plateformes référencées.

La consultation du catalogue est accessible sans compte. Les fonctions personnelles nécessitent un compte. Vous vous engagez à fournir des informations exactes, à protéger vos moyens de connexion et à signaler tout accès suspect à votre compte à [vod-finder@venoix.fr](mailto:vod-finder@venoix.fr).

### Utilisation respectueuse

Utilisez le service à des fins personnelles et dans le respect des autres utilisateurs. Les recommandations et demandes de contact ne doivent pas servir au harcèlement, au spam, à l’usurpation d’identité ou à la diffusion de contenus illicites. Ne publiez pas les informations personnelles d’une autre personne sans autorisation.

L’extraction automatisée du catalogue, la collecte des données des utilisateurs, le contournement des protections ou des quotas et les tentatives d’intrusion sont interdits. Les accès abusifs peuvent être limités ou suspendus pour protéger le service. Un problème ou une contestation peut être signalé à l’adresse de contact ci-dessus.

### Informations, plateformes et alertes

Les informations proviennent notamment de TMDb, TVmaze et Streaming Availability / Movie of the Night. Les offres dépendent du pays, de l’abonnement et des options souscrites. Les catalogues, prix, dates et horaires peuvent changer ou être incomplets. Vérifiez l’offre et ses conditions directement auprès de la plateforme avant tout achat ou visionnage.

Les notifications sont une aide au suivi, sans garantie de réception immédiate. Elles dépendent de vos réglages, des autorisations du navigateur et du système, de la connexion réseau et des services de livraison. Une application arrêtée de force peut ne plus recevoir de notifications jusqu’à sa réouverture.

Les liens vers des services tiers vous soumettent à leurs propres conditions et politiques de confidentialité. VOD Finder ne contrôle pas leur fonctionnement.

### Propriété intellectuelle

Les affiches, images, titres, marques et données des œuvres appartiennent à leurs titulaires respectifs. Leur affichage dans VOD Finder ne transfère aucun droit de réutilisation. Les mentions des fournisseurs figurent en bas de cette page.

Le code de l’application est déclaré sous licence MIT dans le projet. Cette licence ne couvre pas les œuvres, données, images ou marques de tiers.

### Disponibilité et évolution

Le service peut évoluer ou être temporairement interrompu pour maintenance, incident ou indisponibilité d’un fournisseur. Ces conditions ne limitent pas les droits et garanties que la loi vous reconnaît. Vous pouvez cesser d’utiliser le service et demander la suppression de votre compte selon les modalités de la section Confidentialité.

Les modifications des présentes conditions sont publiées sur cette page avec une date de version.
MD;
    }

    public static function privacy(): string
    {
        return <<<'MD'
Version du 18 octobre 2026.

### Contact et périmètre

VOD Finder est l’éditeur de l’application et le responsable des traitements décrits dans cette politique. Pour toute question ou demande concernant vos données, contactez l’administration de VOD Finder : [vod-finder@venoix.fr](mailto:vod-finder@venoix.fr).

### Données utilisées et finalités

- **Compte et connexion** : prénom, nom, pseudo, adresse e-mail, mot de passe conservé sous forme de hachage, vérification de l’adresse et préférences. Ces données permettent la gestion du compte, l’authentification et la sécurité.
- **Votre bibliothèque** : plateformes et abonnements déclarés, playlist, listes, coups de cœur, contenus marqués comme vus et séries suivies. Ces données servent à personnaliser les fonctions demandées.
- **Relations et recommandations** : demandes de contact, relations acceptées, blocages, recommandations envoyées et reçues, dates et états de consultation ou de livraison. Les destinataires ont accès aux informations nécessaires à l’échange.
- **Notifications** : choix de canaux, adresse e-mail et, si vous activez les notifications sur un appareil, abonnement push avec son adresse technique et ses clés publiques. Ces données servent à transmettre les alertes choisies et à gérer leur livraison.
- **Fonctionnement et sécurité** : informations de session, adresses IP, informations techniques du navigateur, événements de sécurité, journaux d’erreurs et mesures des appels aux API ou du cache. Elles permettent de protéger, diagnostiquer et exploiter le service.

La gestion du compte et des fonctions demandées repose sur l’exécution du service. La prévention des abus et la maintenance reposent sur l’intérêt légitime à assurer sa sécurité et son fonctionnement. Les notifications facultatives et la visibilité volontaire de vos informations reposent sur vos choix, que vous pouvez modifier à tout moment. Les messages nécessaires à la sécurité du compte, notamment la réinitialisation du mot de passe, peuvent être envoyés indépendamment des alertes facultatives.

### Visibilité et destinataires

L’apparition dans l’annuaire et l’affichage de votre nom réel sont des réglages distincts. Les autres utilisateurs ne disposent pas d’un annuaire public des adresses e-mail. Vos contacts peuvent voir votre identité d’affichage et les recommandations que vous leur adressez. Vérifiez les réglages de partage avant de rendre une liste accessible à d’autres personnes.

Les administrateurs habilités peuvent consulter les données des comptes, leurs contenus et les journaux pour gérer le service et résoudre les incidents. Ces accès sont réservés à l’administration.

L’hébergement utilise OVHcloud et la protection des échanges peut passer par Cloudflare. Les e-mails peuvent être transmis par Brevo ou le fournisseur de messagerie configuré ; les notifications push passent par le service du navigateur et du système de votre appareil. Ces prestataires reçoivent les données nécessaires à leur rôle.

Les recherches et identifiants de contenus peuvent être transmis aux fournisseurs du catalogue (TMDb, TVmaze et Streaming Availability / Movie of the Night). Le chargement d’images distantes et l’ouverture de liens de plateformes peuvent communiquer à ces services votre adresse IP et des informations techniques de connexion. Leurs propres politiques s’appliquent également.

Certains fournisseurs peuvent traiter des données hors de l’Espace économique européen. Le lieu de traitement et les garanties applicables dépendent du fournisseur et du service utilisé ; leurs politiques de confidentialité précisent ces modalités. Il n’est pas garanti que tous les traitements restent en France ou dans l’Union européenne.

### Connexion avec un fournisseur externe

Si cette option est activée et que vous choisissez Google, Microsoft ou Facebook, le fournisseur transmet les informations nécessaires à la connexion : identifiant du compte, nom et, selon vos autorisations, adresse e-mail. VOD Finder conserve le lien avec cet identifiant ; les jetons d’accès du fournisseur ne sont pas conservés en base pour une utilisation ultérieure.

Un compte existant n’est pas associé automatiquement sur la seule base d’une adresse e-mail identique. Vous pouvez gérer les connexions associées depuis votre compte. Le fournisseur reste responsable de ses propres traitements lors de l’authentification.

### Cookies et stockage sur votre appareil

L’application utilise des cookies de session et de sécurité pour vous connecter et protéger les formulaires. Le navigateur peut aussi conserver des préférences, des résultats temporaires et les ressources nécessaires à l’application installable. Cloudflare peut utiliser des mécanismes de sécurité selon sa configuration.

Ces éléments servent au fonctionnement de l’application. Vous pouvez les supprimer dans les réglages de votre navigateur ; cela peut vous déconnecter ou effacer vos préférences locales. Retirer l’application de votre écran d’accueil ne supprime pas votre compte.

### Notifications et retrait des autorisations

Vous pouvez modifier les notifications dans votre compte et retirer l’autorisation de notifications dans les réglages du navigateur ou d’Android. L’autorisation est propre à chaque appareil : activer les e-mails ne suffit pas à autoriser les notifications push sur un téléphone. Désactiver les alertes ne supprime pas les recommandations déjà reçues.

### Conservation et suppression du compte

Les données du compte et de votre bibliothèque sont conservées tant que le compte existe, sous réserve des suppressions que vous effectuez. Les journaux de sécurité de l’application sont configurés pour une conservation de 30 jours. La durée des caches dépend des données mises en cache. Le journal général de l’application ne dispose pas, dans sa configuration par défaut, d’une purge automatique. Les journaux techniques ne sont pas tous effacés immédiatement lors de la fermeture du compte. Les éventuelles sauvegardes suivent les cycles de conservation de l’hébergement. Aucun effacement automatique après une durée d’inactivité déterminée n’est annoncé.

Pour supprimer votre compte, connectez-vous, ouvrez [les paramètres du profil](/profile), puis la rubrique de suppression du compte et confirmez avec votre mot de passe. Si vous vous êtes inscrit avec Google, Microsoft ou Facebook sans mot de passe local, utilisez d’abord [la réinitialisation du mot de passe](/forgot-password) pour en définir un. Le dernier administrateur doit désigner un autre administrateur avant de supprimer son compte.

La suppression retire le compte et ses données associées dans l’application, y compris ses liens de connexion externes. Elle ne supprime pas votre compte chez Google, Microsoft ou Facebook ni les copies de messages déjà reçues par e-mail ou enregistrées par un destinataire. Vous pouvez aussi révoquer l’accès de VOD Finder dans les réglages du fournisseur de connexion. Si vous ne pouvez plus vous connecter, adressez votre demande à [vod-finder@venoix.fr](mailto:vod-finder@venoix.fr) ; une vérification proportionnée de votre identité pourra être nécessaire.

### Vos droits

Selon les conditions prévues par la réglementation, vous pouvez demander l’accès, la rectification, l’effacement ou la portabilité de vos données, la limitation d’un traitement ou vous y opposer. Vous pouvez retirer vos choix facultatifs sans remettre en cause les traitements déjà réalisés.

Adressez votre demande à [vod-finder@venoix.fr](mailto:vod-finder@venoix.fr). Une réponse est normalement apportée sous un mois ; une prolongation motivée est possible dans les cas prévus par le RGPD. Vous pouvez également déposer une réclamation auprès de la [CNIL](https://www.cnil.fr/fr/adresser-une-plainte).

Les évolutions de cette politique sont publiées sur cette page avec une date de version.
MD;
    }
}
