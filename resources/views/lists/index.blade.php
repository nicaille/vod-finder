<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>❤️ Mes listes - VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.app-head')
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">

<div class="vod-shell" id="main-content">

    @include('partials.main-navigation')

    <section class="vod-intro"><span class="vod-eyebrow">Tes collections</span><h1>Mes listes et coups de cœur</h1><p>Organise tes découvertes et retrouve les titres que tu aimes.</p></section>

    {{-- Onglets --}}
    <div class="border-b border-slate-700 mb-4 flex gap-4 text-sm">
        <button type="button"
                class="pb-2 border-b-2 border-indigo-400 text-indigo-300"
                data-tab-btn
                data-tab-target="lists">
            Listes
        </button>
        <button type="button"
                class="pb-2 border-b-2 border-transparent text-slate-400 hover:text-slate-200"
                data-tab-btn
                data-tab-target="favorites">
            Coups de cœur
        </button>
    </div>

    {{-- Contenu onglet LISTES --}}
    <div data-tab-content="lists">
        {{-- Toolbar : filtre + création --}}
        <div class="flex items-center justify-between mb-4 gap-3 text-xs">
            <div class="flex items-center gap-2">
                <span class="text-slate-400">Afficher :</span>
                <select id="privacy-filter"
                        class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
                    <option value="all">Toutes</option>
                    <option value="private">Privées</option>
                    <option value="public">Publiques</option>
                </select>
            </div>

            <button id="btn-open-create-list"
                    type="button"
                    class="flex items-center gap-1 px-3 py-1 rounded-lg border border-indigo-500 text-indigo-300 text-xs hover:bg-indigo-600 hover:text-slate-900">
                <span>＋</span>
                <span>Nouvelle liste</span>
            </button>
        </div>

        {{-- Panneau de création de liste --}}
        <div id="create-list-panel"
             class="hidden mb-4 bg-slate-800 border border-slate-700 rounded-lg p-3 text-sm">
            <form id="create-list-form" class="space-y-3">
                <div>
                    <label for="create-list-name" class="block text-xs text-slate-300 mb-1">
                        Nom de la liste
                    </label>
                    <input id="create-list-name" type="text" required
                           class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
                </div>

                <div>
                    <label for="create-list-description" class="block text-xs text-slate-300 mb-1">
                        Description (facultatif)
                    </label>
                    <textarea id="create-list-description" rows="2"
                              class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-1.5 text-sm resize-y focus:outline-none focus:ring-1 focus:ring-indigo-400"></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input id="create-list-public" type="checkbox"
                           class="rounded border-slate-600 bg-slate-900">
                    <label for="create-list-public" class="text-xs text-slate-300">
                        Liste publique (partageable plus tard)
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button"
                            id="create-list-cancel"
                            class="px-2 py-1 rounded border border-slate-600 text-slate-300 text-xs hover:bg-slate-700">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-3 py-1 rounded border border-indigo-500 bg-indigo-600 text-slate-900 text-xs font-semibold hover:bg-indigo-500">
                        Créer
                    </button>
                </div>
            </form>
        </div>

        @if(empty($lists) || !count($lists))
            <p class="text-sm text-slate-300">
                Tu n'as pas encore créé de liste. Depuis la popin d'un film / d'une série, utilise le bouton
                <span class="font-semibold">“Ajouter à une liste”</span> pour en créer une, ou clique sur
                <span class="font-semibold">“Nouvelle liste”</span> ci-dessus.
            </p>
        @else
            <div class="space-y-4">
                @foreach($lists as $list)
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-sm"
                         data-list-card
                         data-list-id="{{ $list['id'] }}"
                         data-list-is-public="{{ $list['is_public'] ? '1' : '0' }}">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <h2 class="font-semibold flex items-center gap-2">
                                    <span data-list-name-display="{{ $list['id'] }}">
                                        {{ $list['name'] }}
                                    </span>
                                    @if($list['is_public'])
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-700 text-emerald-50 uppercase"
                                              data-list-public-badge="{{ $list['id'] }}">
                                            Publique
                                        </span>
                                    @else
                                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 uppercase"
                                              data-list-public-badge="{{ $list['id'] }}">
                                            Privée
                                        </span>
                                    @endif
                                </h2>
                                @if(!empty($list['description']))
                                    <p class="text-xs text-slate-300 mt-1"
                                       data-list-description-display="{{ $list['id'] }}">
                                        {{ $list['description'] }}
                                    </p>
                                @else
                                    <p class="text-xs text-slate-500 mt-1"
                                       data-list-description-display="{{ $list['id'] }}">
                                        <em>Aucune description.</em>
                                    </p>
                                @endif
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    {{ count($list['items']) }} titre{{ count($list['items']) > 1 ? 's' : '' }}
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-1">
                                <button type="button"
                                        class="text-xs px-2 py-1 rounded border border-slate-500 text-slate-200 hover:bg-slate-700"
                                        data-edit-list-button
                                        data-list-id="{{ $list['id'] }}">
                                    Modifier
                                </button>

                                <form action="{{ route('lists.destroy', $list['id']) }}"
                                      method="POST"
                                      onsubmit="return confirm('Supprimer cette liste ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs px-2 py-1 rounded border border-red-500 text-red-300 hover:bg-red-500 hover:text-slate-900">
                                        Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Panneau d’édition inline --}}
                        <div class="mt-3 hidden border-t border-slate-700 pt-3 text-xs"
                             data-list-edit-panel="{{ $list['id'] }}">
                            <div class="grid gap-2">
                                <div>
                                    <label class="block mb-1 text-[11px] text-slate-300">
                                        Nom de la liste
                                    </label>
                                    <input type="text"
                                           data-edit-name="{{ $list['id'] }}"
                                           value="{{ $list['name'] }}"
                                           class="w-full bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                </div>
                                <div>
                                    <label class="block mb-1 text-[11px] text-slate-300">
                                        Description
                                    </label>
                                    <textarea rows="2"
                                              data-edit-description="{{ $list['id'] }}"
                                              class="w-full bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs resize-y focus:outline-none focus:ring-1 focus:ring-indigo-400">{{ $list['description'] }}</textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox"
                                           data-edit-public="{{ $list['id'] }}"
                                           class="rounded border-slate-600 bg-slate-900"
                                           {{ $list['is_public'] ? 'checked' : '' }}>
                                    <span class="text-[11px] text-slate-300">
                                        Rendre cette liste publique
                                    </span>
                                </div>
                            </div>

                            <div class="mt-2 flex justify-end gap-2">
                                <button type="button"
                                        class="px-2 py-1 rounded border border-slate-600 text-slate-300 text-[11px] hover:bg-slate-700"
                                        data-list-edit-cancel="{{ $list['id'] }}">
                                    Annuler
                                </button>
                                <button type="button"
                                        class="px-3 py-1 rounded border border-indigo-500 bg-indigo-600 text-slate-900 text-[11px] font-semibold hover:bg-indigo-500"
                                        data-list-edit-save="{{ $list['id'] }}">
                                    Enregistrer
                                </button>
                            </div>
                        </div>

                        @if(empty($list['items']) || !count($list['items']))
                            <p class="text-xs text-slate-400 mt-3">
                                Aucun titre dans cette liste.
                            </p>
                        @else
                            <div class="space-y-2 mt-3">
                                @foreach($list['items'] as $item)
                                    <div class="flex gap-3 items-center"
                                         data-list-item-row="{{ $list['id'] }}-{{ $item['id'] }}">
                                        <div class="w-12 h-16 flex-shrink-0 bg-slate-700 rounded overflow-hidden">
                                            @if($item['poster'])
                                                <img src="{{ $item['poster'] }}"
                                                     alt="{{ $item['title'] }}"
                                                     class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-[10px] text-slate-400">
                                                    Aucune image
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <div>
                                                    <div class="font-semibold truncate">
                                                        {{ $item['title'] }}
                                                        @if($item['year'])
                                                            <span class="text-xs text-slate-400">
                                                                ({{ $item['year'] }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-400">
                                                        {{ $item['type'] === 'tv' ? 'Série' : 'Film' }}
                                                    </div>
                                                </div>

                                                <div class="flex flex-col items-end gap-1">
                                                    {{-- Voir (popin) --}}
                                                    <button type="button"
                                                            class="text-[11px] px-2 py-1 rounded border border-slate-500 text-slate-200 hover:bg-slate-700"
                                                            onclick="openPopup('{{ $item['type'] }}', '{{ $item['tmdb_id'] }}', true)">
                                                        Voir
                                                    </button>

                                                    <div class="flex gap-1">
                                                        {{-- Coup de cœur --}}
                                                        <button type="button"
                                                                class="text-[11px] px-2 py-1 rounded border border-pink-500 text-pink-400 hover:bg-pink-500 hover:text-slate-900"
                                                                data-favorite-from-list="1"
                                                                data-tmdb-id="{{ $item['tmdb_id'] }}"
                                                                data-type="{{ $item['type'] }}">
                                                            ❤️
                                                        </button>

                                                        {{-- Retirer de la liste --}}
                                                        <button type="button"
                                                                class="text-[11px] px-2 py-1 rounded border border-red-500 text-red-400 hover:bg-red-500 hover:text-slate-900"
                                                                data-remove-from-list="1"
                                                                data-list-id="{{ $list['id'] }}"
                                                                data-item-id="{{ $item['id'] }}">
                                                            Retirer
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Contenu onglet FAVORIS --}}
    <div data-tab-content="favorites" class="hidden">
        @if(empty($favorites) || !count($favorites))
            <p class="text-sm text-slate-300">
                Tu n'as pas encore de coups de cœur. Utilise le bouton
                <span class="font-semibold">“Coup de cœur”</span> dans la popin d'un titre.
            </p>
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach($favorites as $fav)
                    <button type="button"
                            class="bg-slate-800 border border-slate-700 rounded-lg overflow-hidden text-left text-xs hover:border-indigo-400"
                            onclick="openPopup('{{ $fav['type'] }}', '{{ $fav['tmdb_id'] }}', true)">
                        <div class="w-full h-40 bg-slate-700 overflow-hidden">
                            @if($fav['poster'])
                                <img src="{{ $fav['poster'] }}"
                                     alt="{{ $fav['title'] }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-[11px] text-slate-400">
                                    Aucune image
                                </div>
                            @endif
                        </div>
                        <div class="p-2">
                            <div class="font-semibold truncate">
                                {{ $fav['title'] }}
                            </div>
                            @if($fav['year'])
                                <div class="text-[11px] text-slate-400">
                                    {{ $fav['year'] }}
                                </div>
                            @endif
                            <div class="text-[10px] text-slate-500">
                                {{ $fav['type'] === 'tv' ? 'Série' : 'Film' }}
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- PopIn overlay (réutilisation du mécanisme existant) --}}
    <div id="modal-overlay" class="hidden fixed inset-0 z-50"></div>

