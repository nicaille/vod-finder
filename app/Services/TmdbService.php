<?php

namespace App\Services;

use App\Support\ExternalApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TmdbService
{
    protected string $apiKey;
    protected string $country;
    protected string $language;
    protected string $baseUrl = 'https://api.themoviedb.org/3';

    public function __construct()
    {
        $this->apiKey   = (string) config('services.tmdb.key');
        $this->country  = (string) config('services.tmdb.country', 'FR');
        $this->language = (string) config('services.tmdb.language', 'fr-FR');
    }

    protected function http()
    {
        return ExternalApiClient::make();
    }

    /**
     * Cache "safe" : si l'appel échoue on ne pollue pas le cache.
     */
    protected function cached(string $cacheKey, int|callable $ttlMinutes, callable $callback)
    {
        try {
            $existing = Cache::get($cacheKey);
            if ($existing !== null) {
                return $existing;
            }
            $result = $callback();

            // On ne met pas en cache un échec
            if ($result === null || $result === false) {
                throw new \RuntimeException("TMDb call failed - cache not updated.");
            }

            $expiry = is_callable($ttlMinutes) ? $ttlMinutes($result) : now()->addMinutes($ttlMinutes);
            Cache::put($cacheKey, $result, $expiry);
            return $result;
        } catch (\Throwable $e) {
            \Log::warning('TMDb cached call failed', [
                'cache_key' => $cacheKey,
                ...ExternalApiClient::failureContext($e),
            ]);

            // On retente une fois sans cache
            try {
                $result = $callback();
                return is_array($result) ? $result : [];
            } catch (\Throwable $e2) {
                \Log::warning('TMDb direct call failed', ExternalApiClient::failureContext($e2));
                return [];
            }
        }
    }

    /**
     * Hydrate les résultats avec les watch providers TMDb (pays donné),
     * puis filtre selon:
     * - plateformes sélectionnées (slugs internes: netflix, prime, etc.)
     * - type d'accès (all|flatrate|rent|buy)
     *
     * Comportement: filtrage STRICT
     * - si aucun provider ne matche -> le résultat est exclu
     */
    public function hydrateAndFilterByProvidersAccess(
        array $results,
        string $type,
        string $country,
        array $selectedProviderSlugs = [],
        string $access = 'all'
    ): array {
        $type = $type === 'tv' ? 'tv' : 'movie';
        $country = strtoupper($country ?: 'FR');

        // Nettoyage filtres
        $selectedProviderSlugs = array_values(array_unique(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            $selectedProviderSlugs
        ))));

        $access = in_array($access, ['all', 'flatrate', 'rent', 'buy'], true) ? $access : 'all';

        if (empty($results)) return [];

        $out = [];

        foreach ($results as $r) {
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) continue;

            // Raw availability has its own 24-hour cache; avoid a second cache
            // that could turn a temporary API failure into a long-lived empty mapping.
            $mappedProviders = $this->getAvailability($id, $type, $country);

            // Calcul global (tous providers) pour flags
            $hasRent = false;
            $hasBuy  = false;

            foreach ($mappedProviders as $p) {
                $acc = $p['access'] ?? null;
                if ($acc === 'rent') $hasRent = true;
                if ($acc === 'buy')  $hasBuy  = true;
            }

            // Filtrage selon plateformes + type d'accès
            $filtered = $mappedProviders;

            if (!empty($selectedProviderSlugs)) {
                $filtered = array_values(array_filter($filtered, function ($p) use ($selectedProviderSlugs) {
                    $slug = (string)($p['slug'] ?? '');
                    return $slug !== '' && in_array($slug, $selectedProviderSlugs, true);
                }));
            }

            if ($access !== 'all') {
                $filtered = array_values(array_filter($filtered, function ($p) use ($access) {
                    return (string)($p['access'] ?? '') === $access;
                }));
            }

            // Filtrage STRICT: si aucun provider ne matche -> on exclut le résultat
            if (empty($filtered)) {
                continue;
            }

            // Injecte champs utilisés par le front
            $r['providers'] = $filtered;
            $r['has_rent']  = $hasRent;
            $r['has_buy']   = $hasBuy;

            $out[] = $r;
        }

        return $out;
    }

    /**
     * Recherche film ou série (par titre)
     */
    public function search(string $query, string $type = 'movie'): array
    {
        if (! $this->apiKey) {
            return [];
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $endpoint = $type === 'tv' ? 'search/tv' : 'search/movie';
        $cacheKey = "tmdb.cache-v3.search.{$type}." . md5($query . '.' . $this->language);

        return $this->cached($cacheKey, 1440, function () use ($endpoint, $query) {
            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'query'    => $query,
                'language' => $this->language,
                'include_adult' => false,
            ]);

            if (! $response->successful()) {
                \Log::warning('TMDb search failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json('results') ?? [];
        });
    }

    /**
     * Recherche multi (films + séries + personnes) pour l'autocomplete
     */
    public function searchMulti(string $query): array
    {
        if (! $this->apiKey) {
            return [];
        }

        $endpoint = 'search/multi';
        $cacheKey = "tmdb.cache-v3.searchMulti." . md5($query . '.' . $this->language);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($endpoint, $query) {
            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'query'    => $query,
                'language' => $this->language,
                'include_adult' => false,
            ]);

            if (! $response->successful()) {
                \Log::warning('TMDb searchMulti failed', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            return $response->json('results') ?? [];
        });
    }

    /**
     * Watch providers (flatrate / rent / buy) pour un film ou une série
     */
    public function getWatchProviders(int $id, string $type = 'movie', ?string $countryOverride = null): array
    {
        // Preserve the interactive lookup's immediate retry; background checks
        // use the nullable snapshot directly and retain their previous state.
        return $this->getWatchProvidersSnapshot($id, $type, $countryOverride)
            ?? $this->getWatchProvidersSnapshot($id, $type, $countryOverride) ?? [];
    }

    public function getWatchProvidersSnapshot(int $id, string $type = 'movie', ?string $countryOverride = null): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $country = $countryOverride ?: $this->country;
        $country = strtoupper((string) $country);

        $endpoint = $type === 'tv'
            ? "tv/{$id}/watch/providers"
            : "movie/{$id}/watch/providers";

        $cacheKey = "tmdb.cache-v3.providers.{$type}.{$id}.{$country}";

        try {
            return Cache::remember($cacheKey, now()->addDay(), function () use ($endpoint, $country) {
                $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                    'api_key' => $this->apiKey,
                ]);

                if (!$response->successful() || !is_array($response->json('results'))) {
                    throw new \RuntimeException('Availability response unavailable.');
                }

                $countryData = $response->json('results.' . $country) ?? [];
                $providers = [];

                foreach (['flatrate' => 'flatrate', 'rent' => 'rent', 'buy' => 'buy'] as $key => $access) {
                    if (!empty($countryData[$key]) && is_array($countryData[$key])) {
                        foreach ($countryData[$key] as $p) {
                            if (!is_array($p)) {
                                continue;
                            }
                            $p['access'] = $access;
                            $providers[] = $p;
                        }
                    }
                }

                return $providers;
            });
        } catch (\Throwable $e) {
            \Log::warning('TMDb availability snapshot failed', ExternalApiClient::failureContext($e));
            return null;
        }
    }

    /**
     * Détails complets d'un film ou d'une série (avec crédits + vidéos)
     */
    public function getDetails(int $id, string $type = 'movie', ?string $languageOverride = null): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $language = $languageOverride ?: $this->language;

        $endpoint = $type === 'tv' ? "tv/{$id}" : "movie/{$id}";
        $cacheKey = "tmdb.cache-v3.details.images-v2.{$type}.{$id}." . $language;

        return $this->cached($cacheKey, $type === 'movie' ? 43200 : \App\Support\TmdbCachePolicy::seriesExpiry(...), function () use ($endpoint, $type, $language) {
            $append = 'credits,videos,external_ids,images';

            if ($type === 'movie') {
                $append .= ',release_dates';
            } else {
                $append .= ',content_ratings';
            }

            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'language' => $language,
                'append_to_response' => $append,
                'include_image_language' => implode(',', array_unique([substr($language, 0, 2), 'en', 'null'])),
            ]);

            if (! $response->successful()) {
                return null;
            }

            return $response->json();
        });
    }

    /**
     * Recommandations pour un titre
     */
    public function getRecommendations(int $id, string $type = 'movie'): array
    {
        if (! $this->apiKey) {
            return [];
        }

        $type = $type === 'tv' ? 'tv' : 'movie';

        $endpoint = $type === 'tv'
            ? "tv/{$id}/recommendations"
            : "movie/{$id}/recommendations";

        $cacheKey = "tmdb.cache-v3.reco.{$type}.{$id}." . $this->language;

        return Cache::remember($cacheKey, now()->addDay(), function () use ($endpoint) {
            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'language' => $this->language,
            ]);

            if (! $response->successful()) {
                return [];
            }

            return $response->json('results') ?? [];
        });
    }

    /**
     * Découverte par personnes (acteur/réal/etc.)
     * - $personIds: tableau d'IDs TMDb (on les envoie en OR via "|")
     *
     * Important TMDb :
     * - valeurs séparées par "," = AND
     * - valeurs séparées par "|" = OR
     */
    public function discoverByPeople(array $personIds, string $type = 'movie'): array
    {
        if (! $this->apiKey) {
            return [];
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $endpoint = $type === 'tv' ? 'discover/tv' : 'discover/movie';

        // Nettoyage IDs
        $personIds = array_values(array_unique(array_filter(
            array_map(static fn ($v) => (int) $v, $personIds),
            static fn ($v) => $v > 0
        )));

        if (empty($personIds)) {
            return [];
        }

        // OR
        $withPeople = implode('|', $personIds);

        // Tri "filmographie"
        $sortBy = $type === 'tv' ? 'first_air_date.desc' : 'primary_release_date.desc';

        // Pagination (20 résultats/page)
        $maxPages = 10; // jusqu'à 200 résultats

        $cacheKey = "tmdb.cache-v3.discover.people.{$type}." . md5($withPeople . '.' . $this->language . ".{$sortBy}.p{$maxPages}");

        return Cache::remember($cacheKey, now()->addDay(), function () use ($endpoint, $withPeople, $sortBy, $maxPages) {
            $byId = [];

            for ($page = 1; $page <= $maxPages; $page++) {
                $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                    'api_key'       => $this->apiKey,
                    'language'      => $this->language,
                    'with_people'   => $withPeople,
                    'sort_by'       => $sortBy,
                    'include_adult' => false,
                    'page'          => $page,
                ]);

                if (! $response->successful()) {
                    \Log::warning('TMDb discoverByPeople failed', [
                        'endpoint' => $endpoint,
                        'status'   => $response->status(),
                        'body'     => $response->body(),
                        'page'     => $page,
                    ]);
                    break;
                }

                $json = $response->json();
                $results = $json['results'] ?? [];

                if (empty($results)) {
                    break;
                }

                foreach ($results as $it) {
                    $id = (int) ($it['id'] ?? 0);
                    if ($id > 0) {
                        $byId[$id] = $it; // dédoublonnage par id
                    }
                }

                $totalPages = (int) ($json['total_pages'] ?? 1);
                if ($page >= $totalPages) {
                    break;
                }
            }

            return array_values($byId);
        });
    }

    /**
     * Identité pour distinguer les homonymes ; cache de sept jours, échecs non conservés.
     */
    public function getPersonProfile(int $personId): ?array
    {
        if (!$this->apiKey || $personId <= 0) return null;
        $result = $this->cached('tmdb.cache-v3.person.profile.images-v2.'.$personId.'.'.md5($this->language), 10080, function () use ($personId) {
            $response = $this->http()->get("{$this->baseUrl}/person/{$personId}", ['api_key' => $this->apiKey, 'language' => $this->language, 'append_to_response' => 'images']);
            return $response->successful() ? $response->json() : null;
        });
        return is_array($result) && (int) ($result['id'] ?? 0) === $personId ? $result : null;
    }

    public function getPersonIdentity(int $personId): ?array
    {
        if (!$this->apiKey || $personId <= 0) return null;
        $result = $this->cached('tmdb.cache-v3.person.identity.'.$personId, 10080, function () use ($personId) {
            $response = $this->http()->get("{$this->baseUrl}/person/{$personId}", [
                'api_key' => $this->apiKey, 'append_to_response' => 'external_ids',
            ]);
            if (!$response->successful()) return null;
            $data = $response->json();
            if (!is_array($data) || (int) ($data['id'] ?? 0) !== $personId) return null;

            return ['birthday' => $data['birthday'] ?? null, 'external_ids' => [
                'imdb_id' => $data['external_ids']['imdb_id'] ?? $data['imdb_id'] ?? null,
                'wikidata_id' => $data['external_ids']['wikidata_id'] ?? null,
            ]];
        });

        return is_array($result) ? $result : null;
    }

    public function getPersonCombinedCredits(int $personId): array
    {
        if (! $this->apiKey || $personId <= 0) {
            return ['cast' => [], 'crew' => []];
        }

        $endpoint = "person/{$personId}/combined_credits";
        $cacheKey = "tmdb.cache-v3.person.combined_credits.{$personId}." . md5($this->language);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($endpoint) {
            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'language' => $this->language,
            ]);

            if (! $response->successful()) {
                \Log::warning('TMDb getPersonCombinedCredits failed', [
                    'endpoint' => $endpoint,
                    'status'   => $response->status(),
                ]);

                return ['cast' => [], 'crew' => []];
            }

            return [
                'cast' => $response->json('cast') ?? [],
                'crew' => $response->json('crew') ?? [],
            ];
        });
    }

    /**
     * Mapping providers TMDb -> structure interne (slug, via, access, logo...)
     */
    /** A failed lookup is null; a verified empty catalogue is an empty array. */
    public function getAvailabilitySnapshot(int $id, string $type, string $country, array $requiredSlugs): ?array
    {
        $bundle = app(StreamingAvailabilityService::class)->getAvailabilityBundle($id, $type, $country);
        if ($bundle !== null && !array_diff($requiredSlugs, $bundle['covered'])) return $bundle['providers'];
        $fallback = $this->getWatchProvidersSnapshot($id, $type, $country);
        if ($fallback === null) return null;
        $fallback = $this->mapProviders($fallback);
        if ($bundle === null) return $fallback;
        return array_merge($bundle['providers'], array_values(array_filter($fallback, fn ($p) => !in_array($p['slug'], $bundle['covered'], true))));
    }

    public function getAvailability(int $id, string $type = 'movie', string $country = 'FR'): array
    {
        $bundle = app(StreamingAvailabilityService::class)->getAvailabilityBundle($id, $type, $country);
        if ($bundle === null) return $this->mapProviders($this->getWatchProviders($id, $type, $country));
        $providers = $bundle['providers'];
        // Keep TMDb only for services outside the authoritative source's coverage.
        foreach ($this->mapProviders($this->getWatchProviders($id, $type, $country)) as $provider) {
            if (!in_array($provider['slug'], $bundle['covered'], true)) {
                $provider['source'] = 'tmdb';
                $providers[] = $provider;
            }
        }
        $unique = [];
        foreach ($providers as $provider) {
            $key = $provider['slug'].'|'.$provider['access'].'|'.($provider['via'] ?? '');
            $unique[$key] ??= $provider;
        }
        return array_values($unique);
    }

    public function mapProviders(array $providers): array
    {
        $result = [];

        $map = [
            'Netflix'             => 'netflix',
            'Amazon Prime Video'  => 'prime',
            'Disney Plus'         => 'disneyplus',
            'Disney+'             => 'disneyplus',
            'Canal+'              => 'canalplus',
            'Canal Plus'          => 'canalplus',
            'Apple TV'            => 'appletv',
            'Apple TV Plus'       => 'appletv',
            'Apple TV+'           => 'appletv',
            'Paramount Plus'      => 'paramountplus',
            'Paramount+'          => 'paramountplus',
            'HBO Max'             => 'hbomax',
            'Max'                 => 'hbomax',

            'Amazon Video'        => 'prime',
            'Apple iTunes'        => 'appletv',
            'Apple TV Store'      => 'appletv',
            'Canal VOD'           => 'canalplus',
        ];

        $baseUrls = [
            'netflix'       => 'https://www.netflix.com/',
            'prime'         => 'https://www.primevideo.com/',
            'disneyplus'    => 'https://www.disneyplus.com/',
            'canalplus'     => 'https://www.canalplus.com/',
            'appletv'       => 'https://tv.apple.com/',
            'paramountplus' => 'https://www.paramountplus.com/',
            'hbomax'        => 'https://www.max.com/',
            'roku'          => 'https://therokuchannel.roku.com/',
            'unext'         => 'https://video.unext.jp/',
        ];

        foreach ($providers as $p) {
            $name = $p['provider_name'] ?? null;
            $identity = \App\Support\ProviderIdentity::tmdb((int) ($p['provider_id'] ?? 0), $name ?? '');
            $slug = $identity[0] ?? ($map[$name ?? ''] ?? null);
            if (!$name || !$slug) {
                continue;
            }
            $access = $p['access'] ?? 'flatrate';

            $via    = $identity[1] ?? null;
            $url    = $baseUrls[$via ?? $slug] ?? null;

            $result[] = [
                'name'   => $name,
                'slug'   => $slug,
                'logo'   => !empty($p['logo_path'])
                    ? 'https://image.tmdb.org/t/p/w45' . $p['logo_path']
                    : null,
                'id'     => $p['provider_id'] ?? null,
                'access' => $access,
                'via'    => $via,
                'url'    => $url,
            ];
        }

        // Preserve distinct subscription channels within each platform family.
        $unique = [];
        foreach ($result as $r) {
            $key = ($r['slug'] ?? '') . '|' . ($r['access'] ?? '') . '|' . ($r['via'] ?? '');
            $unique[$key] = $r;
        }

        return array_values($unique);
    }

    public function getGenreMap(string $type = 'movie'): array
    {
        if (! $this->apiKey) {
            return [];
        }

        $type = $type === 'tv' ? 'tv' : 'movie';
        $endpoint = $type === 'tv' ? 'genre/tv/list' : 'genre/movie/list';
        $cacheKey = "tmdb.cache-v3.genres.{$type}." . $this->language;

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($endpoint) {
            $response = $this->http()->get("{$this->baseUrl}/{$endpoint}", [
                'api_key'  => $this->apiKey,
                'language' => $this->language,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $genres = $response->json('genres') ?? [];
            $map = [];

            foreach ($genres as $g) {
                if (isset($g['id'], $g['name'])) {
                    $map[(int) $g['id']] = $g['name'];
                }
            }

            return $map;
        });
    }

    public function getTvEpisodeCalendar(int $tvId): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        // Short, separate cache: changed release dates should not wait for the detail cache.
        $res = $this->cached('tmdb.cache-v4.calendar.'.$tvId.'.'.$this->language, 30, function () use ($tvId) {
            $response = $this->http()->get("{$this->baseUrl}/tv/{$tvId}", [
                'api_key' => $this->apiKey,
                'language' => $this->language,
                'append_to_response' => 'external_ids',
            ]);
            return $response->successful() ? $response->json() : null;
        });

        return is_array($res) ? $res : null;
    }

    public function discoverRecentReleases(array $slugs, string $type): ?array
    {
        if (!$this->apiKey) return null;
        if (!$slugs) return [];
        $type = $type === 'tv' ? 'tv' : 'movie';
        sort($slugs);
        $today = now('Europe/Paris')->toDateString();
        $from = now('Europe/Paris')->subDays(90)->toDateString();
        $key = 'tmdb.cache-v3.home-releases.v3.'.$type.'.'.md5(implode(',', $slugs).$this->language.$today);
        $result = $this->cached($key, 1440, function () use ($slugs, $type, $today, $from) {
            $catalog = $this->cached('tmdb.cache-v3.provider-catalog.'.$type.'.FR', 1440, function () use ($type) {
                $response = $this->http()->get("{$this->baseUrl}/watch/providers/{$type}", ['api_key' => $this->apiKey, 'watch_region' => 'FR']);
                return $response->successful() ? $response->json('results') : null;
            });
            if (!is_array($catalog)) return null;
            $ids = [];
            foreach ($catalog as $provider) {
                $mapped = $this->mapProviders([$provider]);
                if (isset($provider['provider_id']) && isset($mapped[0]['slug']) && in_array($mapped[0]['slug'], $slugs, true)) {
                    $ids[] = (int) $provider['provider_id'];
                }
            }
            if (!$ids) return [];
            $dateField = $type === 'tv' ? 'first_air_date' : 'primary_release_date';
            $response = $this->http()->get("{$this->baseUrl}/discover/{$type}", [
                'api_key' => $this->apiKey, 'language' => $this->language, 'watch_region' => 'FR',
                'with_watch_providers' => implode('|', array_unique($ids)), 'with_watch_monetization_types' => 'flatrate',
                'sort_by' => $dateField.'.desc', $dateField.'.gte' => $from, $dateField.'.lte' => $today,
                'include_adult' => false, 'page' => 1,
            ]);
            if (!$response->successful()) return null;
            $unique = [];
            foreach ($response->json('results') ?? [] as $title) {
                $date = $title[$type === 'tv' ? 'first_air_date' : 'release_date'] ?? '';
                if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
                    || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) continue;
                if (!empty($title['id']) && $date >= $from && $date <= $today) $unique[$title['id']] = $title;
            }
            // Verify availability and keep only subscription offers from followed platforms.
            return $this->hydrateAndFilterByProvidersAccess(array_slice(array_values($unique), 0, 12), $type, 'FR', $slugs, 'flatrate');
        });
        return is_array($result) ? $result : null;
    }

    public function getTvSeason(int $tvId, int $seasonNumber, ?string $language = null, bool $forCalendar = false): ?array
    {
        if (! $this->apiKey) {
            return null;
        }

        $language = $language ?: $this->language;
        $cacheKey = "tmdb.cache-v3.season.{$tvId}.{$seasonNumber}.{$language}";
        if ($forCalendar) {
            $cacheKey .= '.calendar';
        }

        $res = $this->cached($cacheKey, $forCalendar ? 30 : \App\Support\TmdbCachePolicy::seriesExpiry(...), function () use ($tvId, $seasonNumber, $language) {
            $response = $this->http()->get("{$this->baseUrl}/tv/{$tvId}/season/{$seasonNumber}", [
                'api_key'  => $this->apiKey,
                'language' => $language,
            ]);

            if (! $response->successful()) {
                \Log::warning('TMDb getTvSeason failed', [
                    'tv_id'    => $tvId,
                    'season'   => $seasonNumber,
                    'status'   => $response->status(),
                ]);
                return null;
            }

            return $response->json();
        });

        return is_array($res) ? $res : null;
    }
}
