@php $availableLogins=\App\Services\SocialLogin::available(); $linkedLogins=$user->socialAccounts->keyBy('provider'); @endphp
@if($availableLogins || $linkedLogins->isNotEmpty())
<section class="vod-social-panel" id="connections" aria-labelledby="linked-accounts-title">
    <h2 id="linked-accounts-title">Méthodes de connexion</h2>
    <p>Associe un compte Google, Microsoft ou Facebook pour te connecter plus facilement. Une confirmation de ton mot de passe peut être demandée.</p>
    @unless($user->has_local_password)
        <p class="mt-3 text-sm">Ton compte utilise une connexion sociale. Pour ajouter une autre méthode ou modifier ton adresse, <a class="underline" href="{{ route('password.request') }}">définis un mot de passe par e-mail</a>.</p>
    @endunless
    <div class="vod-linked-accounts">
    @foreach(\App\Services\SocialLogin::PROVIDERS as $provider=>$label)
        @if(isset($availableLogins[$provider]) || isset($linkedLogins[$provider]))
        <div class="vod-linked-account"><span>{{ $label }} · {{ isset($linkedLogins[$provider]) ? 'Associé' : 'Non associé' }}</span>
            @if(isset($linkedLogins[$provider]))
                @if($user->has_local_password || $linkedLogins->count() > 1)
                <form method="POST" action="{{ route('oauth.unlink', $provider) }}">@csrf @method('DELETE')<button type="submit">Retirer {{ $label }}</button></form>
                @else<span class="text-sm text-slate-400">Ta méthode de connexion actuelle</span>@endif
            @elseif($user->has_local_password)
                <form method="POST" action="{{ route('oauth.link', $provider) }}">@csrf<button type="submit">Associer {{ $label }}</button></form>
            @endif
        </div>
        @endif
    @endforeach
    </div>
</section>
@endif
