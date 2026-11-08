# Inviter un proche depuis le répertoire ou un lien

## Parcours

Dans **Mon compte → Mes contacts → Inviter un proche** :

- Sur les navigateurs qui proposent Contact Picker en HTTPS, **Choisir dans mon répertoire** ouvre le sélecteur natif pour un seul contact. L’application demande uniquement ses e-mails et téléphones, et propose un choix si plusieurs coordonnées sont retournées.
- Sans API compatible, les champs manuels et le lien restent disponibles.
- **Envoyer l’invitation par e-mail** est une action explicite. Elle utilise la configuration Brevo de l’admin ou le SMTP existant, pour un compte déjà inscrit ou un nouveau proche. L’expéditeur doit avoir confirmé sa propre adresse.
- **Créer un lien d’invitation** prépare un lien privé valable 7 jours. Ensuite **Partager…** utilise le partage natif (WhatsApp, Signal, etc. selon les applications installées), **Copier le lien** le copie et **Préparer un SMS** ouvre le composeur du téléphone. L’envoi du SMS est confirmé dans l’application SMS ; VOD Finder ne possède pas de passerelle SMS.
- Chaque lien permet une seule acceptation. Pour plusieurs proches, créer un lien pour chacun. Copier ou partager le lien avant de quitter la page : le jeton brut n’est pas conservé dans la table d’invitations.
- **Actualiser et gérer mes invitations** affiche l’historique paginé et permet au créateur de révoquer les invitations non utilisées.

Le destinataire voit une page d’invitation avec le pseudo ou le nom que l’expéditeur a choisi de partager. Il se connecte ou crée son compte, puis clique explicitement sur **Accepter l’invitation**. Les demandes de permissions de notifications restent séparées : si l’inscription ouvre d’abord les réglages de notifications, un lien **reprendre la mise en relation** conserve l’invitation. La connexion classique et le retour d’un compte OAuth existant reprennent directement la page d’acceptation. Une nouvelle inscription OAuth conserve aussi le lien dans Mon compte.

Ouvrir un lien, scanner une prévisualisation ou s’inscrire ne crée aucune relation. Une acceptation valide rend les deux membres contacts et crée l’événement social habituel ; les notifications respectent les préférences du compte et les autorisations des appareils. Une invitation ne réactive aucune préférence de notification.

## Confidentialité et sécurité

- Le répertoire complet n’est jamais importé. Le numéro reste dans la page et sert uniquement à construire le lien `sms:` ; aucun nom ou téléphone sélectionné n’est envoyé au serveur.
- Pour un e-mail, seule l’adresse choisie est envoyée à l’application puis au prestataire configuré. Elle n’est pas conservée dans la table d’invitations ; le prestataire et ses journaux de livraison ont leur propre conservation.
- La table conserve l’expéditeur, l’empreinte SHA-256 du jeton aléatoire de 256 bits, les dates et l’identifiant de l’acceptant. Le jeton brut est nécessaire dans le lien, le message envoyé et temporairement dans la session de reprise ; il reste confidentiel.
- Les pages `/invite/*` sont privées/non mises en cache. L’application demande `Referrer-Policy: no-referrer` et place aussi une métadonnée `referrer=no-referrer` avant les ressources du document. Cette dernière protège les requêtes et navigations du navigateur même si un frontal réécrit l’en-tête HTTP (la réponse publique OVH/Cloudflare conserve actuellement `same-origin`). Le test Chromium reproduit cet en-tête et vérifie l’absence de Referer sur les scripts et feuilles de style. Les jetons sont masqués dans la consultation des journaux admin. Les journaux d’accès de l’hébergeur peuvent contenir les URLs : leur accès et leur conservation doivent rester maîtrisés.
- Création authentifiée, protection CSRF, limites de 5 créations/minute et 20/jour par compte. Les invitations par e-mail ont aussi un budget global de 100/jour, distinct du quota effectif du prestataire ; les autres notifications conservent leur fonctionnement.
- Les e-mails ne sont pas envoyés pour une relation bloquée/refusée, un compte déjà en relation ou l’adresse de l’expéditeur. La réponse reste générique et n’annonce pas si l’adresse correspond à un compte.
- L’acceptation vérifie les comptes, l’expiration, la révocation et l’usage sous verrou transactionnel. Aucun blocage ou refus existant n’est levé par un lien.
- Les invitations sont supprimées 30 jours après leur expiration par le cron horaire OVH ou le planificateur Laravel quotidien. Le nettoyage ne supprime pas les relations acceptées.

## Installation et vérification

Une migration additive crée `contact_invitations`. Au déploiement, appliquer les migrations habituelles ; aucune nouvelle clé ni API de carnet d’adresses n’est nécessaire. Les nouveaux assets sont `/assets/contact-invitations.js` et `/assets/contact-invitations.css`.

Les tests PHP utilisent SQLite et un MySQL jetable pour les invitations, les relations et le cron ; Brevo/SMTP sont simulés. Les tests Chromium contrôlent le sélecteur simulé, le geste utilisateur pour le partage, l’absence de téléphone dans les requêtes, les vues 320/390 px, le repli sans API et le parcours complet lien → inscription → acceptation.

La validation finale sur un vrai Android/Chrome installé reste nécessaire : le navigateur, la version Android et les applications installées déterminent la présence du sélecteur et des applications SMS/partage. Vérifier aussi l’annulation et les refus de permission. Aucun test automatisé ne doit envoyer un véritable SMS ni un e-mail à un proche.
