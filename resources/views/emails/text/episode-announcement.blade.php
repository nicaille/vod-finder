{!! $alert->episode->series->name !!} · {!! $alert->episode->code !!}

{!! $alert->episode->name !!}
{!! $alert->episode->broadcast_label !!}.

Cette date peut changer et ne confirme pas une disponibilité sur une plateforme en France.

Voir mes séries suivies : {!! route('series.index') !!}

@include('emails.text.footer', ['showPreferences' => true, 'notificationReason' => 'Tu reçois cet e-mail parce que tu suis cette série et as activé les alertes par e-mail dans ton compte.'])
