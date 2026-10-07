<hr>
@if(!empty($notificationReason))
<p>{{ $notificationReason }}</p>
@endif
@if(!empty($showPreferences))
<p><a href="{{ route('account.edit') }}#notifications">Modifier mes préférences ou désactiver les notifications par e-mail</a></p>
@endif
<p>VOD Finder<br>Tes films, tes séries et tes recommandations.<br>
<a href="{{ url('/') }}">Ouvrir VOD Finder</a></p>
