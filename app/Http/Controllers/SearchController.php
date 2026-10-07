<?php

namespace App\Http\Controllers;

use App\Models\WatchlistItem;
use App\Models\MediaList;
use App\Models\Favorite;
use App\Services\TmdbService;
use App\Services\StreamingAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use App\Support\ExternalApiClient;
use App\Support\SearchRelevance;
use App\Support\PersonIdentityGroups;

class SearchController extends Controller
{
    public function index()
    {
        $defaultProviderSlugs = [];

        if (auth()->check()) {
            $defaultProviderSlugs = auth()->user()
                ->platformSubscriptions()
                ->wherePivot('is_active', true)
                ->pluck('platforms.slug')
                ->map(fn ($s) => (string) $s)
                ->values()
                ->all();
        }

        return view('search', [
            'defaultProviderSlugs' => $defaultProviderSlugs,
        ]);
    }

    public function search(Request $request, TmdbService $tmdb)
    {
        $q         = trim($request->input('q', ''));
        $type      = $request->input('type', 'movie');        // movie | tv
        $country   = strtoupper($request->input('country', 'FR'));
        $access    = $request->input('access', 'all');        // all | flatrate | rent | buy
        $providers = (array) $request->input('providers', []);

        // Person IDs:
        // - compat legacy: person_id = "123" ou "123|456|789"
        // - nouveau front: person_ids[] = [123,456,789]
        $personIds = [];

        // Nouveau: person_ids[]
        $personIdsRawArr = $request->input('person_ids', []);
        if (is_array($personIdsRawArr)) {
            foreach ($personIdsRawArr as $v) {
                if (is_numeric($v)) {
                    $personIds[] = (int) $v;
                }
            }
        }

        // Legacy: person_id string
        $personRaw = trim((string) $request->input('person_id', ''));
        if ($personRaw !== '') {
            $parts = preg_split('/[|,;\s]+/', $personRaw) ?: [];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '' && ctype_digit($p)) {
                    $personIds[] = (int) $p;
                }
            }
        }

        $personIds = array_values(array_unique(array_filter($personIds, fn ($id) => $id > 0)));

        // 1) Résultats TMDb (bruts)
        if (!empty($personIds)) {

            $byKey = [];

            foreach ($personIds as $pid) {
                $credits = $tmdb->getPersonCombinedCredits($pid);

                foreach (array_merge($credits['cast'] ?? [], $credits['crew'] ?? []) as $it) {
                    if (!is_array($it)) continue;

                    $mediaType = $it['media_type'] ?? null;
                    if ($mediaType !== $type) continue;

                    $id = (int) ($it['id'] ?? 0);
                    if ($id <= 0) continue;

                    $byKey[$mediaType . ':' . $id] = $it; // dédoublonnage early
                }
            }

            $rawResults = array_values($byKey);

            // Tri filmographie (récent -> ancien)
            usort($rawResults, function ($a, $b) use ($type) {
                $da = $type === 'tv'
                    ? ($a['first_air_date'] ?? '0000-00-00')
                    : ($a['release_date'] ?? '0000-00-00');

                $db = $type === 'tv'
                    ? ($b['first_air_date'] ?? '0000-00-00')
                    : ($b['release_date'] ?? '0000-00-00');

                return strcmp($db, $da);
            });

        } else {
            if ($q === '') {
                return response()->json(['results' => []]);
            }

            $rawResults = SearchRelevance::rank($tmdb->search($q, $type), $q);
        }

        // 2) Dédoublonnage (sécurité, surtout côté search())
        $seen = [];
        $rawResults = array_values(array_filter($rawResults, function ($r) use (&$seen, $type) {
            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0) return false;

            $k = $type . ':' . $id;
            if (isset($seen[$k])) return false;
            $seen[$k] = true;

            return true;
        }));

        // 3) Hydrate providers + filtre plateformes / type d'accès (UNE SEULE FOIS)
        $rawResults = $tmdb->hydrateAndFilterByProvidersAccess(
            results: $rawResults,
            type: $type,
            country: $country,
            selectedProviderSlugs: $providers,
            access: $access
        );

        // 4) Normalisation pour le front
        $user = $request->user();
        $inWatchlist = [];

        if ($user) {
            $inWatchlist = WatchlistItem::where('user_id', $user->id)
                ->where('type', $type)
                ->pluck('tmdb_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $results = collect($rawResults)->map(function (array $r) use ($type, $inWatchlist) {
            $id      = (int) ($r['id'] ?? 0);
            $title   = $r['title'] ?? $r['name'] ?? 'Sans titre';
            $date    = $r['release_date'] ?? $r['first_air_date'] ?? null;
            $year    = $date ? substr($date, 0, 4) : null;
            $poster  = !empty($r['poster_path'])
                ? 'https://image.tmdb.org/t/p/w342' . $r['poster_path']
                : null;

            return [
                'id'           => $id,
                'type'         => $type,
                'title'        => $title,
                'year'         => $year,
                'poster'       => $poster,
                'overview'     => $r['overview'] ?? null,

                'providers'    => $r['providers'] ?? [],
                'has_rent'     => (bool) ($r['has_rent'] ?? false),
                'has_buy'      => (bool) ($r['has_buy'] ?? false),

                'in_watchlist' => in_array($id, $inWatchlist, true),
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }

    public function recentReleases(Request $request, TmdbService $tmdb)
    {
        $user = $request->user();
        if (!$user) return response()->json(['results' => [], 'reason' => 'guest']);
        $slugs = $user->platformSubscriptions()->wherePivot('is_active', true)->pluck('platforms.slug')->all();
        if (!$slugs) return response()->json(['results' => [], 'reason' => 'no_platforms']);
        $watchlist = $user->watchlist()->get()->mapWithKeys(fn ($item) => [$item->type.':'.$item->tmdb_id => true]);
        $results = [];
        foreach (['movie', 'tv'] as $type) {
            $titles = $tmdb->discoverRecentReleases($slugs, $type);
            if ($titles === null) return response()->json(['results' => [], 'reason' => 'unavailable'], 503);
            foreach ($titles as $title) {
                $date = $title[$type === 'tv' ? 'first_air_date' : 'release_date'] ?? null;
                $results[] = [
                    'id' => (int) $title['id'], 'type' => $type, 'title' => $title['title'] ?? $title['name'] ?? 'Sans titre',
                    'release_date' => $date, 'year' => $date ? substr($date, 0, 4) : null, 'country' => 'FR',
                    'poster' => empty($title['poster_path']) ? null : 'https://image.tmdb.org/t/p/w342'.$title['poster_path'],
                    'overview' => $title['overview'] ?? null, 'providers' => $title['providers'] ?? [],
                    'in_watchlist' => (bool) ($watchlist[$type.':'.$title['id']] ?? false),
                ];
            }
        }
        usort($results, fn ($a, $b) => strcmp($b['release_date'] ?? '', $a['release_date'] ?? ''));
        return response()->json(['results' => array_slice($results, 0, 24)]);
    }


    public function autocomplete(Request $request, TmdbService $tmdb)
    {
        $q = trim((string) $request->get('q', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        try {
            $raw = SearchRelevance::rank($tmdb->searchMulti($q), $q);

            $moviesTv = [];
            $people = [];

            // 1) Collecte SANS break (sinon on rate des personnes à dédupliquer)
            foreach ($raw as $item) {
                if (!is_array($item)) continue;

                $mediaType = $item['media_type'] ?? null;

                if ($mediaType === 'person') {
                    $name = (string) ($item['name'] ?? '');
                    $id   = (int) ($item['id'] ?? 0);
                    if ($name === '' || $id <= 0) continue;

                    $knownTitles = [];
                    foreach (($item['known_for'] ?? []) as $known) {
                        if (!is_array($known)) continue;
                        $knownTitle = trim((string) ($known['title'] ?? $known['name'] ?? ''));
                        if ($knownTitle !== '') $knownTitles[] = $knownTitle;
                    }

                    $people[] = [
                        'id'        => $id,
                        'name'      => $name,
                        'name_key'  => SearchRelevance::normalize($name),
                        'dept'      => (string) ($item['known_for_department'] ?? ''),
                        'gender'    => (int) ($item['gender'] ?? 0),
                        'pop'       => (float) ($item['popularity'] ?? 0),
                        'profile'   => (string) ($item['profile_path'] ?? ''),
                        'known_titles' => array_values(array_unique($knownTitles)),
                    ];

                    continue;
                }

                if (!in_array($mediaType, ['movie', 'tv'], true)) {
                    continue;
                }

                $title = $mediaType === 'movie'
                    ? (string) ($item['title'] ?? '')
                    : (string) ($item['name'] ?? '');

                if ($title === '') continue;

                $date = $mediaType === 'movie'
                    ? ($item['release_date'] ?? null)
                    : ($item['first_air_date'] ?? null);

                $year = $date ? substr((string) $date, 0, 4) : null;

                $moviesTv[] = [
                    'id'    => $item['id'] ?? null,
                    'title' => $title,
                    'year'  => $year,
                    'type'  => $mediaType,
                ];
            }

            // Fetch identity only for names appearing more than once, before the display limit.
            $people = collect($people)->unique('id')->values()->all();
            $nameCounts = array_count_values(array_column($people, 'name_key'));
            foreach ($people as &$person) {
                $person['identity'] = ($nameCounts[$person['name_key']] ?? 0) > 1
                    ? $tmdb->getPersonIdentity($person['id']) : null;
            }
            unset($person);
            $dedupPeople = PersonIdentityGroups::group($people);

            // Correspondance du nom avant la popularité.
            $dedupPeople = SearchRelevance::rank($dedupPeople, $q);

            // 3) Quotas indépendants : les personnes ne masquent jamais les contenus.
            $peopleItems = [];
            foreach ($dedupPeople as $cl) {
                if (count($peopleItems) >= 6) break;

                $peopleItems[] = [
                    // compat back actuel: "123|456"
                    'id'    => implode('|', $cl['ids']),
                    'title' => (string) ($cl['name'] ?? ''),
                    'year'  => null,
                    'type'  => 'person',
                    'department' => (string) ($cl['dept'] ?? ''),
                    'profile' => !empty($cl['profile']) ? 'https://image.tmdb.org/t/p/w92'.$cl['profile'] : null,
                    'known_titles' => array_slice($cl['known_titles'], 0, 2),
                    'birthday' => $cl['identity']['birthday'] ?? null,

                    // debug utile (optionnel)
                    'count_ids' => count($cl['ids']),
                ];
            }

            // Jusqu’à dix contenus et six personnes.
            $moviesTv = array_slice($moviesTv, 0, 10);

            $final = array_merge($moviesTv, $peopleItems);

            return response()->json(['results' => $final]);

        } catch (Throwable $e) {
            logger()->error('Autocomplete error', ExternalApiClient::failureContext($e));

            return response()->json([
                'results' => [],
                'error'   => true,
            ], 500);
        }
    }


    public function show(
        string $type,
        int $id,
        Request $request,
        TmdbService $tmdb,
        StreamingAvailabilityService $sa
    ) {
        if (!in_array($type, ['movie', 'tv'], true)) {
            abort(404);
        }

        $isAjax = $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest';
        if (!$isAjax) {
            return redirect('/');
        }

        $country  = strtoupper((string) $request->get('country', config('services.tmdb.country', 'FR')));
        $languageInput = (string) $request->get('language', config('services.tmdb.language', 'fr-FR'));
        $language = $this->normalizeTmdbLanguage($languageInput);

        // Compatible getDetails() 2 ou 3 paramètres
        $details = null;
        try {
            $details = $tmdb->getDetails($id, $type, $language);
        } catch (\ArgumentCountError $e) {
            $details = $tmdb->getDetails($id, $type);
        }

        if (!$details) {
            abort(404);
        }

        $ratingFr = $this->extractFrenchCertification($details, $type);
        $numberOfSeasons = $details['number_of_seasons'] ?? null;

        $providers = $tmdb->getAvailability($id, $type, $country);

        $saShow  = null;
        $seasons = [];

        if ($type === 'tv' && $sa->isEnabled()) {
            $saShow = $sa->getShowWithSeasonsFromTmdbId($id, $type, $country);

            if (is_array($saShow) && isset($saShow['seasons']) && is_array($saShow['seasons'])) {
                $seasons = $saShow['seasons'];
            }
        }

        $episodeRuntime = null;
        if ($type === 'tv') {
            $runtimes = $details['episode_run_time'] ?? [];
            if (is_array($runtimes) && !empty($runtimes)) {
                $episodeRuntime = (int) $runtimes[0];
            }
        }

        $episodeMeta = [];

        if ($type === 'tv' && !empty($details['id']) && is_array($seasons ?? null)) {
            // TMDb may announce episodes before the availability service lists them.
            $seasonsByNumber = [];
            foreach ($seasons as $idx => $season) {
                $number = (int) ($season['seasonNumber'] ?? ($idx + 1));
                $season['seasonNumber'] = $number;
                $seasonsByNumber[$number] = $season;
            }
            foreach ($details['seasons'] ?? [] as $season) {
                $number = (int) ($season['season_number'] ?? 0);
                if ($number < 1) continue;
                $seasonsByNumber[$number] ??= ['seasonNumber' => $number, 'title' => $season['name'] ?? 'Saison '.$number, 'episodes' => []];
            }
            ksort($seasonsByNumber);
            $seasons = array_values($seasonsByNumber);
            $tmdbShowId = (int) $details['id'];
            $lang       = $language ?: 'fr-FR';

            foreach ($seasons as $idx => $season) {
                $seasonNumber = $season['seasonNumber'] ?? ($idx + 1);

                $tmdbSeasonLang = $tmdb->getTvSeason($tmdbShowId, $seasonNumber, $lang);
                if (empty($tmdbSeasonLang['episodes']) || !is_array($tmdbSeasonLang['episodes'])) {
                    continue;
                }

                $episodesEnByNum = [];
                if ($lang !== 'en-US') {
                    $tmdbSeasonEn = $tmdb->getTvSeason($tmdbShowId, $seasonNumber, 'en-US');
                    if (!empty($tmdbSeasonEn['episodes']) && is_array($tmdbSeasonEn['episodes'])) {
                        foreach ($tmdbSeasonEn['episodes'] as $epEn) {
                            if (!empty($epEn['episode_number'])) {
                                $episodesEnByNum[(int) $epEn['episode_number']] = $epEn;
                            }
                        }
                    }
                }

                foreach ($tmdbSeasonLang['episodes'] as $epLang) {
                    $epNum = (int) ($epLang['episode_number'] ?? 0);
                    if ($epNum <= 0) continue;

                    $source = $epLang;

                    $hasOverviewLang = isset($epLang['overview']) && trim((string) $epLang['overview']) !== '';
                    if (!$hasOverviewLang && isset($episodesEnByNum[$epNum])) {
                        $epEn = $episodesEnByNum[$epNum];

                        if (empty($source['name'] ?? null) && !empty($epEn['name'] ?? null)) {
                            $source['name'] = $epEn['name'];
                        }
                        if (!empty($epEn['overview'] ?? null)) {
                            $source['overview'] = $epEn['overview'];
                        }
                    }

                    $episodeMeta[$seasonNumber][$epNum] = [
                        'still_path' => $source['still_path'] ?? null,
                        'runtime'    => $source['runtime'] ?? null,
                        'name'       => $source['name'] ?? null,
                        'overview'   => $source['overview'] ?? null,
                        'air_date'   => $source['air_date'] ?? null,
                    ];
                }

                $episodesByNumber = [];
                foreach ($season['episodes'] ?? [] as $epIndex => $episode) {
                    $number = (int) ($episode['episodeNumber'] ?? ($epIndex + 1));
                    $episode['episodeNumber'] = $number;
                    $episodesByNumber[$number] = $episode;
                }
                foreach ($episodeMeta[$seasonNumber] ?? [] as $number => $meta) {
                    $episodesByNumber[$number] ??= ['episodeNumber' => $number, 'title' => $meta['name'], 'streamingOptions' => []];
                }
                ksort($episodesByNumber);
                $seasons[$idx]['episodes'] = array_values($episodesByNumber);
            }
        }

        if ($type === 'tv' && Auth::check()) {
            // Reuse the synchronized calendar on the detail page, including episodes
            // announced by TVmaze before they reach TMDb/availability season payloads.
            $calendarEpisodes = \App\Models\SeriesEpisode::whereHas('series', fn ($query) => $query
                ->where('tmdb_id', $id)->whereHas('follows', fn ($follow) => $follow->where('user_id', Auth::id())))->get();
            $bySeason = [];
            foreach ($seasons as $season) $bySeason[(int) $season['seasonNumber']] = $season;
            foreach ($calendarEpisodes as $episode) {
                $number = $episode->season_number;
                $bySeason[$number] ??= ['seasonNumber' => $number, 'title' => 'Saison '.$number, 'episodes' => []];
                $episodeMeta[$number][$episode->episode_number] = array_merge($episodeMeta[$number][$episode->episode_number] ?? [], [
                    'name' => $episodeMeta[$number][$episode->episode_number]['name'] ?? $episode->name,
                    'air_date' => $episode->air_date?->toDateString(),
                    'airs_at' => $episode->airs_at?->toIso8601String(),
                    'calendar_source' => $episode->calendar_source,
                    'is_next_announced' => $episode->is_next_announced,
                ]);
                if (!collect($bySeason[$number]['episodes'])->contains(fn ($item) => (int) $item['episodeNumber'] === $episode->episode_number)) {
                    $bySeason[$number]['episodes'][] = ['episodeNumber' => $episode->episode_number, 'title' => $episode->name, 'streamingOptions' => []];
                }
            }
            ksort($bySeason);
            foreach ($bySeason as &$season) {
                $season['episodes'] = array_values(array_filter(is_array($season['episodes'] ?? null) ? $season['episodes'] : [], 'is_array'));
                foreach ($season['episodes'] as $index => &$episode) {
                    $episode['episodeNumber'] = (int) ($episode['episodeNumber'] ?? ($index + 1));
                }
                unset($episode);
                usort($season['episodes'], fn ($a, $b) => $a['episodeNumber'] <=> $b['episodeNumber']);
            }
            unset($season);
            $seasons = array_values($bySeason);
        }

        $watchNow = $this->chooseBestProvider($providers);
        $reco = $tmdb->getRecommendations($id, $type);

        $userLists  = [];
        $isFavorite = false;

        if (Auth::check()) {
            $userId = Auth::id();

            $userLists = MediaList::where('user_id', $userId)
                ->orderBy('name')
                ->get();

            $isFavorite = Favorite::where('user_id', $userId)
                ->where('tmdb_id', $id)
                ->where('type', $type)
                ->exists();
        }

        return view('details-popup', [
            'type'      => $type,
            'details'   => $details,
            'providers' => $providers,
            'watchNow'  => $watchNow,
            'reco'      => $reco,
            'country'   => $country,

            'seasons'         => $seasons,
            'episodeMeta'     => $episodeMeta,
            'saShow'          => $saShow,
            'episodeRuntime'  => $episodeRuntime ?? null,
            'numberOfSeasons' => $numberOfSeasons ?? null,

            'ratingFr'   => $ratingFr,

            'userLists'  => $userLists,
            'isFavorite' => $isFavorite,
            'inUserList' => Auth::check() && MediaList::where('user_id', Auth::id())->whereHas('items', fn ($query) => $query->where('tmdb_id', $id)->where('type', $type))->exists(),
        ]);
    }

    protected function chooseBestProvider(array $providers): ?array
    {
        if (empty($providers)) return null;

        $priority = ['netflix', 'canalplus', 'prime', 'disneyplus', 'appletv', 'paramountplus', 'hbomax'];

        $byAccess = [
            'flatrate' => [],
            'rent'     => [],
            'buy'      => [],
        ];

        foreach ($providers as $p) {
            $acc = $p['access'] ?? 'flatrate';
            if (!isset($byAccess[$acc])) $byAccess[$acc] = [];
            $byAccess[$acc][] = $p;
        }

        foreach (['flatrate', 'rent', 'buy'] as $acc) {
            if (empty($byAccess[$acc])) continue;

            foreach ($priority as $slug) {
                foreach ($byAccess[$acc] as $p) {
                    if (($p['slug'] ?? null) === $slug) return $p;
                }
            }

            return $byAccess[$acc][0];
        }

        return null;
    }

    protected function extractFrenchCertification(array $details, string $type): ?string
    {
        if ($type === 'movie') {
            $results = $details['release_dates']['results'] ?? [];

            foreach ($results as $country) {
                if (($country['iso_3166_1'] ?? null) !== 'FR') continue;

                foreach (($country['release_dates'] ?? []) as $rel) {
                    $cert = trim((string) ($rel['certification'] ?? ''));
                    if ($cert !== '') return $cert;
                }
            }

            return null;
        }

        $results = $details['content_ratings']['results'] ?? [];
        foreach ($results as $country) {
            if (($country['iso_3166_1'] ?? null) !== 'FR') continue;

            $rating = trim((string) ($country['rating'] ?? ''));
            if ($rating !== '') return $rating;
        }

        return null;
    }

    protected function normalizeTmdbLanguage(string $lang): string
    {
        $lang = trim($lang);

        if (preg_match('/^[a-z]{2}-[A-Z]{2}$/', $lang)) return $lang;

        if (preg_match('/^[A-Z]{2}$/', $lang)) {
            $map = [
                'FR' => 'fr-FR',
                'US' => 'en-US',
                'GB' => 'en-GB',
                'DE' => 'de-DE',
                'ES' => 'es-ES',
                'IT' => 'it-IT',
            ];
            return $map[$lang] ?? 'en-US';
        }

        if (preg_match('/^[a-z]{2}$/', $lang)) {
            $map = [
                'fr' => 'fr-FR',
                'en' => 'en-US',
                'es' => 'es-ES',
                'de' => 'de-DE',
                'it' => 'it-IT',
            ];
            return $map[$lang] ?? 'en-US';
        }

        return 'en-US';
    }
}
