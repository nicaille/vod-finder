@php $socialProviders = \App\Services\SocialLogin::available(); @endphp
@if($socialProviders)
<div class="vod-social-login" aria-label="Autres méthodes de connexion">
    @foreach($socialProviders as $provider=>$label)
        <a class="vod-provider-login" data-oauth-provider="{{ $provider }}" href="{{ route('oauth.redirect', $provider) }}">Continuer avec {{ $label }}</a>
    @endforeach
    <p class="vod-login-separator">ou avec ton adresse e-mail</p>
</div>
@endif