</div>

<script>
    // Tabs simple
    document.querySelectorAll('[data-tab-btn]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tabTarget;

            document.querySelectorAll('[data-tab-btn]').forEach(b => {
                const isActive = b.dataset.tabTarget === target;
                b.classList.toggle('border-indigo-400', isActive);
                b.classList.toggle('text-indigo-300', isActive);
                b.classList.toggle('border-transparent', !isActive);
                b.classList.toggle('text-slate-400', !isActive);
            });

            document.querySelectorAll('[data-tab-content]').forEach(c => {
                c.classList.toggle('hidden', c.dataset.tabContent !== target);
            });
        });
    });
</script>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function showToast(message, variant = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed inset-x-0 bottom-4 flex justify-center z-[60] pointer-events-none';
            document.body.appendChild(container);
        }

        const colorClasses =
            variant === 'error'
                ? 'bg-red-600 border-red-400'
                : 'bg-emerald-600 border-emerald-400';

        const toast = document.createElement('div');
        toast.className =
            'pointer-events-auto max-w-xs px-3 py-2 rounded-lg shadow-lg border text-sm text-slate-50 ' +
            colorClasses +
            ' flex items-center gap-2';

        toast.innerHTML = `<span>${message}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-1');
            toast.style.transition = 'opacity 150ms ease-out, transform 150ms ease-out';
            setTimeout(() => toast.remove(), 180);
        }, 2300);
    }

    // Filtre privées / publiques
    const privacyFilter = document.getElementById('privacy-filter');
    if (privacyFilter) {
        privacyFilter.addEventListener('change', () => {
            const value = privacyFilter.value; // all | private | public

            document.querySelectorAll('[data-list-card]').forEach(card => {
                const isPublic = card.dataset.listIsPublic === '1';
                let show = true;

                if (value === 'private') {
                    show = !isPublic;
                } else if (value === 'public') {
                    show = isPublic;
                }

                card.classList.toggle('hidden', !show);
            });
        });
    }

    // Création de liste
    const btnOpenCreate = document.getElementById('btn-open-create-list');
    const createPanel   = document.getElementById('create-list-panel');
    const createForm    = document.getElementById('create-list-form');
    const createCancel  = document.getElementById('create-list-cancel');

    if (btnOpenCreate && createPanel) {
        btnOpenCreate.addEventListener('click', () => {
            createPanel.classList.toggle('hidden');
        });
    }

    if (createCancel && createForm) {
        createCancel.addEventListener('click', () => {
            createForm.reset();
            createPanel.classList.add('hidden');
        });
    }

    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const nameEl = document.getElementById('create-list-name');
            const descEl = document.getElementById('create-list-description');
            const pubEl  = document.getElementById('create-list-public');

            const name = (nameEl?.value || '').trim();
            const description = (descEl?.value || '').trim();
            const isPublic = !!(pubEl && pubEl.checked);

            if (!name) {
                showToast('Le nom de la liste est obligatoire.', 'error');
                return;
            }

            try {
                const res = await fetch('/lists', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        description: description,
                        is_public: isPublic,
                    }),
                });

                if (!res.ok) {
                    showToast('Erreur lors de la création de la liste.', 'error');
                    return;
                }

                showToast('Liste créée avec succès.');
                window.setTimeout(() => window.location.reload(), 500);
            } catch (e) {
                console.error(e);
                showToast('Erreur réseau lors de la création.', 'error');
            }
        });
    }

    // Gestion des panneaux d’édition de liste (rename / description / public)
    document.addEventListener('click', async (e) => {
        // Ouverture/fermeture du panneau
        const editBtn = e.target.closest('[data-edit-list-button]');
        if (editBtn) {
            const listId = editBtn.dataset.listId;
            if (!listId) return;

            const panel = document.querySelector(`[data-list-edit-panel="${listId}"]`);
            if (panel) {
                panel.classList.toggle('hidden');
            }
            return;
        }

        // Annuler
        const cancelBtn = e.target.closest('[data-list-edit-cancel]');
        if (cancelBtn) {
            const listId = cancelBtn.dataset.listEditCancel;
            if (!listId) return;

            const panel = document.querySelector(`[data-list-edit-panel="${listId}"]`);
            if (panel) {
                panel.classList.add('hidden');
            }
            return;
        }

        // Enregistrer
        const saveBtn = e.target.closest('[data-list-edit-save]');
        if (saveBtn) {
            const listId = saveBtn.dataset.listEditSave;
            if (!listId) return;

            const nameInput = document.querySelector(`[data-edit-name="${listId}"]`);
            const descInput = document.querySelector(`[data-edit-description="${listId}"]`);
            const pubInput  = document.querySelector(`[data-edit-public="${listId}"]`);

            const name        = (nameInput?.value || '').trim();
            const description = (descInput?.value || '').trim();
            const isPublic    = !!(pubInput && pubInput.checked);

            if (!name) {
                showToast('Le nom de la liste est obligatoire.', 'error');
                return;
            }

            try {
                const res = await fetch(`/lists/${encodeURIComponent(listId)}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        description: description,
                        is_public: isPublic,
                    }),
                });

                if (!res.ok) {
                    showToast('Erreur lors de la mise à jour de la liste.', 'error');
                    return;
                }

                // Mise à jour visuelle rapide
                const nameDisplay = document.querySelector(`[data-list-name-display="${listId}"]`);
                if (nameDisplay) {
                    nameDisplay.textContent = name;
                }

                const descDisplay = document.querySelector(`[data-list-description-display="${listId}"]`);
                if (descDisplay) {
                    if (description) {
                        descDisplay.textContent = description;
                        descDisplay.classList.remove('text-slate-500');
                    } else {
                        descDisplay.textContent = 'Aucune description.';
                        descDisplay.classList.add('text-slate-500');
                    }
                }

                const badge = document.querySelector(`[data-list-public-badge="${listId}"]`);
                const card  = document.querySelector(`[data-list-card][data-list-id="${listId}"]`);
                if (badge && card) {
                    card.dataset.listIsPublic = isPublic ? '1' : '0';

                    if (isPublic) {
                        badge.textContent = 'Publique';
                        badge.className =
                            'text-[10px] px-2 py-0.5 rounded-full bg-emerald-700 text-emerald-50 uppercase';
                    } else {
                        badge.textContent = 'Privée';
                        badge.className =
                            'text-[10px] px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 uppercase';
                    }
                }

                const panel = document.querySelector(`[data-list-edit-panel="${listId}"]`);
                if (panel) panel.classList.add('hidden');

                showToast('Liste mise à jour.');
            } catch (err) {
                console.error(err);
                showToast('Erreur réseau lors de la mise à jour.', 'error');
            }
        }
    });

    // Retirer un item de liste
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-from-list]');
        if (!btn) return;

        const listId = btn.dataset.listId;
        const itemId = btn.dataset.itemId;

        if (!listId || !itemId) return;

        if (!confirm('Retirer ce titre de la liste ?')) {
            return;
        }

        try {
            const url = `/lists/${encodeURIComponent(listId)}/items/${encodeURIComponent(itemId)}`;

            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });

            if (!res.ok) {
                showToast('Erreur lors du retrait du titre.', 'error');
                return;
            }

            const rowSelector = `[data-list-item-row="${listId}-${itemId}"]`;
            const row = document.querySelector(rowSelector);
            if (row) row.remove();

            showToast('Titre retiré de la liste.');
        } catch (err) {
            console.error(err);
            showToast('Erreur réseau lors du retrait.', 'error');
        }
    });

    // Coup de cœur depuis une liste
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-favorite-from-list]');
        if (!btn) return;

        const tmdbId = btn.dataset.tmdbId;
        const type   = btn.dataset.type;

        if (!tmdbId || !type) return;

        try {
            const res = await fetch('/favorites/toggle', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    tmdb_id: tmdbId,
                    type: type,
                }),
            });

            if (!res.ok) {
                showToast('Erreur lors de la mise à jour des coups de cœur.', 'error');
                return;
            }

            const data = await res.json();
            const favorited = !!data.favorited;

            if (favorited) {
                btn.classList.add('bg-pink-500', 'text-slate-900');
                btn.classList.remove('text-pink-400');
                showToast('Ajouté à tes coups de cœur.');
            } else {
                btn.classList.remove('bg-pink-500', 'text-slate-900');
                btn.classList.add('text-pink-400');
                showToast('Retiré de tes coups de cœur.');
            }
        } catch (err) {
            console.error(err);
            showToast('Erreur réseau sur les coups de cœur.', 'error');
        }
    });
</script>

<script>
    let modalOpen = false;

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

    async function openPopup(type, id, push = true, countryOverride = null) {
        const overlay = document.getElementById('modal-overlay');
        const country = (countryOverride || 'FR').toUpperCase(); // ici on force FR, tu peux adapter si besoin

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
                    'X-Requested-With': 'XMLHttpRequest',
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
                        country: String(country),
                    },
                    '',
                    url
                );
            }
        } catch (err) {
            console.error(err);
            closePopup(false);
        }
    }

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
</script>

</body>
</html>