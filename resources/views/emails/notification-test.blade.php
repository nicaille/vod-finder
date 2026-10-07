<!doctype html>
<html lang="fr"><body>
<h1>Test des notifications VOD Finder</h1>
<p>Ce message a été envoyé à ta demande depuis l’administration pour vérifier la configuration des notifications e-mail.</p>
<p>Ce test confirme l’envoi ; tu peux maintenant vérifier la réception et le classement du message dans ta messagerie.</p>
<p><a href="{{ route('admin.notification-mail.edit') }}">Gérer la configuration des notifications e-mail</a></p>
@include('emails.partials.footer')
</body></html>
