<!doctype html>
<html lang="fr"><body>
<h1>{{ $alert->episode->series->name }} · {{ $alert->episode->code }}</h1>
<p>{{ $alert->episode->name }}</p>
<p>{{ $alert->episode->broadcast_label }}.</p>
<p>Cette date peut changer et ne confirme pas une disponibilité sur une plateforme en France.</p>
<p><a href="{{ route('series.index') }}">Voir mes séries suivies</a></p>
<p><a href="{{ route('account.edit') }}">Modifier mes préférences de notification</a></p>
</body></html>
