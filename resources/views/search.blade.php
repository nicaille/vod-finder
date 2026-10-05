<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>VOD Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('partials.app-head')

    <style>
        #autocomplete button:hover { background-color: rgb(51 65 85); }
        #modal-overlay {
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background:rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 50;
        }
    </style>
</head>

<body class="bg-slate-900 text-slate-100 min-h-screen">

<div class="vod-shell" id="main-content">

    @include('partials.main-navigation')

    <section class="vod-intro"><span class="vod-eyebrow">Ton prochain coup de cœur</span><h1>Ce soir, on regarde quoi&nbsp;?</h1><p>Films, séries, envies du moment. Retrouve ce qui est disponible sur tes plateformes et suis les prochaines sorties.</p></section>

    {{-- FORMULAIRE --}}
    <div class="vod-search-panel bg-slate-800 border border-slate-700 rounded-lg p-5 space-y-5">

        {{-- Barre de recherche --}}
        <div>
            <label class="block text-sm mb-1">Recherche</label>

            <div class="relative">
                <input type="text" id="q"
                       class="w-full bg-slate-900 border border-slate-700 rounded px-3 pr-10 py-2 text-sm"
                       placeholder="Ex: Dune, Fallout, Tom Hanks..."
                       autocomplete="off">

                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="h-4 w-4"
                         viewBox="0 0 20 20"
                         fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387a1 1 0 01-1.414 1.414l-3.387-3.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z"
                              clip-rule="evenodd" />
                    </svg>
                </span>

                <div id="autocomplete"
                     class="absolute left-0 right-0 top-full bg-slate-900 border border-slate-700 rounded mt-1 hidden z-20 text-sm vod-autocomplete"></div>
            </div>

            <p class="text-xs text-slate-400 mt-2">
                Astuce: tu peux taper un titre ou une personne (acteur, réalisateur...). Entrée lance une recherche intelligente.
            </p>
        </div>

        {{-- Type --}}
        <div>
            <label class="block text-sm mb-1">Type</label>
            <select id="type"
                    class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
                <option value="movie">Film</option>
                <option value="tv">Série</option>
            </select>
        </div>

        <details>
            <summary>Filtres et plateformes</summary>
            <div class="vod-filter-grid">
        {{-- Plateformes --}}
        <div>
            <label class="block text-sm mb-1">Plateformes</label>

            <div class="grid grid-cols-2 gap-2 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" value="netflix" class="provider-checkbox" data-slug="netflix">
                    <span>Netflix</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="prime" class="provider-checkbox" data-slug="prime">
                    <span>Prime Video</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="disneyplus" class="provider-checkbox" data-slug="disneyplus">
                    <span>Disney+</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="canalplus" class="provider-checkbox" data-slug="canalplus">
                    <span>Canal+</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="appletv" class="provider-checkbox" data-slug="appletv">
                    <span>Apple TV+</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="paramountplus" class="provider-checkbox" data-slug="paramountplus">
                    <span>Paramount+</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" value="hbomax" class="provider-checkbox" data-slug="hbomax">
                    <span>HBO Max</span>
                </label>
            </div>
        </div>

        {{-- Pays --}}
        <div>
            <label class="block text-sm mb-1">Pays / région TMDb</label>
            <select id="country"
                    class="w-full bg-slate-900 border border-slate-700 rounded px-3 py-2 text-sm">
                <option value="FR">France (FR)</option>
                <option value="US">États-Unis (US)</option>
                <option value="GB">Royaume-Uni (GB)</option>
                <option value="DE">Allemagne (DE)</option>
                <option value="ES">Espagne (ES)</option>
                <option value="IT">Italie (IT)</option>
                <option value="CA">Canada (CA)</option>
            </select>
        </div>

        {{-- Type d'accès --}}
        <div>
            <label class="block text-sm mb-1">Type d'accès</label>

            <div class="grid grid-cols-2 gap-2 text-xs">
                <label class="flex items-center gap-2">
                    <input type="radio" name="access" value="all" checked>
                    <span>Tous</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="radio" name="access" value="flatrate">
                    <span>Inclus</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="radio" name="access" value="rent">
                    <span>Location</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="radio" name="access" value="buy">
                    <span>Achat</span>
                </label>
            </div>
        </div>

            </div>
        </details>

        {{-- Bouton --}}
        <button id="search-button"
                type="button"
                class="w-full py-2 rounded bg-indigo-600 hover:bg-indigo-500 text-sm font-semibold">
            🔍 Rechercher
        </button>

        <p id="loader" class="hidden text-sm text-slate-400">Recherche en cours...</p>
        <p id="error" class="hidden text-sm text-red-400"></p>
    </div>

    <section id="home-releases-section" class="mt-8" aria-labelledby="home-releases-heading">
        <h2 id="home-releases-heading" class="text-xl font-semibold">Sorties récentes sur tes plateformes</h2>
        <p class="text-xs text-slate-400 mt-2">Films sortis et séries commencées ces 90 derniers jours, actuellement inclus dans tes abonnements en France. La date d’ajout au catalogue n’est pas fournie.</p>
        <p id="home-releases-status" class="text-sm text-slate-300 mt-4" role="status" aria-live="polite"></p>
        <button type="button" id="home-releases-retry" class="hidden mt-3 px-4 py-2 rounded border border-slate-500 text-sm">Réessayer</button>
        <div id="home-releases" class="grid mt-5"></div>
    </section>

    {{-- Toolbar Filtres / Tri pour les résultats --}}
    <div id="results-toolbar" class="mt-8 mb-2 flex flex-wrap items-center justify-between gap-3 hidden">
        <div class="flex items-center gap-2 text-xs">
            <span class="text-slate-400">Filtrer :</span>
            <label class="inline-flex items-center gap-1">
                <input type="checkbox" id="filter-flatrate-only" class="rounded border-slate-600">
                <span>Inclus uniquement</span>
            </label>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <span class="text-slate-400">Trier par :</span>
            <select id="sort-results"
                    class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs">
                <option value="relevance">Pertinence</option>
                <option value="year_desc">Année (récent → ancien)</option>
                <option value="year_asc">Année (ancien → récent)</option>
                <option value="title_az">Titre (A → Z)</option>
                <option value="title_za">Titre (Z → A)</option>
            </select>
        </div>
    </div>

    {{-- Résultats --}}
    <div id="results" class="mt-8 grid grid-cols-1 sm:grid-cols-2 gap-4"></div>

    {{-- PopIn --}}
    <div id="modal-overlay" class="hidden fixed inset-0 z-50"></div>

</div>

<script>
    const DEFAULT_PROVIDER_SLUGS = @json($defaultProviderSlugs ?? []);
    const STORAGE_KEY = 'vodfinder_search_form_v2';
    const STORAGE_RESULTS_PREFIX = 'vodfinder_results:v3:'; // cache par requête
    const CACHE_TTL_DAYS = 20;
    const CACHE_MAX_ENTRIES = 40;
    const IS_AUTH = @json(auth()->check());

    // null => recherche titre
    // number => un seul person_id
    // array  => plusieurs person_ids[]
    let currentPersonId = null;

    // Filtre: masquer les contenus sans overview (par défaut ON)
    let hideNoOverview = true;

    // Anti double-restore (load + pageshow)
    let restoring = false;

    const qInput = document.getElementById('q');
    const autocompleteEl = document.getElementById('autocomplete');
    const resultsEl = document.getElementById('results');
    const loaderEl = document.getElementById('loader');
    const button = document.getElementById('search-button');
    const errorEl = document.getElementById('error');

    let autocompleteTimeout = null;
    let autocompleteRequestId = 0;
    let lastResults = [];
    let homeReleasesLoaded = false;
    let homeReleasesLoading = false;
    const homeSection = document.getElementById('home-releases-section');
    const homeGrid = document.getElementById('home-releases');
    const homeStatus = document.getElementById('home-releases-status');
    const homeRetry = document.getElementById('home-releases-retry');

    async function showHomeReleases() {
        const searching = !!qInput.value.trim();
        homeSection.hidden = searching;
        if (searching || homeReleasesLoaded || homeReleasesLoading) return;
        if (!IS_AUTH) {
            homeStatus.innerHTML = 'Connecte-toi pour découvrir les sorties sur tes plateformes. <a class="underline" href="/login">Se connecter</a>';
            return;
        }
        homeReleasesLoading = true;
        homeRetry.classList.add('hidden');
        homeStatus.textContent = 'Recherche des sorties récentes…';
        try {
            const response = await fetch('/home/releases', {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Les sorties récentes sont momentanément indisponibles.');
            const data = await response.json();
            const items = Array.isArray(data.results) ? data.results : [];
            homeGrid.innerHTML = items.map(renderCard).join('');
            homeStatus.innerHTML = data.reason === 'no_platforms'
                ? 'Ajoute tes abonnements dans <a href="/account" class="underline">Mon compte</a> pour voir les sorties qui te correspondent.'
                : items.length ? '' : 'Aucune sortie récente trouvée sur tes plateformes pour le moment.';
            homeReleasesLoaded = true;
        } catch (error) {
            homeStatus.textContent = error.message || 'Impossible de charger les sorties récentes.';
            homeRetry.classList.remove('hidden');
        } finally {
            homeReleasesLoading = false;
        }
    }
    homeRetry.addEventListener('click', showHomeReleases);

    const resultsToolbarEl = document.getElementById('results-toolbar');
    const sortSelectEl = document.getElementById('sort-results');
    const flatrateOnlyEl = document.getElementById('filter-flatrate-only');

    function nowTs() {
        return Date.now();
    }

    function daysToMs(days) {
        return days * 24 * 60 * 60 * 1000;
    }

    function parseTs(v) {
        const n = Number(v);
        return Number.isFinite(n) && n > 0 ? n : 0;
    }

    function listSessionKeysWithPrefix(prefix) {
        const keys = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const k = sessionStorage.key(i);
            if (k && k.startsWith(prefix)) keys.push(k);
        }
        return keys;
    }

    function purgeOldSearchCaches() {
        try {
            const prefix = STORAGE_RESULTS_PREFIX;
            const keys = listSessionKeysWithPrefix('vodfinder_results:');
            if (!keys.length) return;

            const cutoff = nowTs() - daysToMs(CACHE_TTL_DAYS);

            const entries = [];

            keys.forEach((k) => {
                if (!k.startsWith(prefix)) { sessionStorage.removeItem(k); return; }
                try {
                    const raw = sessionStorage.getItem(k);
                    if (!raw) { sessionStorage.removeItem(k); return; }

                    const payload = JSON.parse(raw);
                    const ts = parseTs(payload?.ts);

                    // si pas de ts -> on purge (vieux format)
                    if (!ts || ts < cutoff) {
                        sessionStorage.removeItem(k);
                        return;
                    }

                    entries.push({ k, ts });
                } catch (e) {
                    try { sessionStorage.removeItem(k); } catch (_) {}
                }
            });

            if (entries.length > CACHE_MAX_ENTRIES) {
                entries.sort((a, b) => b.ts - a.ts);
                const toDelete = entries.slice(CACHE_MAX_ENTRIES);
                toDelete.forEach(e => {
                    try { sessionStorage.removeItem(e.k); } catch (_) {}
                });
            }
        } catch (e) {
            // silencieux
        }
    }

    function normalizeStr(s) {
        return String(s || '')
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function currentCountry() {
        const el = document.getElementById('country');
        if (!el) return 'FR';
        const val = el.value || 'FR';
        return String(val).toUpperCase();
    }

    function readAccess() {
        return document.querySelector('input[name="access"]:checked')?.value || 'all';
    }

    function readProviders() {
        return Array.from(document.querySelectorAll('.provider-checkbox:checked')).map(cb => cb.value);
    }

    function normalizePersonIdForKey(pid) {
        if (pid === null || typeof pid === 'undefined') return '';
        if (Array.isArray(pid)) {
            return Array.from(new Set(pid.map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0)))
                .sort((a, b) => a - b)
                .join(',');
        }
        const n = parseInt(pid, 10);
        return (Number.isFinite(n) && n > 0) ? String(n) : '';
    }

    function normalizeProvidersForKey(arr) {
        if (!Array.isArray(arr)) return '';
        return arr
            .map(v => String(v || '').trim())
            .filter(Boolean)
            .sort()
            .join(',');
    }

    function buildSearchKey({ q, type, country, personId, access, providers }) {
        return [
            (q || '').trim().toLowerCase(),
            String(type || 'movie'),
            String(country || 'FR').toUpperCase(),
            normalizePersonIdForKey(personId),
            String(access || 'all'),
            normalizeProvidersForKey(providers)
        ].join('|');
    }

    function saveFormState() {
        const state = {
            q: document.getElementById('q').value.trim(),
            type: document.getElementById('type').value,
            country: document.getElementById('country').value,
            access: readAccess(),
            providers: readProviders(),
            personId: currentPersonId
        };

        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); }
        catch (e) { console.warn('localStorage unavailable', e); }
    }

    function getCachedResultsByKey(key) {
        if (!key) return null;
        try {
            const raw = sessionStorage.getItem(STORAGE_RESULTS_PREFIX + key);
            if (!raw) return null;
            const payload = JSON.parse(raw);
            if (payload && Array.isArray(payload.results)) return payload;
        } catch (e) {}
        return null;
    }

    function setCachedResultsByKey(key, payload) {
        if (!key) return;
        try {
            payload.ts = nowTs();
            sessionStorage.setItem(STORAGE_RESULTS_PREFIX + key, JSON.stringify(payload));
        } catch (e) {}
    }

    function restoreFormState() {
        if (restoring) return;
        restoring = true;

        try {
            purgeOldSearchCaches();
            const state = readStoredFormState();
            if (!state) {
                applyDefaultProvidersIfNoStoredState();
                return;
            }

            if (state.q) document.getElementById('q').value = state.q;
            if (state.type) document.getElementById('type').value = state.type;
            if (state.country) document.getElementById('country').value = state.country;

            if (state.access) {
                const radio = document.querySelector(`input[name="access"][value="${state.access}"]`);
                if (radio) radio.checked = true;
            }

            if (Array.isArray(state.providers)) {
                const checkboxes = document.querySelectorAll('.provider-checkbox');
                checkboxes.forEach(cb => cb.checked = state.providers.includes(cb.value));
            }

            if (typeof state.personId !== 'undefined' && state.personId !== null) {
                if (Array.isArray(state.personId)) {
                    const ids = state.personId
                        .map(v => parseInt(v, 10))
                        .filter(v => Number.isFinite(v) && v > 0);
                    currentPersonId = ids.length ? Array.from(new Set(ids)) : null;
                } else {
                    const id = parseInt(state.personId, 10);
                    currentPersonId = Number.isFinite(id) && id > 0 ? id : null;
                }
            } else {
                currentPersonId = null;
            }

            const key = buildSearchKey({
                q: state.q || '',
                type: state.type || document.getElementById('type').value || 'movie',
                country: state.country || document.getElementById('country').value || 'FR',
                personId: currentPersonId,
                access: state.access || readAccess(),
                providers: Array.isArray(state.providers) ? state.providers : readProviders()
            });

            const cached = getCachedResultsByKey(key);
            if (cached) {
                lastResults = cached.results;
                resultsToolbarEl.classList.toggle('hidden', lastResults.length === 0);
                renderResults();
                return;
            }

            if ((state.q || '').trim()) {
                setTimeout(() => doSearch(), 50);
            }
        } catch (e) {
            console.warn('Failed to restore state', e);
        } finally {
            restoring = false;
        }
    }

    /* ------------------------------- */
    /* AUTOCOMPLÉTION                  */
    /* ------------------------------- */

    function hideAutocomplete() {
        autocompleteRequestId++;
        if (autocompleteTimeout) clearTimeout(autocompleteTimeout);
        autocompleteEl.classList.add('hidden');
        autocompleteEl.innerHTML = '';
    }

    function renderAutocompleteItem(item) {
        const year = item.year ? ` (${item.year})` : '';

        let typeLabel = '';
        if (item.type === 'tv') typeLabel = 'Série';
        if (item.type === 'movie') typeLabel = 'Film';
        if (item.type === 'person') typeLabel = ({ Acting: 'Interprétation', Directing: 'Réalisation', Production: 'Production', Writing: 'Écriture', Sound: 'Son', Camera: 'Image' })[item.department] || 'Personne';

        const portrait = item.type === 'person' ? (item.profile
            ? `<img class="vod-person-photo" src="${escapeHtml(item.profile)}" alt="" loading="lazy">`
            : '<span class="vod-person-photo vod-person-placeholder" aria-hidden="true">?</span>') : '';
        const known = Array.isArray(item.known_titles) ? item.known_titles.slice(0, 2).join(' · ') : '';
        const birth = /^\d{4}-\d{2}-\d{2}$/.test(item.birthday || '') ? `Naissance : ${item.birthday.split('-').reverse().join('/')}` : '';
        const context = item.type === 'person' ? [known, birth].filter(Boolean).map(text => `<span class="vod-person-context">${escapeHtml(text)}</span>`).join('') : '';

        return `
            <button type="button"
                    class="w-full px-3 py-2 flex justify-between items-center"
                    data-title="${escapeHtml(item.title)}"
                    data-type="${escapeHtml(item.type)}"
                    data-id="${escapeHtml(item.id ?? '')}">
                <span class="vod-person-identity">${portrait}<span class="vod-suggestion-title">${escapeHtml(item.title)}${escapeHtml(year)}${context}</span></span>
                <span class="text-xs text-slate-400 flex-shrink-0">${typeLabel}</span>
            </button>
        `;
    }

    function renderPeopleSuggestions(people) {
        const groups = new Map();
        people.forEach(person => {
            const key = normalizeStr(person.title);
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(person);
        });
        return Array.from(groups.values()).map(group => {
            const [first, ...others] = group;
            return renderAutocompleteItem(first) + (others.length
                ? `<details class="vod-person-others"><summary>Voir les autres ${escapeHtml(first.title)} (${others.length})</summary>${others.map(renderAutocompleteItem).join('')}</details>` : '');
        }).join('');
    }

    function handleAutocompleteInput(e) {
        hideAutocomplete();
        const term = (e.target.value || '').trim();
        currentPersonId = null;

        if (autocompleteTimeout) clearTimeout(autocompleteTimeout);

        if (term.length < 2) {
            hideAutocomplete();
            return;
        }

        autocompleteTimeout = setTimeout(() => {
            fetchAutocomplete(term);
        }, 250);
    }

    async function fetchAutocomplete(term) {
        const requestId = ++autocompleteRequestId;
        try {
            const res = await fetch('/autocomplete?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' }
            });
            if (requestId !== autocompleteRequestId || qInput.value.trim() !== term) return;
            if (!res.ok) return hideAutocomplete();

            const data = await res.json();
            const items = data.results || [];
            if (requestId !== autocompleteRequestId || qInput.value.trim() !== term) return;

            if (!items.length) return hideAutocomplete();

            const contents = items.filter(item => item.type !== 'person');
            const people = items.filter(item => item.type === 'person');
            autocompleteEl.innerHTML = [
                ['Films et séries', contents], ['Personnes', people]
            ].map(([label, group]) => `<section class="vod-suggestion-group" aria-label="${label}"><h3>${label}</h3>${group.length ? (label === 'Personnes' ? renderPeopleSuggestions(group) : group.map(renderAutocompleteItem).join('')) : '<p class="vod-suggestion-empty">Aucune suggestion</p>'}</section>`).join('');
            autocompleteEl.classList.remove('hidden');
        } catch (_) {
            if (requestId === autocompleteRequestId && qInput.value.trim() === term) hideAutocomplete();
        }
    }

    function handleAutocompleteClick(e) {
        const btn = e.target.closest('button[data-title]');
        if (!btn) return;

        const kind = btn.dataset.type;
        const idRaw = btn.dataset.id || '';

        qInput.value = btn.dataset.title;

        if (kind === 'person') {
            const ids = String(idRaw)
                .split('|')
                .map(v => parseInt(v, 10))
                .filter(v => Number.isFinite(v) && v > 0);

            if (ids.length === 1) currentPersonId = ids[0];
            else if (ids.length > 1) currentPersonId = Array.from(new Set(ids));
            else currentPersonId = null;
        } else {
            currentPersonId = null;
            document.getElementById('type').value = kind;
        }

        hideAutocomplete();
        doSearch();
    }

    async function smartEnterSearch() {
        const term = qInput.value.trim();
        if (!term) return;

        currentPersonId = null;

        try {
            const res = await fetch('/autocomplete?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' }
            });

            if (res.ok) {
                const data = await res.json();
                const items = Array.isArray(data.results) ? data.results : [];

                if (qInput.value.trim() !== term) return;
                const termN = normalizeStr(term);

                let exactPerson = items.find(it =>
                    it && it.type === 'person' && normalizeStr(it.title) === termN && it.id
                );

                // Un titre correspondant prime sur une personne homonyme.
                if (items.some(it => it.type !== 'person' && normalizeStr(it.title) === termN)) {
                    exactPerson = null;
                }

                if (exactPerson && exactPerson.id) {
                    const ids = String(exactPerson.id)
                        .split('|')
                        .map(v => parseInt(v, 10))
                        .filter(v => Number.isFinite(v) && v > 0);

                    if (ids.length === 1) currentPersonId = ids[0];
                    else if (ids.length > 1) currentPersonId = Array.from(new Set(ids));
                }
            }
        } catch (e) {
            console.warn('smartEnterSearch autocomplete failed', e);
        }

        await doSearch();
    }

    /* ------------------------------- */
    /* RECHERCHE                       */
    /* ------------------------------- */

    async function doSearch(e) {
        if (e) e.preventDefault();

        errorEl.classList.add('hidden');

        const q = qInput.value.trim();
        if (!q) return;
        showHomeReleases();

        saveFormState();

        const type = document.getElementById('type').value;
        const country = document.getElementById('country').value || 'FR';
        const access = readAccess();
        const selectedProviders = readProviders();

        const key = buildSearchKey({
            q,
            type,
            country,
            personId: currentPersonId,
            access,
            providers: selectedProviders
        });

        const cached = getCachedResultsByKey(key);
        if (cached) {
            lastResults = cached.results;
            resultsToolbarEl.classList.toggle('hidden', lastResults.length === 0);
            renderResults();
            return;
        }

        const params = new URLSearchParams();
        params.set('q', q);
        params.set('type', type);
        params.set('access', access);
        params.set('country', country);

        if (currentPersonId !== null && typeof currentPersonId !== 'undefined') {
            let personIds = Array.isArray(currentPersonId) ? currentPersonId : [currentPersonId];

            personIds = Array.from(new Set(
                personIds
                    .map(v => parseInt(v, 10))
                    .filter(v => Number.isFinite(v) && v > 0)
            ));

            if (personIds.length === 1) {
                params.set('person_id', String(personIds[0]));
            } else if (personIds.length > 1) {
                personIds.forEach(id => params.append('person_ids[]', String(id)));
            }
        }

        selectedProviders.forEach(p => params.append('providers[]', p));

        loaderEl.classList.remove('hidden');
        button.disabled = true;

        try {
            const response = await fetch('/search?' + params.toString(), {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) throw new Error('Erreur serveur');

            const data = await response.json();
            const results = Array.isArray(data.results) ? data.results : [];

            if (!results.length) {
                lastResults = [];
                resultsToolbarEl.classList.add('hidden');
                resultsEl.innerHTML = `<p class="text-slate-300 text-sm">Aucun résultat.</p>`;
                return;
            }

            lastResults = results;
            resultsToolbarEl.classList.remove('hidden');
            renderResults();

            purgeOldSearchCaches();

            setCachedResultsByKey(key, {
                key,
                q,
                type,
                country,
                personId: currentPersonId,
                access,
                providers: selectedProviders,
                results
            });

        } catch (err) {
            errorEl.textContent = err.message || 'Erreur';
            errorEl.classList.remove('hidden');
        } finally {
            loaderEl.classList.add('hidden');
            button.disabled = false;
        }
    }

    function groupProvidersByAccess(providersArray) {
        const groups = { flatrate: [], rent: [], buy: [] };

        (providersArray || []).forEach((p) => {
            const access = p.access || 'flatrate';
            if (!groups[access]) groups[access] = [];
            groups[access].push(p);
        });

        return groups;
    }

    function buildProviderUrl(title, slug) {
        const encoded = encodeURIComponent(title);

        let target = slug;
        if (slug === 'appletv' || slug === 'paramountplus') target = 'canalplus';
        if (slug === 'hbomax') target = 'prime';

        switch (target) {
            case 'netflix':    return 'https://www.netflix.com/search?q=' + encoded;
            case 'prime':      return 'https://www.primevideo.com/search?phrase=' + encoded;
            case 'disneyplus': return 'https://www.disneyplus.com/search/' + encoded;
            case 'canalplus':  return 'https://www.canalplus.com/recherche?search_query=' + encoded;
            case 'appletv':    return 'https://tv.apple.com/fr/search/' + encoded;
            case 'paramountplus': return 'https://www.paramountplus.com/search/' + encoded;
            case 'hbomax':     return 'https://play.max.com/search?q=' + encoded;
        }

        return null;
    }

    function providerChip(p, title) {
        const logo = p.logo
            ? `<img src="${p.logo}" class="w-5 h-5 rounded">`
            : `<span>${escapeHtml(p.name)}</span>`;

        const viaText =
            p.via === 'canalplus' ? 'via Canal+' :
            p.via === 'prime'     ? 'via Prime Video' :
            '';

        let color = 'bg-slate-700 border-slate-500';
        let label = '';

        if (p.access === 'flatrate') { color = 'bg-emerald-700 border-emerald-500'; label = 'Inclus'; }
        if (p.access === 'rent')     { color = 'bg-amber-700 border-amber-500'; label = 'Location'; }
        if (p.access === 'buy')      { color = 'bg-sky-700 border-sky-500'; label = 'Achat'; }

        const url = buildProviderUrl(title, p.slug) || '#';

        return `
            <a href="${url}" target="_blank" rel="noopener"
               class="flex items-center gap-2 px-2 py-1 rounded-full border text-xs ${color}">
                ${logo}
                <div class="leading-tight">
                    <div>${escapeHtml(p.name)}</div>
                    <div class="text-[10px] uppercase">${label}${viaText ? ' · ' + viaText : ''}</div>
                </div>
            </a>
        `;
    }

    function renderCard(item) {
        const poster = item.poster
            ? `<img src="${escapeHtml(item.poster)}" alt="${escapeHtml(item.title || '')}" loading="lazy" decoding="async" class="w-full h-56 object-cover rounded-t-lg">`
            : `<div class="w-full h-56 flex items-center justify-center bg-slate-700 rounded-t-lg">Aucune image</div>`;

        const providersArray = item.providers || [];
        const groups = groupProvidersByAccess(providersArray);

        const hasRent = !!item.has_rent;
        const hasBuy  = !!item.has_buy;

        let providersHtml = '';

        if (providersArray.length === 0) {
            if (hasRent || hasBuy) {
                providersHtml = `
                    <span class="text-xs text-slate-400">
                        Non disponible sur vos plateformes sélectionnées, mais proposé en location et/ou à l'achat sur d'autres services.
                    </span>
                `;
            } else {
                providersHtml = `
                    <span class="text-xs text-slate-400">
                        Aucune disponibilité trouvée sur vos plateformes sélectionnées.
                    </span>
                `;
            }
        } else {
            const sections = [];

            if (groups.flatrate.length) {
                sections.push(`
                    <div class="w-full">
                        <div class="text-[11px] text-emerald-400 mb-1 uppercase">Inclus</div>
                        <div class="flex flex-wrap gap-2">
                            ${groups.flatrate.map(p => providerChip(p, item.title)).join('')}
                        </div>
                    </div>
                `);
            }

            if (groups.rent.length) {
                sections.push(`
                    <div class="w-full">
                        <div class="text-[11px] text-amber-300 mb-1 uppercase">Location</div>
                        <div class="flex flex-wrap gap-2">
                            ${groups.rent.map(p => providerChip(p, item.title)).join('')}
                        </div>
                    </div>
                `);
            }

            if (groups.buy.length) {
                sections.push(`
                    <div class="w-full">
                        <div class="text-[11px] text-sky-300 mb-1 uppercase">Achat</div>
                        <div class="flex flex-wrap gap-2">
                            ${groups.buy.map(p => providerChip(p, item.title)).join('')}
                        </div>
                    </div>
                `);
            }

            providersHtml = sections.join('<div class="h-2"></div>');
        }

        const overview = item.overview
            ? escapeHtml(String(item.overview).slice(0, 140)) + '...'
            : 'Pas de description.';

        const genres = Array.isArray(item.genres) ? item.genres : [];
        let genresHtml = '';

        if (genres.length) {
            genresHtml = `
                <div class="flex flex-wrap gap-1 mt-1">
                    ${genres.slice(0, 4).map(g => `
                        <span class="px-2 py-0.5 rounded-full bg-slate-700 text-[10px] text-slate-200">
                            ${escapeHtml(g)}
                        </span>
                    `).join('')}
                </div>
            `;
        }

        const star = item.in_watchlist ? '⭐' : '☆';

        return `
            <div class="vod-media-card bg-slate-800 border border-slate-700 rounded-lg overflow-hidden shadow text-sm">
                <button type="button" class="w-full text-left" onclick="openPopup('${item.type}', '${item.id}', true, '${item.country || currentCountry()}')">
                    ${poster}
                </button>

                <div class="p-3 space-y-2">
                    <h2 class="font-semibold leading-tight flex items-center justify-between gap-2">
                        <button type="button"
                                class="hover:underline text-left flex-1"
                                onclick="openPopup('${item.type}', '${item.id}', true, '${item.country || currentCountry()}')">
                            ${escapeHtml(item.title)}
                            ${item.year ? `<span class="text-xs text-slate-400"> (${item.year})</span>` : ''}
                        </button>

                        <button type="button"
                                class="ml-2 text-lg watchlist-toggle"
                                data-watchlist-button="1"
                                data-id="${item.id}"
                                data-type="${item.type}"
                                data-title="${escapeHtml(item.title)}"
                                data-year="${item.year ?? ''}"
                                data-poster="${item.poster ?? ''}"
                                data-in="${item.in_watchlist ? '1' : '0'}">
                            ${star}
                        </button>
                    </h2>

                    ${item.release_date ? `<p class="vod-release-date text-xs text-slate-400">${item.type === 'tv' ? 'Série · Première diffusion' : 'Film · Sortie'} : ${escapeHtml(item.release_date.split('-').reverse().join('/'))}</p>` : ''}
                    ${genresHtml}

                    <p class="text-xs text-slate-300">${overview}</p>

                    <div class="space-y-2">
                        ${providersHtml}
                    </div>
                </div>
            </div>
        `;
    }

    function hasValidOverview(item) {
        const ov = item && typeof item.overview === 'string' ? item.overview.trim() : '';
        return ov.length > 0;
    }
                    
    function readStoredFormState() {
        try {
            const state = JSON.parse(localStorage.getItem(STORAGE_KEY));
            return state && typeof state === 'object' && !Array.isArray(state)
                && Array.isArray(state.providers) ? state : null;
        } catch (e) {
            return null;
        }
    }

    function applyDefaultProvidersIfNoStoredState() {
        if (readStoredFormState()) return;

        const defaults = new Set(DEFAULT_PROVIDER_SLUGS.map(String));
        document.querySelectorAll('.provider-checkbox').forEach(cb => {
            const slug = cb.getAttribute('data-slug') || cb.value;
            cb.checked = IS_AUTH ? defaults.has(slug) : true;
        });
        saveFormState();
    }


    function renderResults() {
        if (!Array.isArray(lastResults)) {
            resultsEl.innerHTML = '';
            return;
        }

        let items = [...lastResults];

        if (hideNoOverview) {
            items = items.filter(hasValidOverview);
        }

        if (flatrateOnlyEl && flatrateOnlyEl.checked) {
            items = items.filter(item => {
                const providersArray = item.providers || [];
                return providersArray.some(p => p.access === 'flatrate');
            });
        }

        const sort = sortSelectEl ? sortSelectEl.value : 'relevance';

        if (sort !== 'relevance') {
            items.sort((a, b) => {
                const titleA = (a.title || '').toLowerCase();
                const titleB = (b.title || '').toLowerCase();
                const yearA = a.year ? parseInt(a.year, 10) : 0;
                const yearB = b.year ? parseInt(b.year, 10) : 0;

                switch (sort) {
                    case 'year_desc': return (yearB || 0) - (yearA || 0);
                    case 'year_asc':  return (yearA || 0) - (yearB || 0);
                    case 'title_az':  return titleA.localeCompare(titleB);
                    case 'title_za':  return titleB.localeCompare(titleA);
                    default: return 0;
                }
            });
        }

        if (!items.length) {
            if (hideNoOverview && Array.isArray(lastResults) && lastResults.length > 0) {
                resultsEl.innerHTML = `
                    <div class="bg-slate-800 border border-slate-700 rounded-lg p-4 text-sm text-slate-200">
                        <div class="mb-2">Aucun contenu avec une description disponible.</div>
                        <button type="button"
                                id="show-without-overview"
                                class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-xs font-semibold">
                            Afficher quand même les contenus sans descriptif
                        </button>
                    </div>
                `;

                const btn = document.getElementById('show-without-overview');
                if (btn) {
                    btn.addEventListener('click', () => {
                        hideNoOverview = false;
                        renderResults();
                    });
                }
                return;
            }

            resultsEl.innerHTML = `<p class="text-slate-300 text-sm">Aucun résultat.</p>`;
            return;
        }

        resultsEl.innerHTML = items.map(renderCard).join('');
    }

    /* ------------------------------- */
    /* WATCHLIST TOGGLE                */
    /* ------------------------------- */

    async function toggleWatchlist(buttonEl) {
        if (!IS_AUTH) {
            window.location.href = '/login';
            return;
        }

        const tmdbId = buttonEl.dataset.id;
        const type   = buttonEl.dataset.type;
        const title  = buttonEl.dataset.title || '';
        const year   = buttonEl.dataset.year || '';
        const poster = buttonEl.dataset.poster || '';

        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (!tokenMeta) return;

        const csrfToken = tokenMeta.getAttribute('content');

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

            if (!res.ok) return;

            const data = await res.json();
            const inWatchlist = !!data.in_watchlist;

            buttonEl.dataset.in = inWatchlist ? '1' : '0';
            buttonEl.textContent = inWatchlist ? '⭐' : '☆';

        } catch (e) {
            console.error(e);
        }
    }

    /* ------------------------------- */
    /* EVENT LISTENERS                 */
    /* ------------------------------- */

    qInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            hideAutocomplete();
            smartEnterSearch();
        }
    });

    button.addEventListener('click', (e) => doSearch(e));

    qInput.addEventListener('input', handleAutocompleteInput);
    qInput.addEventListener('input', () => {
        if (!qInput.value.trim()) {
            resultsEl.innerHTML = '';
            lastResults = [];
            resultsToolbarEl.classList.add('hidden');
        }
        showHomeReleases();
    });
    autocompleteEl.addEventListener('click', handleAutocompleteClick);

    document.addEventListener('click', function (e) {
        if (!autocompleteEl.contains(e.target) && e.target !== qInput) {
            hideAutocomplete();
        }
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-watchlist-button]');
        if (btn) toggleWatchlist(btn);
    });

    document.getElementById('q').addEventListener('input', saveFormState);
    document.getElementById('type').addEventListener('change', saveFormState);
    document.getElementById('country').addEventListener('change', saveFormState);

    document.querySelectorAll('input[name="access"]').forEach(radio => {
        radio.addEventListener('change', saveFormState);
    });

    document.querySelectorAll('.provider-checkbox').forEach(cb => {
        cb.addEventListener('change', saveFormState);
    });

    if (sortSelectEl) sortSelectEl.addEventListener('change', renderResults);
    if (flatrateOnlyEl) flatrateOnlyEl.addEventListener('change', renderResults);

    /* ------------------------------- */
    /* POPIN (version minimale)        */
    /* ------------------------------- */

    let modalOpen = false;

    async function openPopup(type, id, push = true, countryOverride = null) {
        const overlay = document.getElementById('modal-overlay');
        const country = (countryOverride || currentCountry()).toUpperCase();

        overlay.innerHTML =
            `<div class="flex items-center justify-center h-full">
                <div class="text-white text-sm">Chargement...</div>
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
            if (closeBtn) closeBtn.onclick = () => closePopup(true);

            const container = document.getElementById('popup-container');
            if (container) {
                container.addEventListener('click', (e) => {
                    if (e.target.id === 'popup-container') closePopup(true);
                });
            }

            document.addEventListener('keydown', escClose);

            if (push) {
                history.pushState(
                    { modal: true, type: String(type), id: Number(id), country: String(country) },
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
        if (e.key === 'Escape') closePopup(true);
    }

    function closePopup(useHistory) {
        const overlay = document.getElementById('modal-overlay');
        overlay.classList.add('hidden');
        overlay.innerHTML = '';
        document.body.style.overflow = 'auto';
        document.removeEventListener('keydown', escClose);

        const wasOpen = modalOpen;
        modalOpen = false;

        if (useHistory && wasOpen) history.back();
    }

    window.addEventListener('popstate', (event) => {
        const state = event.state;

        if (state && state.modal && state.type && state.id) {
            const country = state.country || 'FR';
            openPopup(state.type, state.id, false, country);
        } else {
            if (modalOpen) closePopup(false);
        }
    });

    document.addEventListener('change', function (e) {
        const select = e.target.matches('#season-select')
            ? e.target
            : e.target.closest('#season-select');

        if (!select) return;

        const targetId = String(select.value);
        const blocks = document.querySelectorAll('.season-block');

        blocks.forEach(block => {
            const blockId = String(block.dataset.seasonId || block.dataset.season || '');
            block.classList.toggle('hidden', blockId !== targetId);
        });
    });

    // Boot unique (et suffisant pour refresh + BFCache)
    restoreFormState();
    showHomeReleases();
    window.addEventListener('pageshow', (event) => { restoreFormState(); if (event.persisted) showHomeReleases(); });
</script>

</body>
</html>
