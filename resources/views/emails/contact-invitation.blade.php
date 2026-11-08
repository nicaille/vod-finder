<!doctype html><html lang="fr"><body>
<h1>{{ $senderName }} t’invite sur VOD Finder</h1>
<p>Échangez des recommandations de films, séries et personnes à découvrir.</p>
<p><a href="{{ $invitationUrl }}">Découvrir et accepter l’invitation</a></p>
<p>Connecte-toi ou crée ton compte, puis accepte la mise en relation. Aucune relation n’est créée simplement en ouvrant ce message.</p>
<p>Ce lien est valable jusqu’au {{ $expiry }} et permet une seule acceptation.</p>
<p>Tu peux ignorer cet e-mail si tu ne souhaites pas donner suite. Aucun abonnement aux notifications n’est activé par cette invitation.</p>
@include('emails.partials.footer', ['notificationReason' => 'Cette invitation ponctuelle a été envoyée à la demande de '.$senderName.'.'])
</body></html>
