<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $details['title'] ?? $details['name'] ?? 'Détail' }} - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('partials.app-head')
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="max-w-5xl mx-auto py-6 px-4">
    <a href="{{ route('search.form') }}" class="text-sm text-indigo-300 hover:underline">&larr; Retour à la recherche</a>

    <div class="mt-4 flex flex-col md:flex-row gap-6">
        <div class="w-full md:w-1/3">
            @php
                $poster = $details['poster_path'] ?? null;
                $title  = $details['title'] ?? $details['name'] ?? '';
                $year   = isset($details['release_date'])
                    ? substr($details['release_date'], 0, 4)
                    : (isset($details['first_air_date']) ? substr($details['first_air_date'], 0, 4) : null);
            @endphp

            @if ($poster)
                <img src="https://image.tmdb.org/t/p/w500{{ $poster }}" alt="{{ $title }}" class="w-full rounded-lg shadow">
            @else
                <div class="w-full h-80 flex items-center justify-center bg-slate-700 rounded-lg">Aucune image</div>
            @endif
        </div>

        <div class="flex-1 space-y-4">
            <div>
                <h1 class="text-2xl font-bold">
                    {{ $title }}
                    @if($year)
                        <span class="text-sm text-slate-400">({{ $year }})</span>
                    @endif
                </h1>
                @if (!empty($details['tagline']))
                    <p class="text-sm text-slate-300 mt-1 italic">{{ $details['tagline'] }}</p>
                @endif
            </div>

            @php
                $runtime = $details['runtime'] ?? ($details['episode_run_time'][0] ?? null);
                $genres = $details['genres'] ?? [];
                $vote   = $details['vote_average'] ?? null;
            @endphp

            <div class="text-sm text-slate-200 space-y-1">
                @if($runtime)
                    <div>Durée: {{ $runtime }} min</div>
                @endif
                @if(!empty($genres))
                    <div>Genres:
                        @foreach($genres as $g)
                            <span class="inline-block px-2 py-0.5 rounded-full bg-slate-800 text-xs mr-1">{{ $g['name'] }}</span>
                        @endforeach
                    </div>
                @endif
                @if($vote)
                    <div>Note TMDb: {{ number_format($vote, 1) }}/10</div>
                @endif
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase text-slate-300">Synopsis</h2>
                <p class="text-sm text-slate-200 mt-1">
                    {{ $details['overview'] ?? 'Pas de description disponible.' }}
                </p>
            </div>

            {{-- Bouton Regarder maintenant --}}
            @if($watchNow)
                @php
                    $watchTitle = $title;
                    $slug = $watchNow['slug'] ?? null;
                @endphp
                <div class="mt-2">
                    <a href="#"
                       data-provider-slug="{{ $slug }}"
                       data-title="{{ $watchTitle }}"
                       class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold"
                       id="watch-now-btn">
                        Regarder maintenant
                    </a>
                    <p class="mt-1 text-xs text-slate-400">
                        Meilleure option détectée: {{ $watchNow['name'] ?? '' }}
                        @if(($watchNow['access'] ?? '') === 'flatrate')
                            - Inclus dans votre abonnement
                        @elseif(($watchNow['access'] ?? '') === 'rent')
                            - Disponible en location
                        @elseif(($watchNow['access'] ?? '') === 'buy')
                            - Disponible à l achat
                        @endif
                    </p>
                </div>
            @endif

            {{-- Providers détaillés --}}
            <div class="mt-4">
                <h2 class="text-sm font-semibold uppercase text-slate-300 mb-2">Offres VOD</h2>
                @if(empty($providers))
                    <p class="text-xs text-slate-400">Aucune offre trouvée dans votre sélection de plateformes.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($providers as $p)
                            @php
                                $viaText = $p['via'] === 'canalplus'
                                    ? 'via Canal+'
                                    : ($p['via'] === 'prime' ? 'via Prime Video' : '');
                            @endphp
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 px-2 py-1 rounded-full border text-xs
                                    @if($p['access'] === 'flatrate') bg-emerald-700 border-emerald-500
                                    @elseif($p['access'] === 'rent') bg-amber-700 border-amber-500
                                    @elseif($p['access'] === 'buy') bg-sky-700 border-sky-500
                                    @else bg-slate-700 border-slate-500 @endif
                                "
                                data-provider-slug="{{ $p['slug'] }}"
                                data-title="{{ $title }}"
                                onclick="openProviderUrl(this)"
                            >
                                @if(!empty($p['logo']))
                                    <img src="{{ $p['logo'] }}" alt="{{ $p['name'] }}" class="w-6 h-6 rounded">
                                @endif
                                <div class="flex flex-col leading-tight">
                                    <span>{{ $p['name'] }}</span>
                                    <span class="text-[10px] uppercase">
                                        @if($p['access'] === 'flatrate') Inclus
                                        @elseif($p['access'] === 'rent') Location
                                        @elseif($p['access'] === 'buy') Achat
                                        @else Payant
                                        @endif
                                        @if($viaText) · {{ $viaText }} @endif
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Watchlist localStorage --}}
            <div class="mt-4">
                <button
                    type="button"
                    class="inline-flex items-center px-3 py-1.5 rounded-md border border-slate-600 text-xs"
                    id="watchlist-toggle"
                    data-id="{{ $details['id'] }}"
                    data-type="{{ $type }}"
                    data-title="{{ $title }}"
                >
                    Ajouter à ma watchlist
                </button>
                <p class="mt-1 text-xs text-slate-400" id="watchlist-status"></p>
            </div>
        </div>
    </div>

    {{-- Recos --}}
    @if(!empty($reco))
        <div class="mt-8">
            <h2 class="text-sm font-semibold uppercase text-slate-300 mb-3">Recommandations</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                @foreach(array_slice($reco, 0, 8) as $r)
                    @php
                        $rid    = $r['id'] ?? null;
                        $rtype  = isset($r['name']) ? 'tv' : 'movie';
                        $rtitle = $r['title'] ?? $r['name'] ?? '';
                        $rposter = $r['poster_path'] ?? null;
                    @endphp
                    @if($rid)
                        <a href="{{ route('title.show', ['type' => $rtype, 'id' => $rid]) }}" class="bg-slate-800 rounded-md overflow-hidden hover:bg-slate-700">
                            @if($rposter)
                                <img src="https://image.tmdb.org/t/p/w342{{ $rposter }}" alt="{{ $rtitle }}" class="w-full h-40 object-cover">
                            @else
                                <div class="w-full h-40 flex items-center justify-center bg-slate-700">Aucune image</div>
                            @endif
                            <div class="px-2 py-2">
                                <div class="font-semibold truncate">{{ $rtitle }}</div>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildProviderUrl(title, slug) {
        const encoded = encodeURIComponent(title);
        let target = slug;

        if (slug === 'appletv' || slug === 'paramountplus') {
            target = 'canalplus';
        }
        if (slug === 'hbomax') {
            target = 'prime';
        }

        switch (target) {
            case 'netflix':
                return 'https://www.netflix.com/search?q=' + encoded;
            case 'prime':
                return 'https://www.primevideo.com/search?phrase=' + encoded;
            case 'disneyplus':
                return 'https://www.disneyplus.com/search/' + encoded;
            case 'canalplus':
                return 'https://www.canalplus.com/recherche?search_query=' + encoded;
            case 'appletv':
                return 'https://tv.apple.com/fr/search/' + encoded;
            case 'paramountplus':
                return 'https://www.paramountplus.com/search/' + encoded;
            case 'hbomax':
                return 'https://play.max.com/search?q=' + encoded;
            default:
                return null;
        }
    }

    function openProviderUrl(button) {
        const title = button.getAttribute('data-title');
        const slug  = button.getAttribute('data-provider-slug');
        const url   = buildProviderUrl(title, slug);
        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    }

    // Watch now
    const watchNowBtn = document.getElementById('watch-now-btn');
    if (watchNowBtn) {
        watchNowBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const slug  = this.getAttribute('data-provider-slug');
            const title = this.getAttribute('data-title');
            const url   = buildProviderUrl(title, slug);
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
        });
    }

    // Watchlist localStorage
    (function initWatchlist() {
        const btn = document.getElementById('watchlist-toggle');
        const status = document.getElementById('watchlist-status');
        if (!btn || !status) return;

        const id = btn.getAttribute('data-id');
        const type = btn.getAttribute('data-type');
        const title = btn.getAttribute('data-title');

        const storageKey = 'vodfinder.watchlist';
        const raw = localStorage.getItem(storageKey);
        let list = [];
        try {
            list = raw ? JSON.parse(raw) : [];
        } catch (_) {
            list = [];
        }

        const key = type + ':' + id;

        function isInWatchlist() {
            return list.some(item => item.key === key);
        }

        function updateUi() {
            if (isInWatchlist()) {
                btn.textContent = 'Retirer de ma watchlist';
                status.textContent = 'Ce titre est dans votre watchlist (stockée sur cet appareil).';
            } else {
                btn.textContent = 'Ajouter à ma watchlist';
                status.textContent = '';
            }
        }

        btn.addEventListener('click', function () {
            if (isInWatchlist()) {
                list = list.filter(item => item.key !== key);
            } else {
                list.push({ key, id, type, title });
            }
            localStorage.setItem(storageKey, JSON.stringify(list));
            updateUi();
        });

        updateUi();
    })();
</script>
</body>
</html>
