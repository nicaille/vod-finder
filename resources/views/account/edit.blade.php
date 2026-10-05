<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon compte - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-10">

    @include('partials.main-navigation')

    <h1 class="text-xl font-semibold mb-4">Mon compte</h1>

    @if (session('status'))
        <div class="mb-4 rounded border border-emerald-700 bg-emerald-900/30 px-4 py-3 text-sm text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-700 bg-red-900/30 px-4 py-3 text-sm text-red-200">
            <div class="font-semibold mb-1">Il y a des erreurs dans le formulaire :</div>
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('account.update') }}" method="POST"
          class="bg-slate-800 border border-slate-700 rounded-lg p-5 space-y-6">
        @csrf
        @method('PUT')

        {{-- Infos perso --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm mb-1">Prénom</label>
                <input type="text" name="first_name"
                       value="{{ old('first_name', $user->first_name) }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm mb-1">Nom</label>
                <input type="text" name="last_name"
                       value="{{ old('last_name', $user->last_name) }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm mb-1">Email</label>
                <input type="email" name="email"
                       value="{{ old('email', $user->email) }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm mb-1">Surnom (nickname)</label>
                <input type="text" name="nickname"
                       value="{{ old('nickname', $user->nickname) }}"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm"
                       placeholder="Ex: Maverick">
            </div>
        </div>

        {{-- Notifications globales --}}
        <div class="border-t border-slate-700 pt-4">
            <div class="flex items-start gap-3">
                {{-- hidden pour garantir une valeur envoyée --}}
                <input type="hidden" name="notify_opt_in" value="0">

                <input id="notify_opt_in"
                       type="checkbox"
                       name="notify_opt_in"
                       value="1"
                       class="mt-1 rounded border-slate-600"
                       {{ old('notify_opt_in', $user->notify_opt_in) ? 'checked' : '' }}>

                <label for="notify_opt_in" class="text-sm">
                    <div class="font-semibold">Notifications générales</div>
                    <div class="text-xs text-slate-400">
                        Autoriser les alertes de diffusion des séries suivies. Chaque série possède aussi son propre réglage d’alerte.
                    </div>
                </label>
            </div>
        </div>

        {{-- Plateformes --}}
        <div class="border-t border-slate-700 pt-4 space-y-3">
            <div>
                <div class="font-semibold">Mes abonnements</div>
                <div class="text-xs text-slate-400">
                    Coche tes plateformes. Pour chaque plateforme, tu peux indiquer "via" (ex: Apple TV+ via Canal+)
                    et si tu veux recevoir des notifications spécifiques.
                </div>
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
            
            @include('partials.platform-subscriptions')
        </div>

        <button type="submit"
                class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold">
            Enregistrer
        </button>
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
