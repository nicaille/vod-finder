@php
    $title = $details['title'] ?? $details['name'] ?? '';
    $year = isset($details['release_date'])
        ? substr($details['release_date'], 0, 4)
        : (isset($details['first_air_date']) ? substr($details['first_air_date'], 0, 4) : null);

    $runtime = $details['runtime'] ?? ($details['episode_run_time'][0] ?? null);

    // durée "type" épisode pour les séries (TMDb)
    $seriesEpRuntime = $details['episode_run_time'][0] ?? null;

    $genres = $details['genres'] ?? [];
    $vote   = $details['vote_average'] ?? null;

    $credits = $details['credits'] ?? [];
    $cast = $credits['cast'] ?? [];
    $crew = $credits['crew'] ?? [];

    // Classification FR (public cible)
    $certFr = $ratingFr ?? null;
    $certLabel = null;
    if (!empty($certFr)) {
        $map = [
            'U'  => 'Tous publics',
            '10' => '10+',
            '12' => '12+',
            '16' => '16+',
            '18' => '18+',
        ];
        $certLabel = $map[$certFr] ?? $certFr;
    }

    // Producteurs
    $producers = array_values(array_filter($crew, fn($c) => in_array($c['job'] ?? '', ['Producer', 'Executive Producer'])));
    $producers = array_slice($producers, 0, 3);

    // Réalisateurs
    $directors = array_values(array_filter($crew, fn($c) => ($c['job'] ?? '') === 'Director'));
    $directors = array_slice($directors, 0, 2);

    // Casting principal
    $mainCast = array_slice($cast, 0, 5);

    $isTv = $type === 'tv';

    $seasonCount  = $details['number_of_seasons'] ?? null;
    $episodeCount = $details['number_of_episodes'] ?? null;

    // SA: clés pays en lower-case
    $countryKey = strtolower($country ?? 'FR');

    // Saisons StreamingAvailability passées par le contrôleur
    $saSeasons = (isset($seasons) && is_array($seasons)) ? $seasons : [];
    
    //Meta episodes
    $seriesEpRuntime = $details['episode_run_time'][0] ?? null;
    $episodeMeta = $episodeMeta ?? [];
@endphp

