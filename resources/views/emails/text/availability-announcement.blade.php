Disponible maintenant : {!! $alert->title !!}

Ce titre de ta playlist est désormais annoncé comme inclus sur {!! $alert->providerLabel() !!}.
Disponibilité vérifiée le {!! $alert->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') !!} pour la France. Le catalogue peut évoluer ; pour une série, tous les épisodes ne sont pas nécessairement inclus.

Voir la fiche : {!! $alert->url() !!}

@include('emails.text.footer', ['showPreferences'=>true,'notificationReason'=>'Tu reçois cet e-mail parce que ce titre est dans ta playlist et que tu as activé les alertes de disponibilité par e-mail.'])
