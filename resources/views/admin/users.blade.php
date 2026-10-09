@extends('pages.layout')
@section('title', 'Administrateurs')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Gérer les administrateurs</h1><p>Ces personnes peuvent consulter les données des utilisateurs et les logs, gérer les réglages du site et attribuer des droits d’administration.</p></section>
@include('partials.admin-navigation')
<form method="POST" action="{{ route('admin.users.store') }}" class="vod-social-panel vod-about-editor">@csrf
<h2>Ajouter un administrateur</h2><label for="admin-email">Adresse e-mail d’un compte existant<input type="email" name="email" id="admin-email" value="{{ old('email') }}" maxlength="255" required autocomplete="off"></label>
<p class="vod-social-muted">La personne doit déjà posséder un compte VOD Finder. Elle aura les mêmes droits d’administration que vous.</p>@error('email')<p role="alert">{{ $message }}</p>@enderror
<button type="submit">Accorder les droits</button></form>
<section class="vod-social-panel vod-about-editor"><h2>Administrateurs actuels</h2>@error('administrator')<p role="alert">{{ $message }}</p>@enderror
@foreach($administrators as $administrator)<div class="vod-contact-row"><div><strong>{{ $administrator->nickname ?: $administrator->name }}</strong><p class="vod-social-muted">{{ $administrator->email }} @if($administrator->id === auth()->id()) · Vous @endif</p></div>
@if($administrators->count() > 1)<form method="POST" action="{{ route('admin.users.destroy', $administrator) }}">@csrf @method('DELETE')<button type="submit">{{ $administrator->id === auth()->id() ? 'Retirer mes droits' : 'Retirer les droits' }}</button></form>@else<span class="vod-social-muted">Dernier administrateur : droits conservés</span>@endif</div>@endforeach
</section>
@endsection
