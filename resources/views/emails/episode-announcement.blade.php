<!doctype html>
<html lang="fr"><body>
<h1>{{ $alert->episode->series->name }} · {{ $alert->episode->code }}</h1>
<p>{{ $alert->episode->name }}</p>
<p>{{ $alert->episode->broadcast_label }}.</p>
<p>Cette date peut changer et ne confirme pas une disponibilité sur une plateforme en France.</p>
<p><a href="{{ route('series.index') }}">Voir mes séries suivies</a></p>
@include('emails.partials.footer', ['showPreferences' => true, 'notificationReason' => 'Tu reçois cet e-mail parce que tu suis cette série et as activé les alertes par e-mail dans ton compte.'])
</body></html>
