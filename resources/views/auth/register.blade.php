<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen">

<div class="max-w-xl mx-auto px-4 py-10">
    <div class="mb-6">
        <a href="{{ route('search.index') }}" class="text-sm text-slate-300 hover:text-slate-100">Retour</a>
        <h1 class="text-2xl font-bold mt-2">Créer un compte</h1>
        <p class="text-sm text-slate-400 mt-1">Renseigne tes infos et tes abonnements.</p>
    </div>

    @if ($errors->any())
        <div class="bg-red-900/30 border border-red-700 rounded p-3 text-sm mb-4">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-5 bg-slate-800 border border-slate-700 rounded-lg p-5">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Prénom</label>
                <input name="first_name" value="{{ old('first_name') }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-sm mb-1">Nom</label>
                <input name="last_name" value="{{ old('last_name') }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm" required>
            </div>
        </div>

        <div>
            <label class="block text-sm mb-1">Surnom (nickname)</label>
            <input name="nickname" value="{{ old('nickname') }}"
                   class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm"
                   placeholder="Ex: Maverick">
            <p class="text-xs text-slate-400 mt-1">Optionnel, visible dans l'app.</p>
        </div>

        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm" required>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm mb-1">Mot de passe</label>
                <input type="password" name="password"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-sm mb-1">Confirmation</label>
                <input type="password" name="password_confirmation"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm" required>
            </div>
        </div>

        <div class="border-t border-slate-700 pt-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="notify_opt_in" value="0">
                <input type="checkbox" name="notify_opt_in" value="1" {{ old('notify_opt_in', true) ? 'checked' : '' }}>
                <span>Autoriser les notifications</span>
            </label>
        </div>

        <div class="border-t border-slate-700 pt-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Mes abonnements</h2>
                <span class="text-xs text-slate-400">Tu pourras modifier dans "Mon compte"</span>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        id="platforms-check-all"
                        class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-xs font-semibold">
                    Tout cocher
                </button>

                <button type="button"
                        id="platforms-uncheck-all"
                        class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-xs font-semibold">
                    Tout décocher
                </button>
            </div>


            @include('partials.platform-subscriptions', ['subscriptions' => collect()])
        </div>

        <button type="submit" class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold">
            Créer mon compte
        </button>

        <p class="text-xs text-slate-400">
            Déjà un compte ?
            <a class="text-indigo-300 hover:text-indigo-200" href="{{ route('login') }}">Se connecter</a>
        </p>
    </form>
</div>
<script>
    function togglePlatformOptions(platformId, isChecked) {
        const options = document.querySelector('[data-platform-options="' + platformId + '"]');
        if (!options) return;
        options.classList.toggle('hidden', !isChecked);
    }

    // checkbox -> show/hide options
    document.addEventListener('change', function (e) {
        const cb = e.target.closest('.platform-checkbox');
        if (!cb) return;
        togglePlatformOptions(cb.value, cb.checked);
    });

    // Tout cocher / tout décocher
    document.getElementById('platforms-check-all')?.addEventListener('click', () => {
        document.querySelectorAll('.platform-checkbox').forEach(cb => {
            cb.checked = true;
            togglePlatformOptions(cb.value, true);
        });
    });

    document.getElementById('platforms-uncheck-all')?.addEventListener('click', () => {
        document.querySelectorAll('.platform-checkbox').forEach(cb => {
            cb.checked = false;
            togglePlatformOptions(cb.value, false);
        });
    });
</script>
</body>
</html>