@extends('pages.layout')
@section('title', 'Tests TV')
@section('content')
<section class="vod-intro">
    <span class="vod-eyebrow">Administration</span>
    <h1>Tester les liens sur la TV</h1>
    <p>Contenu de référence : <strong>Tyler Rake (Extraction, 2020)</strong> sur Netflix. Identifiant Netflix : <strong>81098494</strong>.</p>
</section>
@include('partials.admin-navigation')

<section class="vod-social-panel">
    <h2>Avant de commencer</h2>
    <p>Ouvre cette page dans le navigateur de ta Samsung The Frame ou The Frame Pro, avec ton compte administrateur VOD Finder. Installe Netflix et connecte-toi dans l’application TV avant les essais.</p>
    <p>Teste les liens dans l’ordre. Après chaque essai, reviens ici avec la touche Retour ou en rouvrant cette page. Si une lecture démarre, arrête-la avant le test suivant.</p>
    <p class="vod-social-muted">Aucun lien n’est lancé automatiquement. Les liens d’application sont expérimentaux : leur prise en charge sous Tizen n’est pas confirmée. Une absence de réaction ou un message « protocole non pris en charge » est un résultat utile.</p>
</section>

@php
    $tvLinks = [
        ['title' => '1. Accueil Netflix — HTTPS', 'url' => 'https://www.netflix.com/', 'description' => 'Point de comparaison : ouvre-t-il le site Netflix ou l’application de la TV ?', 'newTab' => false],
        ['title' => '2. Fiche Tyler Rake — HTTPS', 'url' => 'https://www.netflix.com/title/81098494', 'description' => 'Lien classique vers la fiche du film, dans le même onglet.', 'newTab' => false],
        ['title' => '3. Même fiche — nouvel onglet', 'url' => 'https://www.netflix.com/title/81098494', 'description' => 'Même destination, en demandant un nouvel onglet. Le navigateur TV peut ignorer cette demande.', 'newTab' => true],
        ['title' => '4. Lecture Tyler Rake — HTTPS', 'url' => 'https://www.netflix.com/watch/81098494', 'description' => 'Lien vers le lecteur Netflix. Il peut demander une connexion, rester dans le navigateur ou démarrer une lecture ; il ne garantit pas l’ouverture de l’application.', 'newTab' => false],
        ['title' => '5. Schéma netflix:// — expérimental', 'url' => 'netflix://title/81098494', 'description' => 'Essai d’un protocole d’application. Ce format n’est pas une API Samsung validée et peut être refusé ou ne rien faire.', 'newTab' => false],
        ['title' => '6. Schéma nflx:// — expérimental', 'url' => 'nflx://www.netflix.com/title/81098494', 'description' => 'Autre essai de protocole d’application, sans garantie de prise en charge par le navigateur Samsung.', 'newTab' => false],
    ];
@endphp

@foreach($tvLinks as $tvLink)
<section class="vod-social-panel">
    <h2>{{ $tvLink['title'] }}</h2>
    <p>{{ $tvLink['description'] }}</p>
    <p class="vod-admin-row"><code>{{ $tvLink['url'] }}</code></p>
    <a class="vod-action-pill" href="{{ $tvLink['url'] }}" @if($tvLink['newTab']) target="_blank" @endif rel="noopener noreferrer">Lancer le test {{ $loop->iteration }}@if($tvLink['newTab']) (nouvel onglet)@endif</a>
</section>
@endforeach

<section class="vod-social-panel">
    <h2>Résultats à me transmettre</h2>
    <p>Indique la référence et l’année de la TV, ainsi que sa version logicielle (Paramètres → Assistance → À propos de cette TV, selon le modèle).</p>
    <p>Pour chaque numéro de test, note : aucune réaction / message d’erreur / site web Netflix / application Netflix ; accueil ou fiche de Tyler Rake ; lecture démarrée ou non. Signale aussi un éventuel choix de profil ou une demande de connexion.</p>
    <p class="vod-social-muted">Cette page ne peut pas détecter de façon fiable quelle application la TV a ouverte. Les observations restent manuelles et ne sont pas enregistrées.</p>
</section>
@endsection
