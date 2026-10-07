@extends('pages.layout')
@section('title', 'Notifications e-mail')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Notifications e-mail</h1><p>Configurez Brevo pour envoyer les alertes d’épisodes, les demandes de contact et les recommandations.</p></section>
@include('partials.admin-navigation')
<form class="vod-social-panel vod-about-editor" action="{{ route('admin.notification-mail.update') }}" method="POST">@csrf @method('PUT')
<input type="hidden" name="brevo_enabled" value="0">
<label class="vod-mail-toggle"><input type="checkbox" name="brevo_enabled" value="1" @checked(old('brevo_enabled', $enabled))> Utiliser l’API Brevo pour les notifications e-mail</label>
<p class="vod-social-muted">Lorsque Brevo est désactivé, la configuration e-mail habituelle du serveur est utilisée. Les choix e-mail des utilisateurs et la vérification de leur adresse restent nécessaires. Le lien de confirmation d’adresse peut également être envoyé par Brevo.</p>
@error('brevo_enabled')<p role="alert">{{ $message }}</p>@enderror
<label for="brevo-api-key">Clé API Brevo<input id="brevo-api-key" type="password" name="brevo_api_key" maxlength="1000" autocomplete="new-password" aria-describedby="brevo-key-help"></label>
<p id="brevo-key-help" class="vod-social-muted">{{ $keyConfigured ? 'Une clé est enregistrée. Laissez ce champ vide pour la conserver, ou saisissez une nouvelle clé pour la remplacer.' : 'Créez une clé API dans Brevo, puis renseignez-la ici.' }} La clé est conservée chiffrée et ne sera pas réaffichée.</p>
@error('brevo_api_key')<p role="alert">{{ $message }}</p>@enderror
<label for="brevo-sender-email">Adresse de l’expéditeur<input id="brevo-sender-email" type="email" name="sender_email" value="{{ old('sender_email', $senderEmail) }}" maxlength="255" autocomplete="email"></label>
@error('sender_email')<p role="alert">{{ $message }}</p>@enderror
<label for="brevo-sender-name">Nom de l’expéditeur<input id="brevo-sender-name" name="sender_name" value="{{ old('sender_name', $senderName) }}" maxlength="100"></label>
@error('sender_name')<p role="alert">{{ $message }}</p>@enderror
<p class="vod-social-muted">L’expéditeur ou son domaine doit être validé dans Brevo. Enregistrez les paramètres avant d’envoyer le test. L’activation s’applique aux prochains passages du planificateur existant.</p>
<div class="vod-social-actions"><button type="submit">Enregistrer</button><a href="https://app.brevo.com/" target="_blank" rel="noopener" class="vod-social-button">Ouvrir Brevo</a></div>
</form>
<section class="vod-social-panel vod-about-editor"><h2>Tester la configuration enregistrée</h2><p>Ce bouton envoie un véritable e-mail à votre adresse d’administrateur : <strong>{{ auth()->user()->email }}</strong>. Il fonctionne aussi avant l’activation de Brevo pour les utilisateurs.</p>
@error('brevo_test')<p role="alert">{{ $message }}</p>@enderror
<form action="{{ route('admin.notification-mail.test') }}" method="POST">@csrf<button type="submit" class="vod-social-button" @disabled(!$keyConfigured || !$senderEmail || !$senderName)>Envoyer un e-mail de test à mon adresse</button></form></section>
@endsection
