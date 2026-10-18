<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Finaliser mon compte · VOD Finder</title>@include('partials.app-head')</head>
<body class="bg-slate-900 text-slate-100 min-h-screen"><main class="vod-oauth-signup">
    <a class="text-sm underline" href="{{ route('login') }}">Retour à la connexion</a>
    <h1 class="text-2xl font-semibold mt-4">Bienvenue sur VOD Finder</h1>
    <p class="text-slate-300 mt-3">Tu continues avec {{ \App\Services\SocialLogin::PROVIDERS[$identity['provider']] }}. Confirme tes informations ; tu choisiras ensuite tes abonnements.</p>
    @if($errors->any())<div class="vod-social-notice mt-4" role="alert">{{ $errors->first() }}</div>@endif
    <form class="vod-social-panel" method="POST" action="{{ route('oauth.register') }}">@csrf
        <label for="oauth-first-name">Prénom</label><input id="oauth-first-name" name="first_name" maxlength="80" required autocomplete="given-name" value="{{ old('first_name', $identity['first_name']) }}">
        <label for="oauth-last-name">Nom</label><input id="oauth-last-name" name="last_name" maxlength="80" required autocomplete="family-name" value="{{ old('last_name', $identity['last_name']) }}">
        <label for="oauth-nickname">Pseudo (facultatif)</label><input id="oauth-nickname" name="nickname" maxlength="80" autocomplete="nickname" value="{{ old('nickname') }}" aria-describedby="oauth-nickname-help">
        <p id="oauth-nickname-help" class="text-sm text-slate-400 mt-2">Ton pseudo doit être disponible. Ton nom n’est pas partagé dans l’annuaire par défaut.</p>
        <label for="oauth-email">Adresse e-mail</label><input id="oauth-email" type="email" name="email" maxlength="255" autocomplete="email" required value="{{ old('email', $identity['email']) }}" @readonly($identity['email_verified'])>
        @unless($identity['email_verified'])<p class="text-sm text-slate-400 mt-2">Cette adresse devra être confirmée avant de recevoir des alertes par e-mail.</p>@endunless
        <fieldset class="mt-5"><legend class="font-semibold">Tes notifications</legend>
            <label><input type="checkbox" name="notify_opt_in" value="1" @checked(old('notify_opt_in', false))> Activer les notifications</label>
            <label><input type="checkbox" name="notify_email" value="1" @checked(old('notify_email', false))> E-mails</label>
            <label><input type="checkbox" name="notify_web" value="1" @checked(old('notify_web', false))> Notifications navigateur / mobile</label>
            <p class="text-sm text-slate-400">Les notifications du navigateur nécessitent aussi ton autorisation sur chaque appareil.</p>
        </fieldset>
        <button class="w-full mt-5" type="submit">Créer mon compte</button>
    </form>
</main>@include('partials.footer')</body></html>
