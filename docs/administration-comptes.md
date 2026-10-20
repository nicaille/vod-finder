# Modifier un compte depuis l’administration

Ouvrir **Administration → Utilisateurs**, rechercher le compte, ouvrir son profil puis cliquer sur **Modifier le compte**.

L’éditeur permet de modifier le prénom, le nom, le pseudo, l’e-mail, les préférences de notifications et la visibilité dans l’annuaire. Les choix de partage et d’alertes doivent correspondre à ceux de l’utilisateur. Les contenus, abonnements aux plateformes, relations, connexions externes et mot de passe sont conservés. Les droits admin restent gérés dans l’onglet Administrateurs.

L’accès exige un compte administrateur, une confirmation récente du mot de passe et les éventuelles restrictions IP existantes. Les écritures sont protégées par CSRF et les limites de l’administration. Les logs de sécurité conservent l’identifiant de l’administrateur, celui du compte et les noms des champs modifiés, sans leurs anciennes ou nouvelles valeurs personnelles.

## Changer une adresse e-mail

Le pseudo et l’e-mail sont contrôlés pour éviter les doublons, sans distinction de casse. Changer l’adresse retire sa confirmation, invalide les liens de réinitialisation du mot de passe correspondants et renouvelle le jeton de connexion persistante. Aucun e-mail n’est envoyé par l’enregistrement du formulaire.

L’utilisateur doit ouvrir **Mon compte** et demander/confirmer le lien de vérification avant de recevoir ses alertes e-mail sur la nouvelle adresse. L’admin ne peut pas déclarer une adresse vérifiée depuis cet éditeur. La liaison OAuth existante est conservée : elle repose sur l’identifiant du fournisseur, pas sur une association automatique par e-mail.

## Notifications : préférence du compte et permission de l’appareil

Un administrateur peut enregistrer les préférences générales, e-mail, navigateur et Disponible maintenant. Ces choix prennent effet lors des prochains traitements de livraison, sous réserve des autres conditions déjà appliquées (contact accepté, suivi actif, adresse confirmée, délais de reprise, etc.). Ils peuvent donc permettre l’envoi d’alertes récentes encore en attente.

L’admin **ne peut pas accorder l’autorisation Chrome/Android à distance**, enregistrer un appareil à la place de son propriétaire, ni réactiver une autorisation refusée dans les réglages du système.

- **E-mail** : activer les préférences générales et e-mail, avec une adresse confirmée. Aucune autorisation sur le téléphone n’est nécessaire pour l’envoi du mail.
- **Navigateur/PWA** : activer les préférences générales et navigateur ; l’utilisateur doit également autoriser les notifications et enregistrer chaque appareil depuis Mon compte sur cet appareil.
- **Appareil déjà enregistré** : l’admin peut réactiver la préférence serveur à la demande de la personne. La réception dépend encore de l’autorisation locale, de l’abonnement push et des restrictions Android.
- **Aucun appareil enregistré** : cocher la préférence dans l’admin ne crée pas d’abonnement push. L’utilisateur doit ouvrir l’application et autoriser les notifications sur son appareil.

Le nombre d’appareils affiché correspond aux abonnements enregistrés sur le serveur. Il ne prouve pas que les notifications restent autorisées ni qu’elles ont été affichées ou lues. Une application arrêtée de force par Android peut nécessiter une réouverture.
