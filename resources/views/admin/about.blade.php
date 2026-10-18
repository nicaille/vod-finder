@extends('pages.layout')
@section('title', 'Administration')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Page À propos</h1><p>Gérez la présentation, les conditions d’utilisation et la confidentialité.</p></section>
@include('partials.admin-navigation')
<form class="vod-social-panel vod-about-editor" action="{{ route('admin.about.update') }}" method="POST">@csrf @method('PUT')
<label for="about-title">Titre de la page<input id="about-title" name="title" value="{{ old('title', $page->title) }}" maxlength="150" required></label>@error('title')<p role="alert">{{ $message }}</p>@enderror
<label for="about-body">Présentation<textarea id="about-body" name="body" rows="22" maxlength="20000" required aria-describedby="about-format">{{ old('body', $page->body) }}</textarea></label>
<p id="about-format" class="vod-social-muted">Utilisez ## pour un titre, **texte** pour du gras et [texte](https://…) pour un lien. Le HTML n’est pas affiché. Les mentions des fournisseurs restent visibles en bas de la page.</p>@error('body')<p role="alert">{{ $message }}</p>@enderror
@foreach (['terms_body' => 'Conditions d’utilisation', 'privacy_body' => 'Règles de confidentialité'] as $field => $label)
<label for="about-{{ $field }}">{{ $label }}<textarea id="about-{{ $field }}" name="{{ $field }}" rows="18" maxlength="20000" required aria-describedby="about-format">{{ old($field, $page->{$field}) }}</textarea></label>
@error($field)<p role="alert">{{ $message }}</p>@enderror
@endforeach
<p class="vod-social-muted">Vérifiez les coordonnées du responsable, les prestataires et les durées de conservation avant publication. Pensez à mettre à jour la date de version lors d’une modification.</p>
<div class="vod-social-actions"><button type="submit">Enregistrer et publier</button><a class="vod-social-button" href="{{ route('about.show') }}">Voir la page publiée</a></div></form>
@endsection
