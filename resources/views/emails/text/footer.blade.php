@if(!empty($notificationReason))
{!! $notificationReason !!}
@endif
@if(!empty($showPreferences))
Modifier mes préférences ou désactiver les notifications par e-mail :
{!! route('account.edit') !!}#notifications
@endif

VOD Finder
Tes films, tes séries et tes recommandations.
{!! url('/') !!}
