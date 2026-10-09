<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.app-head')
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="min-h-screen flex items-center justify-center px-4 py-10">

    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <div class="text-2xl font-bold tracking-tight">VOD Finder</div>
            <div class="text-sm text-slate-400 mt-1">Connecte-toi pour accéder à ta playlist et tes listes.</div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-lg p-6 shadow">
            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-700 bg-emerald-900/30 px-3 py-2 text-sm text-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded border border-red-700 bg-red-900/30 px-3 py-2 text-sm text-red-200">
                    <div class="font-semibold mb-1">Oups</div>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm mb-1">Email</label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="username"
                           class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password" class="block text-sm mb-1">Mot de passe</label>
                    <input id="password"
                           name="password"
                           type="password"
                           required
                           autocomplete="current-password"
                           class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                        <input id="remember_me"
                               name="remember"
                               type="checkbox"
                               class="rounded border-slate-600 bg-slate-900">
                        <span>Se souvenir de moi</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-sm text-indigo-300 hover:text-indigo-200 hover:underline">
                            Mot de passe oublié ?
                        </a>
                    @endif
                </div>

                <button type="submit"
                        class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold">
                    Se connecter
                </button>

                <div class="text-center text-sm text-slate-400">
                    Pas de compte ?
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="text-indigo-300 hover:text-indigo-200 hover:underline">
                            Créer un compte
                        </a>
                    @else
                        <span class="text-slate-500">Création de compte indisponible</span>
                    @endif
                </div>
            </form>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('search.index') }}"
               class="text-sm text-slate-400 hover:text-slate-200 hover:underline">
                Revenir à la recherche
            </a>
        </div>
    </div>

</div>
</body>
</html>