<div class="fixed inset-0 flex items-start justify-center bg-black/70 backdrop-blur-sm z-50 overflow-y-auto" 
     id="popup-container" 
     data-tmdb-id="{{ $details['id'] ?? '' }}"
     data-type="{{ $isTv ? 'tv' : 'movie' }}">

    <div class="bg-slate-900 text-slate-100 max-w-3xl w-full mt-12 mb-12 rounded-lg shadow-xl overflow-hidden animate-fadeIn"
         id="popup-content">

        @auth<div class="p-3"><a class="vod-social-button" href="{{ route('recommendations.compose',['type'=>$type,'id'=>$details['id']]) }}">Recommander à un contact</a></div>@endauth
        {{-- HEADER IMAGE + CLOSE --}}
        <div class="relative">
            @include('partials.detail-image')

            <button class="absolute top-4 right-4 bg-black/60 hover:bg-black/80 text-white p-2 rounded-full"
                    id="popup-close">
                ✕
            </button>
        </div>

        {{-- CONTENU --}}
        <div class="p-5 space-y-4 text-sm">
            
            {{-- Titre, année, tagline + actions --}}
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <h1 class="text-xl font-bold truncate">
                        {{ $title }}
                        @if($year)
                            <span class="text-slate-400 text-sm">({{ $year }})</span>
                        @endif
                    </h1>

                    @if(!empty($details['tagline']))
                        <p class="mt-1 text-slate-300 italic">{{ $details['tagline'] }}</p>
                    @endif
                </div>

                @auth
                    <div class="flex flex-col items-end gap-2 text-xs">

                        @if($isTv)
                            @php
                                $seriesFollow = auth()->user()->seriesFollows()->whereHas('series', fn ($query) => $query->where('tmdb_id', $details['id']))->first();
                            @endphp
                            @if($seriesFollow)
                                <a href="{{ route('series.index') }}" class="px-2 py-1 rounded-full border border-indigo-400 text-indigo-300">✓ Série suivie</a>
                            @else
                                <form action="{{ route('series.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="tmdb_id" value="{{ $details['id'] }}">
                                    <button type="submit" class="px-2 py-1 rounded-full border border-indigo-400 text-indigo-300 hover:bg-slate-700">Suivre la série</button>
                                </form>
                            @endif
                        @endif

                        {{-- Bouton coup de cœur --}}
                        @php
                            $favActive = !empty($isFavorite);
                        @endphp
                        <button type="button"
                                data-favorite-btn
                                class="px-2 py-1 rounded-full border text-xs
                                       {{ $favActive
                                            ? 'bg-pink-500 border-pink-500 text-slate-900'
                                            : 'border-pink-500 text-pink-500 hover:bg-pink-500 hover:text-slate-900' }}">
                            ❤️ Coup de cœur
                        </button>

                        {{-- Bouton "Ajouter à une liste" + panneau --}}
                        <div class="relative" data-add-to-list-wrapper>
                            <button type="button"
                                    data-open-list-menu
                                    class="px-2 py-1 rounded-full border border-slate-500 text-slate-200 hover:bg-slate-700">
                                ➕ Ajouter à une liste
                            </button>

                            <div class="absolute right-0 mt-2 w-64 bg-slate-800 border border-slate-700 rounded-lg shadow-lg text-xs hidden"
                                 data-list-panel>
                                {{-- Listes existantes --}}
                                @if(!empty($userLists) && count($userLists))
                                    <div class="px-3 py-2 border-b border-slate-700">
                                        <div class="text-[11px] text-slate-300 mb-1">
                                            Ajouter à une liste existante :
                                        </div>
                                        @foreach($userLists as $list)
                                            <button type="button"
                                                    class="w-full text-left px-2 py-1.5 rounded hover:bg-slate-700"
                                                    data-add-to-list
                                                    data-list-id="{{ $list->id }}">
                                                {{ $list->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="px-3 py-2 border-b border-slate-700 text-[11px] text-slate-300">
                                        Vous n'avez encore aucune liste.
                                    </div>
                                @endif

                                {{-- Création d'une nouvelle liste --}}
                                <div class="px-3 py-2" data-create-list-panel>
                                    <div class="text-[11px] text-slate-300 mb-1">
                                        Créer une nouvelle liste :
                                    </div>
                                    <form data-create-list-form class="space-y-2">
                                        <input type="text"
                                               name="name"
                                               class="w-full bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs"
                                               placeholder="Nom de la liste"
                                               required>

                                        <label class="flex items-center gap-1 text-[11px] text-slate-300">
                                            <input type="checkbox" name="is_public" class="rounded border-slate-500 text-xs">
                                            <span>Liste publique</span>
                                        </label>

                                        <button type="submit"
                                                class="w-full mt-1 px-2 py-1 rounded bg-indigo-600 hover:bg-indigo-500 text-[11px] font-semibold">
                                            Créer la liste et ajouter ce titre
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>



            {{-- Infos principales --}}
            <div class="flex flex-wrap gap-4 text-slate-200 text-xs">
                @if($runtime)
                    <div>Durée : {{ $runtime }} min</div>
                @endif

                @if($certLabel)
                    <div>Public : {{ $certLabel }}</div>
                @endif

                @if($isTv)
                    <div>
                        Série :
                        @if($seasonCount) {{ $seasonCount }} saison(s) @endif
                        @if($episodeCount) - {{ $episodeCount }} épisode(s) @endif
                    </div>
                @endif

                @if(!empty($genres))
                    <div>
                        Genres :
                        @foreach($genres as $g)
                            <span class="inline-block px-2 py-0.5 rounded-full bg-slate-800 text-[11px] mr-1">
                                {{ $g['name'] }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if($vote)
                    <div>Note TMDb : {{ number_format($vote, 1) }}/10</div>
                @endif
            </div>

            {{-- Réalisateurs / Producteurs / casting --}}
            <div class="space-y-1 text-xs text-slate-200">
                @if(!$isTv && !empty($directors))
                    <div>
                        Réalisateur(s) :
                        @foreach($directors as $d)
                            <span class="font-semibold">{{ $d['name'] }}</span>@if(!$loop->last), @endif
                        @endforeach
                    </div>
                @endif

                @if(!empty($producers))
                    <div>
                        Producteur(s) :
                        @foreach($producers as $p)
                            <span>{{ $p['name'] }}</span>@if(!$loop->last), @endif
                        @endforeach
                    </div>
                @endif

                @if(!empty($mainCast))
                    <div>
                        Avec :
                        @foreach($mainCast as $c)
                            <span>{{ $c['name'] }}</span>@if(!$loop->last), @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Synopsis --}}
            <div>
                <h2 class="uppercase text-[11px] text-slate-400 font-semibold">Synopsis</h2>
                <p class="mt-1 text-slate-100 text-sm">
                    {{ $details['overview'] ?? 'Pas de description disponible.' }}
                </p>
            </div>

            {{-- Bouton Regarder maintenant --}}
            @if($watchNow)
                @php
                    $labelAccess = [
                        'flatrate' => 'Inclus',
                        'rent'     => 'Location',
                        'buy'      => 'Achat',
                    ];

                    $access   = $watchNow['access'] ?? 'flatrate';
                    $via      = $watchNow['via']    ?? null;
                    $viaText  = \App\Support\ProviderIdentity::viaLabel($via);

                    $urlWatch = $watchNow['deeplink'] ?? ($watchNow['url'] ?? '#');

                    $priceText = '';
                    if (!empty($watchNow['sa_price']['amount'])) {
                        $amount   = $watchNow['sa_price']['amount'];
                        $currency = $watchNow['sa_price']['currency'] ?? 'EUR';
                        $priceText = number_format($amount, 2, ',', ' ') . ' ' . $currency;
                    }
                @endphp

                <div class="mt-2">
                    <h2 class="uppercase text-[11px] text-slate-400 font-semibold mb-1">Regarder maintenant</h2>

                    <a href="{{ $urlWatch }}"
                       target="_blank" rel="noopener"
                       class="inline-flex items-center gap-3 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold">
                        @if(!empty($watchNow['logo']))
                            <img src="{{ $watchNow['logo'] }}" class="w-6 h-6 rounded" alt="{{ $watchNow['name'] }}">
                        @endif
                        <span>
                            {{ $labelAccess[$access] ?? ucfirst($access) }} sur {{ $watchNow['name'] }}
                            @if($viaText)
                                <span class="text-xs font-normal">({{ $viaText }})</span>
                            @endif
                            @if($priceText)
                                <span class="text-xs font-normal"> · {{ $priceText }}</span>
                            @endif
                        </span>
                    </a>
                </div>
            @endif

            {{-- Tous les providers --}}
            @if(!empty($providers))
                @php
                    $providersFlatrate = array_values(array_filter($providers, fn($p) => ($p['access'] ?? 'flatrate') === 'flatrate'));
                    $providersRent     = array_values(array_filter($providers, fn($p) => ($p['access'] ?? 'flatrate') === 'rent'));
                    $providersBuy      = array_values(array_filter($providers, fn($p) => ($p['access'] ?? 'flatrate') === 'buy'));
                @endphp

                <div class="mt-4 space-y-3 text-xs">
                    @if(!empty($providersFlatrate))
                        <div>
                            <h2 class="uppercase text-[11px] text-emerald-400 font-semibold mb-1">Inclus dans vos abonnements</h2>
                            <div class="flex flex-wrap gap-2">
                                @foreach($providersFlatrate as $p)
                                    @php
                                        $viaText = \App\Support\ProviderIdentity::viaLabel($p['via'] ?? null);
                                        $url = $p['deeplink'] ?? ($p['url'] ?? '#');
                                        $priceText = '';
                                        if (!empty($p['sa_price']['amount'])) {
                                            $amount   = $p['sa_price']['amount'];
                                            $currency = $p['sa_price']['currency'] ?? 'EUR';
                                            $priceText = number_format($amount, 2, ',', ' ') . ' ' . $currency;
                                        }
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="flex items-center gap-2 px-2 py-1 rounded-full bg-slate-800 border border-emerald-500">
                                        @if($p['logo'])
                                            <img src="{{ $p['logo'] }}" class="w-5 h-5 rounded" alt="{{ $p['name'] }}">
                                        @endif
                                        <div class="flex flex-col leading-tight text-left">
                                            <span>{{ $p['name'] }}</span>
                                            @if($viaText || $priceText)
                                                <span class="text-[10px]">{{ implode(' · ', array_filter([$viaText, $priceText])) }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($providersRent))
                        <div>
                            <h2 class="uppercase text-[11px] text-amber-300 font-semibold mb-1">Location</h2>
                            <div class="flex flex-wrap gap-2">
                                @foreach($providersRent as $p)
                                    @php
                                        $viaText = \App\Support\ProviderIdentity::viaLabel($p['via'] ?? null);
                                        $url = $p['deeplink'] ?? ($p['url'] ?? '#');
                                        $priceText = '';
                                        if (!empty($p['sa_price']['amount'])) {
                                            $amount   = $p['sa_price']['amount'];
                                            $currency = $p['sa_price']['currency'] ?? 'EUR';
                                            $priceText = number_format($amount, 2, ',', ' ') . ' ' . $currency;
                                        }
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="flex items-center gap-2 px-2 py-1 rounded-full bg-slate-800 border border-amber-500">
                                        @if($p['logo'])
                                            <img src="{{ $p['logo'] }}" class="w-5 h-5 rounded" alt="{{ $p['name'] }}">
                                        @endif
                                        <div class="flex flex-col leading-tight text-left">
                                            <span>{{ $p['name'] }}</span>
                                            @if($viaText || $priceText)
                                                <span class="text-[10px]">{{ implode(' · ', array_filter([$viaText, $priceText])) }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!empty($providersBuy))
                        <div>
                            <h2 class="uppercase text-[11px] text-sky-300 font-semibold mb-1">Achat</h2>
                            <div class="flex flex-wrap gap-2">
                                @foreach($providersBuy as $p)
                                    @php
                                        $viaText = \App\Support\ProviderIdentity::viaLabel($p['via'] ?? null);
                                        $url = $p['deeplink'] ?? ($p['url'] ?? '#');
                                        $priceText = '';
                                        if (!empty($p['sa_price']['amount'])) {
                                            $amount   = $p['sa_price']['amount'];
                                            $currency = $p['sa_price']['currency'] ?? 'EUR';
                                            $priceText = number_format($amount, 2, ',', ' ') . ' ' . $currency;
                                        }
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" rel="noopener"
                                       class="flex items-center gap-2 px-2 py-1 rounded-full bg-slate-800 border border-sky-500">
                                        @if($p['logo'])
                                            <img src="{{ $p['logo'] }}" class="w-5 h-5 rounded" alt="{{ $p['name'] }}">
                                        @endif
                                        <div class="flex flex-col leading-tight text-left">
                                            <span>{{ $p['name'] }}</span>
                                            @if($viaText || $priceText)
                                                <span class="text-[10px]">{{ implode(' · ', array_filter([$viaText, $priceText])) }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ÉPISODES (style Netflix) --}}
@if($isTv && count($saSeasons))
    <div class="mt-8 border-t border-slate-800 pt-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-slate-100">Épisodes</h3>
                @if($seasonCount)
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ $seasonCount }} saison{{ $seasonCount > 1 ? 's' : '' }}
                        @if($episodeCount)
                            · {{ $episodeCount }} épisode{{ $episodeCount > 1 ? 's' : '' }}
                        @endif
                    </p>
                @endif
            </div>

            <div>
                <label class="sr-only" for="season-select">Saison</label>
                <div class="relative text-xs">
                    <select id="season-select"
                            class="appearance-none bg-slate-800 border border-slate-600 rounded px-3 py-2 pr-8 text-xs text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-300 focus:border-slate-300 cursor-pointer">
                        @foreach($saSeasons as $idx => $season)
                            @php
                                $label = $season['title'] ?? ('Saison '.($idx + 1));
                            @endphp
                            <option value="season-{{ $idx }}" @if($idx === 0) selected @endif>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-slate-300">
                        ▾
                    </span>
                </div>
            </div>
        </div>

        @php
            // Deeplinks de la SÉRIE depuis SA (saShow.streamingOptions[country])
            $seriesSaProviders = [];
            if (!empty($saShow['streamingOptions'][$countryKey]) && is_array($saShow['streamingOptions'][$countryKey])) {
                foreach ($saShow['streamingOptions'][$countryKey] as $opt) {
                    if (!is_array($opt)) continue;

                    $svc  = $opt['service']['name'] ?? 'Streaming';
                    $type = $opt['type'] ?? 'subscription';
                    $link = $opt['link'] ?? null;

                    if (!$link) continue;

                    $seriesSaProviders[] = [
                        'name' => $svc,
                        'type' => $type,
                        'link' => $link,
                    ];
                }
            }
        @endphp

        @foreach($saSeasons as $idx => $season)
            @php
                $episodesSa   = is_array($season['episodes'] ?? null) ? $season['episodes'] : [];
                $seasonNumber = $season['seasonNumber'] ?? ($idx + 1);
            @endphp

            <div class="season-block space-y-4 @if($idx !== 0) hidden @endif"
                 data-season-id="season-{{ $idx }}">

                @foreach($episodesSa as $epIndex => $ep)
                    @php
                        // Numéro d'épisode côté SA (fallback index+1)
                        $epNum = $ep['episodeNumber'] ?? ($epIndex + 1);

                        // Meta TMDb éventuelle pour cet épisode
                        $meta = $episodeMeta[$seasonNumber][$epNum] ?? null;

                        // Titre: TMDb (name) > SA (title) > fallback
                        $epTitle = $meta['name']
                            ?? ($ep['title'] ?? 'Épisode '.($epIndex + 1));

                        // Overview: TMDb > SA > null
                        $epOverview = $meta['overview']
                            ?? ($ep['overview'] ?? null);

                        // runtime: TMDb par épisode > SA > runtime moyen série
                        $epRuntime =
                            ($meta['runtime'] ?? null)
                            ?? ($ep['runtime'] ?? $seriesEpRuntime ?? null);

                        // L'épisode a-t-il déjà été diffusé ?
                        $hasAired = false;
                        $airDate = null;
                        if (!empty($meta['air_date'])) {
                            try {
                                $dateValue = $meta['air_date'];
                                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateValue, $dateParts)
                                    && checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
                                    $airDate = \Carbon\CarbonImmutable::parse($dateValue, 'Europe/Paris')->startOfDay();
                                    $hasAired = $airDate->lessThanOrEqualTo(\Carbon\CarbonImmutable::now('Europe/Paris')->startOfDay());
                                }
                            } catch (\Exception $e) {
                                $hasAired = false;
                            }
                        }

                        // Providers de l'épisode via streamingOptions[country]
                        $epProviders = [];
                        if (!empty($ep['streamingOptions'][$countryKey]) && is_array($ep['streamingOptions'][$countryKey])) {
                            foreach ($ep['streamingOptions'][$countryKey] as $opt) {
                                if (!is_array($opt)) continue;

                                $svc  = $opt['service']['name'] ?? 'Streaming';
                                $type = $opt['type'] ?? 'subscription';
                                $link = $opt['link'] ?? null;

                                if (!$link) continue;

                                $epProviders[] = [
                                    'name'           => $svc,
                                    'type'           => $type,
                                    'link'           => $link,
                                    'is_series_link' => false, // lien EPISODE
                                ];
                            }
                        }

                        // Fallback : si aucun deeplink épisode, que l'épisode a été diffusé,
                        // et que la SÉRIE a des streamingOptions SA, on les utilise.
                        if (empty($epProviders) && $hasAired && !empty($seriesSaProviders)) {
                            foreach ($seriesSaProviders as $s) {
                                $epProviders[] = [
                                    'name'           => $s['name'],
                                    'type'           => $s['type'],
                                    'link'           => $s['link'],
                                    'is_series_link' => true, // lien SÉRIE
                                ];
                            }
                        }

                        // Thumb: still TMDb > backdrop > poster
                        $thumb = null;
                        if (!empty($meta['still_path'])) {
                            $thumb = 'https://image.tmdb.org/t/p/w300'.$meta['still_path'];
                        } elseif (!empty($details['backdrop_path'])) {
                            $thumb = 'https://image.tmdb.org/t/p/w300'.$details['backdrop_path'];
                        } elseif (!empty($details['poster_path'])) {
                            $thumb = 'https://image.tmdb.org/t/p/w300'.$details['poster_path'];
                        }
                    @endphp

                    <div class="episode-row flex gap-4 py-4 border-t border-slate-800 first:border-t-0">
                        {{-- numéro --}}
                        <div class="w-6 flex-shrink-0 flex items-start justify-center text-sm text-slate-400 pt-1">
                            {{ $epNum }}
                        </div>

                        {{-- thumb épisode --}}
                        <div class="episode-thumbnail w-32 h-20 flex-shrink-0 bg-slate-800 rounded-md overflow-hidden">
                            @if($thumb)
                                <img src="{{ $thumb }}"
                                     alt="{{ $epTitle }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-xs text-slate-400">
                                    Aucune image
                                </div>
                            @endif
                        </div>

                        {{-- infos épisode --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="text-sm font-semibold text-slate-100 truncate">
                                    {{ $epTitle }}
                                </div>
                                @if($epRuntime)
                                    <div class="text-xs text-slate-400 flex-shrink-0">
                                        {{ $epRuntime }} min
                                    </div>
                                @endif
                            </div>

                            @if($epOverview)
                                <p class="mt-1 text-[11px] text-slate-300 leading-snug line-clamp-3">
                                    {{ $epOverview }}
                                </p>
                            @endif
                        </div>

                        {{-- providers épisode --}}
                        <div class="episode-options w-40 flex-shrink-0 flex flex-col items-end justify-center gap-1">
                            @if($airDate && !$hasAired)
                                <time datetime="{{ $airDate->toDateString() }}"
                                      class="inline-flex px-2 py-1 rounded-full border border-indigo-400 text-[10px] text-indigo-300 text-right">
                                    Diffusion prévue le {{ $airDate->format('d/m/Y') }}
                                </time>
                            @elseif(!$airDate && empty($epProviders))
                                <span class="inline-flex px-2 py-1 rounded-full border border-slate-600 text-[10px] text-slate-400 text-right">Date de diffusion non annoncée</span>
                            @else
                            @forelse(array_slice($epProviders, 0, 3) as $p)
                                @php
                                    $labelType = match($p['type']) {
                                        'subscription' => 'Inclus',
                                        'rent'         => 'Location',
                                        'buy'          => 'Achat',
                                        default        => ucfirst($p['type']),
                                    };

                                    $isSeriesLink = !empty($p['is_series_link']);
                                @endphp

                                <a href="{{ $p['link'] }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="inline-flex items-center justify-end gap-1 px-2 py-1 rounded-full border border-slate-600 text-[10px] bg-slate-900 hover:bg-slate-800 text-slate-100">
                                    <span class="truncate max-w-[7rem] text-right">
                                        {{ $p['name'] }}
                                    </span>
                                    <span class="uppercase text-[9px] text-slate-300">
                                        {{ $labelType }}
                                        @if($isSeriesLink)
                                            · lien série
                                        @endif
                                    </span>
                                </a>
                            @empty
                                <span class="text-[10px] text-slate-500">
                                    Aucune info de visionnage
                                </span>
                            @endforelse
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endif



            {{-- Suggestions --}}
            @if(!empty($reco))
                <div class="vod-suggestions">
                    <h2 class="vod-suggestions-heading uppercase text-[11px] text-slate-400 font-semibold">Suggestions</h2>

                    <div class="suggestions-row pb-2">
                        @foreach(array_slice($reco, 0, 18) as $r)
                            @php
                                $rid = $r['id'] ?? null;
                                $rtype = isset($r['name']) ? 'tv' : 'movie';
                                $rtitle = $rtype === 'tv' ? ($r['name'] ?? '') : ($r['title'] ?? '');
                                $rposter = $r['poster_path'] ?? null;
                            @endphp

                            @if($rid && $rtitle)
                                <button type="button"
                                        class="suggestion-item text-left text-[11px] text-slate-200"
                                        onclick="openPopup('{{ $rtype }}', '{{ $rid }}', true)">
                                    <div class="relative w-full rounded-lg overflow-hidden border border-slate-700 bg-slate-800">
                                        @if($rposter)
                                            <img src="https://image.tmdb.org/t/p/w300{{ $rposter }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $rtitle }}">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center">
                                                ?
                                            </div>
                                        @endif
                                    </div>
                                    <span class="mt-1 block line-clamp-2 leading-snug">
                                        {{ $rtitle }}
                                    </span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn .22s ease-out;
}

/* Carrousel suggestions */
.suggestions-row {
    display: flex;
    gap: 0.75rem;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 0.25rem;
}

.suggestions-row::-webkit-scrollbar {
    height: 6px;
}
.suggestions-row::-webkit-scrollbar-thumb {
    background-color: rgba(148, 163, 184, 0.7);
    border-radius: 9999px;
}
.suggestions-row::-webkit-scrollbar-track {
    background-color: transparent;
}

.suggestion-item {
    scroll-snap-align: start;
    flex: 0 0 70%;
    max-width: 70%;
}

/* ≥ 480px ~ 2 visibles */
@media (min-width: 480px) {
    .suggestion-item {
        flex-basis: 50%;
        max-width: 50%;
    }
}

/* ≥ 640px ~ 3 visibles */
@media (min-width: 640px) {
    .suggestion-item {
        flex-basis: 33.3333%;
        max-width: 33.3333%;
    }
}

/* ≥ 768px ~ 4 visibles */
@media (min-width: 768px) {
    .suggestion-item {
        flex-basis: 25%;
        max-width: 25%;
    }
}

/* ≥ 1024px ~ 6 visibles */
@media (min-width: 1024px) {
    .suggestion-item {
        flex-basis: 16.6667%;
        max-width: 16.6667%;
    }
}
</style>
