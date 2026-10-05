<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ma liste - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.app-head')
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

<div class="vod-shell" id="main-content">

    @include('partials.main-navigation')
    <section class="vod-intro"><span class="vod-eyebrow">À regarder ensuite</span><h1>Ta playlist</h1><p>Les films et séries que tu gardes pour le bon moment.</p></section>

    @if($items->isEmpty())
        <p class="text-sm text-slate-300">Votre liste est vide pour le moment.</p>
    @else
        {{-- Toolbar Filtres / Tri --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Filtrer :</span>
                <select id="playlist-filter-type"
                        class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
                    <option value="all">Tous</option>
                    <option value="movie">Films</option>
                    <option value="tv">Séries</option>
                </select>
            </div>

            <div class="flex items-center gap-2 text-xs">
                <span class="text-slate-400">Trier par :</span>
                <select id="playlist-sort"
                        class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
                    <option value="added_desc">Ajout (récent → ancien)</option>
                    <option value="title_az">Titre (A → Z)</option>
                    <option value="title_za">Titre (Z → A)</option>
                    <option value="year_desc">Année (récent → ancien)</option>
                    <option value="year_asc">Année (ancien → récent)</option>
                </select>
            </div>
        </div>

        <div id="playlist-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($items as $item)
                <div class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden shadow text-sm"
                     data-playlist-card="1"
                     data-type="{{ $item->type }}"
                     data-year="{{ $item->year }}"
                     data-title="{{ Str::lower($item->title) }}"
                     data-added="{{ $item->added_at ?? 0 }}">

                    @if($item->poster)
                        <button type="button"
                                class="w-full text-left"
                                onclick="openPopup('{{ $item->type }}', '{{ $item->tmdb_id }}', true)">
                            <img src="{{ $item->poster }}" class="w-full h-56 object-cover rounded-t-lg" alt="">
                        </button>
                    @else
                        <button type="button"
                                class="w-full text-left"
                                onclick="openPopup('{{ $item->type }}', '{{ $item->tmdb_id }}', true)">
                            <div class="w-full h-56 flex items-center justify-center bg-slate-700 rounded-t-lg">
                                Aucune image
                            </div>
                        </button>
                    @endif

                    <div class="p-3 space-y-2">
                        <h2 class="font-semibold leading-tight flex items-center justify-between gap-2">
                            <button type="button"
                                    class="hover:underline text-left flex-1"
                                    onclick="openPopup('{{ $item->type }}', '{{ $item->tmdb_id }}', true)">
                                {{ $item->title }}
                                @if($item->year)
                                    <span class="text-xs text-slate-400">({{ $item->year }})</span>
                                @endif
                            </button>

                            <button type="button"
                                    class="ml-2 text-lg watchlist-toggle"
                                    data-watchlist-button="1"
                                    data-id="{{ $item->tmdb_id }}"
                                    data-type="{{ $item->type }}"
                                    data-title="{{ $item->title }}"
                                    data-year="{{ $item->year }}"
                                    data-poster="{{ $item->poster }}"
                                    data-in="1">
                                ⭐
                            </button>
                        </h2>

                        @if(!empty($item->overview))
                            <p class="text-xs text-slate-300">
                                {{ Str::limit($item->overview, 140, '…') }}
                            </p>
                        @endif
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>

<div id="modal-overlay" class="hidden fixed inset-0 z-50"></div>

<script>
    const IS_AUTH = @json(auth()->check());

    const playlistContainer    = document.getElementById('playlist-grid');
    const playlistFilterTypeEl = document.getElementById('playlist-filter-type');
    const playlistSortEl       = document.getElementById('playlist-sort');

    function renderPlaylist() {
        if (!playlistContainer) return;

        let cards = Array.from(playlistContainer.querySelectorAll('[data-playlist-card]'));

        // Filtre type
        if (playlistFilterTypeEl) {
            const filterType = playlistFilterTypeEl.value;
            if (filterType !== 'all') {
                cards = cards.filter(card => card.dataset.type === filterType);
            }
        }

        // Tri
        if (playlistSortEl) {
            const sort = playlistSortEl.value;
            cards.sort((a, b) => {
                const titleA = (a.dataset.title || '').toLowerCase();
                const titleB = (b.dataset.title || '').toLowerCase();
                const yearA  = a.dataset.year ? parseInt(a.dataset.year, 10) : 0;
                const yearB  = b.dataset.year ? parseInt(b.dataset.year, 10) : 0;
                const addedA = a.dataset.added ? parseInt(a.dataset.added, 10) : 0;
                const addedB = b.dataset.added ? parseInt(b.dataset.added, 10) : 0;

                switch (sort) {
                    case 'title_az':
                        return titleA.localeCompare(titleB);
                    case 'title_za':
                        return titleB.localeCompare(titleA);
                    case 'year_desc':
                        return (yearB || 0) - (yearA || 0);
                    case 'year_asc':
                        return (yearA || 0) - (yearB || 0);
                    case 'added_desc':
                    default:
                        return (addedB || 0) - (addedA || 0);
                }
            });
        }

        // Réinjection dans le DOM
        playlistContainer.innerHTML = '';
        cards.forEach(card => playlistContainer.appendChild(card));
    }

    // Listeners filtres/tri
    if (playlistFilterTypeEl) {
        playlistFilterTypeEl.addEventListener('change', renderPlaylist);
    }
    if (playlistSortEl) {
        playlistSortEl.addEventListener('change', renderPlaylist);
    }

    // On applique une première fois à l'ouverture
    document.addEventListener('DOMContentLoaded', () => {
        if (playlistContainer) {
            renderPlaylist();
        }
    });


    async function toggleWatchlist(button) {
        if (!IS_AUTH) {
            window.location.href = '/login';
            return;
        }

        const tmdbId = button.dataset.id;
        const type   = button.dataset.type;
        const title  = button.dataset.title || '';
        const year   = button.dataset.year || '';
        const poster = button.dataset.poster || '';

        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (!tokenMeta) {
            console.error('CSRF token meta missing');
            return;
        }
        const csrfToken = tokenMeta.getAttribute('content');

        let inWatchlist = true;

        try {
            const res = await fetch('/watchlist/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    tmdb_id: Number(tmdbId),
                    type: type,
                    title: title,
                    year: year,
                    poster: poster,
                }),
            });

            if (!res.ok) {
                console.error('Erreur watchlist', res.status);
                return;
            }

            const data = await res.json();
            inWatchlist = !!data.in_watchlist;

        } catch (e) {
            console.error('Erreur réseau watchlist', e);
            return;
        }

        // Mise à jour UI
        if (!inWatchlist) {
            const card = button.closest('[data-playlist-card]');
            if (card) {
                card.remove();
                renderPlaylist();
            }
        } else {
            button.textContent = '⭐';
        }
    }

    // Delegation : clic sur les boutons ⭐
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-watchlist-button]');
        if (btn) {
            toggleWatchlist(btn);
        }
    });


    // =====================================================
    // POPIN NETFLIX STYLE (Playlist)
    // =====================================================

    let modalOpen = false;

    async function openPopup(type, id, push = true) {
        const overlay = document.getElementById('modal-overlay');
        const country = 'FR'; // ou récupère depuis une préférence plus tard

        overlay.innerHTML =
            `<div class="flex items-center justify-center h-full">
                <div class="text-white text-sm">Chargement…</div>
             </div>`;
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        modalOpen = true;

        try {
            const url = `/title/${encodeURIComponent(type)}/${encodeURIComponent(id)}?country=${encodeURIComponent(country)}`;

            const res = await fetch(url, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest', // important pour que le contrôleur renvoie la popin
                },
            });
            const html = await res.text();
            overlay.innerHTML = html;

            const closeBtn = document.getElementById('popup-close');
            if (closeBtn) {
                closeBtn.onclick = () => closePopup(true);
            }

            const container = document.getElementById('popup-container');
            if (container) {
                container.addEventListener('click', (e) => {
                    if (e.target.id === 'popup-container') {
                        closePopup(true);
                    }
                });
            }

            document.addEventListener('keydown', escClose);

            if (push) {
                history.pushState(
                    {
                        modal: true,
                        type: String(type),
                        id: Number(id),
                        country: String(country)
                    },
                    '',
                    url
                );
            }

        } catch (e) {
            console.error(e);
            closePopup(false);
        }
    }

    function escClose(e) {
        if (e.key === 'Escape') {
            closePopup(true);
        }
    }

    function closePopup(useHistory) {
        const overlay = document.getElementById('modal-overlay');
        overlay.classList.add('hidden');
        overlay.innerHTML = '';
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', escClose);
        const wasOpen = modalOpen;
        modalOpen = false;

        if (useHistory && wasOpen) {
            history.back();
        }
    }

    // Optionnel: si tu veux que back/forward gèrent la popin aussi sur la playlist
    window.addEventListener('popstate', (event) => {
        const state = event.state;

        if (state && state.modal && state.type && state.id) {
            const country = state.country || 'FR';
            openPopup(state.type, state.id, false, country);
        } else {
            if (modalOpen) {
                closePopup(false);
            }
        }
    });

    // Helper utilisé dans la popin (providers cliquables)
    function openProviderPopup(btn) {
        const title    = btn.dataset.title || '';
        const provider = btn.dataset.provider || '';
        const url      = window.buildProviderUrl
            ? window.buildProviderUrl(title, provider)
            : null;

        if (url) {
            window.open(url, '_blank', 'noopener');
        }
    }
    
        // Gestion du select de saison dans la popin (chargée dynamiquement)
    document.addEventListener('change', function (e) {
        const select = e.target.closest('#season-select');
        if (!select) return;

        const targetId = select.value;
        const blocks = document.querySelectorAll('.season-block');

        blocks.forEach(block => {
            if (block.dataset.seasonId === targetId) {
                block.classList.remove('hidden');
            } else {
                block.classList.add('hidden');
            }
        });
    });
</script>
</body>
</html>