<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Séries suivies - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-10">
    @include('partials.main-navigation')
    <h1 class="text-xl font-semibold mb-3">Séries suivies</h1>
    <p class="text-sm text-slate-300 mb-6">Suis une série depuis sa fiche pour retrouver ses prochains épisodes ici. Les dates de diffusion annoncées peuvent changer et ne confirment pas la disponibilité en France.</p>

    @if(session('status'))
        <p role="status" class="mb-4 rounded border border-emerald-700 p-3 text-emerald-200">{{ session('status') }}</p>
    @endif
    @if($errors->any())
        <p role="alert" class="mb-4 text-red-300">{{ $errors->first() }}</p>
    @endif
    @unless(auth()->user()->notify_opt_in)
        <p class="mb-4 rounded border border-amber-700 p-3 text-amber-200 text-sm">Tes notifications sont désactivées dans <a href="{{ route('account.edit') }}" class="underline">Mon compte</a>. Le calendrier reste disponible.</p>
    @endunless

    <section aria-labelledby="follow-heading" class="mb-8">
        <h2 id="follow-heading" class="font-semibold mb-3">Mon suivi</h2>
        <div class="space-y-3">
        @forelse($follows as $follow)
            <article class="bg-slate-800 border border-slate-700 rounded p-4">
                <h3 class="font-semibold">{{ $follow->series->name }}</h3>
                <p class="text-xs text-slate-400 mt-1">{{ $follow->series->synced_at ? 'Calendrier actualisé le '.$follow->series->synced_at->timezone('Europe/Paris')->format('d/m/Y à H:i') : 'Calendrier en attente de synchronisation' }}</p>
                <div class="flex flex-wrap items-center gap-3 mt-3 text-xs">
                    <form action="{{ route('series.update', $follow) }}" method="POST">
                        @csrf @method('PATCH')
                        <input type="hidden" name="alerts_enabled" value="{{ $follow->alerts_enabled ? '0' : '1' }}">
                        <button class="rounded border border-indigo-400 px-3 py-2" type="submit">{{ $follow->alerts_enabled ? 'Désactiver les alertes' : 'Activer les alertes' }}</button>
                    </form>
                    <form action="{{ route('series.destroy', $follow) }}" method="POST">
                        @csrf @method('DELETE')
                        <button class="rounded border border-slate-500 px-3 py-2" type="submit">Ne plus suivre</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-400">Tu ne suis aucune série. <a href="{{ route('search.index') }}" class="text-indigo-300 underline">Rechercher une série</a></p>
        @endforelse
        </div>
    </section>

    <section aria-labelledby="calendar-heading" class="mb-8">
        <h2 id="calendar-heading" class="font-semibold mb-3">Prochains épisodes</h2>
        <p class="text-xs text-slate-400 mb-3">Dates annoncées par TMDb, sans heure de sortie connue. Les séries sans date annoncée restent dans ton suivi.</p>
        <div class="space-y-2">
        @forelse($episodes as $episode)
            <article class="bg-slate-800 rounded p-3 flex flex-wrap justify-between gap-2 text-sm">
                <div><span class="font-semibold">{{ $episode->series->name }}</span> · {{ $episode->code }}<br><span class="text-slate-300">{{ $episode->name }}</span></div>
                <time datetime="{{ $episode->air_date->toDateString() }}" class="text-indigo-300">{{ $episode->air_date->format('d/m/Y') }}</time>
            </article>
        @empty
            <p class="text-sm text-slate-400">Aucune date à venir annoncée pour tes séries suivies.</p>
        @endforelse
        </div>
        <div class="mt-3">{{ $episodes->withQueryString()->links() }}</div>
    </section>

    <section aria-labelledby="alerts-heading">
        <h2 id="alerts-heading" class="font-semibold mb-3">Alertes de diffusion</h2>
        <div class="space-y-3">
        @forelse($alerts as $alert)
            <article class="rounded border {{ $alert->read_at ? 'border-slate-700' : 'border-indigo-400' }} p-3 text-sm">
                <p><strong>{{ $alert->episode->series->name }} · {{ $alert->episode->code }}</strong> — {{ $alert->episode->name }}</p>
                <p class="text-slate-300">Diffusion annoncée le {{ $alert->episode->air_date->format('d/m/Y') }}.</p>
                @unless($alert->read_at)
                    <form action="{{ route('series.alerts.read', $alert) }}" method="POST" class="mt-2">
                        @csrf @method('PATCH')
                        <button type="submit" class="text-indigo-300 underline">Marquer comme lue</button>
                    </form>
                @else
                    <p class="text-xs text-slate-400 mt-2">Lue</p>
                @endunless
            </article>
        @empty
            <p class="text-sm text-slate-400">Aucune alerte de diffusion pour le moment.</p>
        @endforelse
        </div>
        <div class="mt-3">{{ $alerts->withQueryString()->links() }}</div>
    </section>
    <p class="text-xs text-slate-400 mt-6">Calendrier fourni par <a href="https://www.themoviedb.org/" class="underline">TMDb</a>. Ce produit utilise l’API TMDb mais n’est ni approuvé ni certifié par TMDb.</p>
</div>
</body>
</html>
