{!! $senderName !!} t’invite sur VOD Finder.

Échangez des recommandations de films, séries et personnes à découvrir.
Découvrir et accepter l’invitation : {!! $invitationUrl !!}

Connecte-toi ou crée ton compte, puis accepte la mise en relation. Aucune relation n’est créée simplement en ouvrant ce message.
Ce lien est valable jusqu’au {!! $expiry !!} et permet une seule acceptation.

Tu peux ignorer cet e-mail si tu ne souhaites pas donner suite. Aucun abonnement aux notifications n’est activé par cette invitation.
@include('emails.text.footer', ['notificationReason' => 'Cette invitation ponctuelle a été envoyée à la demande de '.$senderName.'.'])
