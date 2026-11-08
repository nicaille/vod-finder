@extends('social.layout')
@section('title', 'Invitation')
@section('content')
<section class="vod-social-panel">
@if(!$invitation)
<h1>Invitation indisponible</h1><p>Ce lien a expiré, a été révoqué ou a déjà été utilisé. Demande un nouveau lien à la personne qui t’a invité.</p>
@else
<h1>{{ $invitation->sender->socialName() }} t’invite sur VOD Finder</h1>
<p>Échangez des recommandations de films, séries et personnes. La mise en relation aura lieu uniquement après ton acceptation.</p>
<p class="vod-social-muted">Invitation valable jusqu’au {{ $invitation->expires_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }}. Ce lien permet une seule acceptation ; garde-le privé.</p>
@guest
<div class="vod-social-actions"><a class="vod-social-button" href="{{ route('login') }}">J’ai un compte : me connecter</a><a class="vod-social-button" href="{{ route('register') }}">Créer mon compte</a></div>
@else
@if(auth()->id() === $invitation->sender_id)
<p>C’est ton invitation. Partage-la avec ton proche pour qu’il puisse l’accepter.</p>
@else
<form method="POST" action="{{ route('contact-invitations.accept', $token) }}">@csrf<button>Accepter l’invitation de {{ $invitation->sender->socialName() }}</button></form>
<form method="POST" action="{{ route('contact-invitations.dismiss') }}">@csrf<button>Continuer sans accepter</button></form>
@endif
@endguest
@endif
</section>
@endsection
