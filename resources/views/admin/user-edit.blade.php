@extends('pages.layout')
@section('title', 'Modifier un compte utilisateur')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration · utilisateur #{{ $user->id }}</span><h1>Modifier le compte</h1><p>{{ $user->nickname ?: $user->name }} · {{ $user->email }}</p></section>
@include('partials.admin-navigation')
<form method="POST" action="{{ route('admin.user-data.update', $user) }}" class="vod-social-panel vod-admin-user-editor">
@csrf @method('PATCH')
@if($errors->any())<div role="alert"><p>Vérifie les champs indiqués avant d’enregistrer.</p></div>@endif
<fieldset><legend>Informations du compte</legend><div class="vod-admin-user-fields">
@foreach(['first_name'=>'Prénom','last_name'=>'Nom','nickname'=>'Pseudo','email'=>'Adresse e-mail'] as $field=>$label)
<div class="vod-admin-user-field"><label for="user-{{ $field }}">{{ $label }}<input id="user-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field, $user->{$field}) }}" maxlength="{{ $field === 'email' ? 255 : 80 }}" @required($field !== 'nickname') @if($errors->has($field)) aria-invalid="true" aria-describedby="error-{{ $field }}" @endif></label>
@error($field)<span id="error-{{ $field }}" role="alert">{{ $message }}</span>@enderror</div>
@endforeach
</div><p class="vod-social-muted">Le pseudo et l’e-mail doivent être disponibles. Modifier l’e-mail retire sa confirmation : l’utilisateur devra confirmer sa nouvelle adresse depuis Mon compte.</p>
<p>Adresse {{ $user->hasVerifiedEmail() ? 'confirmée' : 'non confirmée' }} · {{ $user->is_admin ? 'Administrateur' : 'Membre' }}</p></fieldset>
<fieldset><legend>Notifications</legend>
<p class="vod-social-muted">Ces préférences sont enregistrées sur le serveur. L’option générale doit être activée pour utiliser les canaux e-mail et navigateur. Modifie-les en accord avec la personne concernée. L’autorisation du navigateur reste à accorder sur chaque appareil. Les préférences s’appliquent aux prochains passages de livraison.</p>
@foreach(['notify_opt_in'=>'Activer les notifications','notify_email'=>'Recevoir les notifications par e-mail','notify_web'=>'Recevoir les notifications sur les appareils autorisés','notify_platform_updates'=>'Alertes Disponible maintenant'] as $field=>$label)
<input type="hidden" name="{{ $field }}" value="0"><label class="vod-admin-user-check" for="user-{{ $field }}"><input id="user-{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $user->{$field}))>{{ $label }}</label>
@error($field)<p role="alert">{{ $message }}</p>@enderror
@endforeach
<p><strong>{{ $user->push_subscriptions_count }} appareil(s) enregistré(s)</strong>. Cet enregistrement ne garantit pas que l’autorisation est encore active sur l’appareil.</p>
@if(!$user->hasVerifiedEmail())<p class="vod-social-muted">Les alertes e-mail nécessitent une adresse confirmée.</p>@endif
@if($user->push_subscriptions_count === 0)<p class="vod-social-muted">Pour recevoir les notifications navigateur, l’utilisateur doit ouvrir VOD Finder sur son appareil, aller dans Mon compte et autoriser les notifications.</p>@endif
</fieldset>
<fieldset><legend>Visibilité</legend>
@foreach(['directory_visible'=>'Apparaître dans l’annuaire','share_real_name'=>'Partager le prénom et le nom'] as $field=>$label)
<input type="hidden" name="{{ $field }}" value="0"><label class="vod-admin-user-check" for="user-{{ $field }}"><input id="user-{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $user->{$field}))>{{ $label }}</label>
@error($field)<p role="alert">{{ $message }}</p>@enderror
@endforeach
<p class="vod-social-muted">Respecte les choix de partage de l’utilisateur.</p></fieldset>
<p class="vod-social-muted">Les droits d’administration sont gérés dans l’onglet Administrateurs. Le mot de passe n’est pas modifiable dans ce formulaire ; l’utilisateur conserve le parcours de réinitialisation par e-mail.</p>
<div class="vod-social-actions"><button type="submit">Enregistrer les modifications</button><a class="vod-social-button" href="{{ route('admin.user-data.show', $user) }}">Annuler</a></div>
</form>
@endsection
