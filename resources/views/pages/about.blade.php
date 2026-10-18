@extends('pages.layout')
@section('title', $page->title)
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Notre projet</span><h1>{{ $page->title }}</h1><p>Vos envies, vos plateformes, vos découvertes.</p></section>
<nav class="vod-social-actions" aria-label="Sommaire de la page À propos">
<a href="#presentation">Présentation</a><a href="#conditions-utilisation">Conditions d’utilisation</a><a href="#confidentialite">Confidentialité</a>
</nav>
<article class="vod-about-content">
<section id="presentation" aria-label="Présentation">{!! $html !!}</section>
<section id="conditions-utilisation" aria-labelledby="terms-title"><h2 id="terms-title">Conditions d’utilisation de l’application</h2>{!! $termsHtml !!}</section>
<section id="confidentialite" aria-labelledby="privacy-title"><h2 id="privacy-title">Règles de confidentialité de l’application</h2>{!! $privacyHtml !!}</section>
@include('partials.data-credits')
</article>
@can('manage-site')<p class="vod-social-actions"><a class="vod-social-button" href="{{ route('admin.index') }}">Modifier cette page</a></p>@endcan
@endsection
