<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Support\ExternalApiClient;

class StreamingAvailabilityService
{
    protected string $apiKey;
    protected string $host;
    protected string $baseUrl;
    protected string $defaultCountry;
    protected string $defaultLanguage;

    public function __construct()
    {
        $cfg = config('services.streaming_availability', []);

        $this->apiKey         = (string) ($cfg['key'] ?? '');
        $this->host           = (string) ($cfg['host'] ?? 'streaming-availability.p.rapidapi.com');
        $this->baseUrl        = (string) ($cfg['base_url'] ?? 'https://streaming-availability.p.rapidapi.com');
        $this->defaultCountry = (string) ($cfg['country'] ?? 'fr');
        $this->defaultLanguage= (string) ($cfg['language'] ?? 'fr');
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }
    
    protected function http()
    {
        return ExternalApiClient::make();
    }
    
    /**
     * Construit l'identifiant attendu par l'API SA pour un TMDb ID.
     * ex: movie/335977 ou tv/12345
     */
    protected function buildTmdbShowId(int $tmdbId, string $type): string
    {
        $kind = $type === 'tv' ? 'tv' : 'movie';

        return $kind . '/' . $tmdbId; // ex: "tv/225171"
    }

    /**
     * Retourne une structure prête à afficher pour les séries:
     * [
     *   [
     *     'title' => 'Saison 1',
     *     'first_air_year' => 2025,
     *     'last_air_year'  => 2025,
     *     'providers' => [
     *         [ 'slug' => 'appletv', 'name' => 'Apple TV', 'type' => 'subscription', 'link' => '...' ],
     *         ...
     *     ],
     *     'episodes' => [
     *         [
     *           'title'     => 'On, c’est nous',
     *           'overview'  => '...',
     *           'year'      => 2025,
     *           'runtime'   => null ou int (minutes),
     *           'providers' => [
     *              [ 'slug' => 'appletv', 'name' => 'Apple TV', 'type' => 'subscription', 'link' => '...' ],
     *           ],
     *         ],
     *         ...
     *     ],
     *   ],
     *   ...
     * ]
     */
    public function getSeriesSeasonsWithEpisodes(int $tmdbId, string $type, string $country = 'FR'): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $country = strtolower($country ?: $this->defaultCountry);
        $saId    = $this->buildTmdbShowId($tmdbId, $type);

        $cacheKey = "sa.show.seasons.{$type}.{$tmdbId}.{$country}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($saId, $country) {
            try {
                $response = $this->http()
                    ->withHeaders([
                        'X-RapidAPI-Key'  => $this->apiKey,
                        'X-RapidAPI-Host' => $this->host,
                    ])
                    ->get($this->baseUrl . '/shows/' . $saId, [
                        'source'          => 'tmdb',
                        'country'         => $country,
                        'output_language' => $this->defaultLanguage,
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                $data    = $response->json();
                $seasons = $data['seasons'] ?? [];

                if (!is_array($seasons) || empty($seasons)) {
                    return [];
                }

                $result = [];

                foreach ($seasons as $index => $season) {
                    if (!is_array($season)) {
                        continue;
                    }

                    $seasonTitle = $season['title'] ?? ('Saison ' . ($index + 1));

                    // options de visionnage pour la saison (Apple TV etc.)
                    $seasonProviders = $this->extractProvidersFromStreamingOptions(
                        $season['streamingOptions'] ?? [],
                        $country
                    );

                    // épisodes
                    $episodesOut = [];
                    $episodes    = $season['episodes'] ?? [];

                    if (is_array($episodes)) {
                        foreach ($episodes as $epIndex => $ep) {
                            if (!is_array($ep)) {
                                continue;
                            }

                            $epTitle    = $ep['title']    ?? ('Episode ' . ($epIndex + 1));
                            $epOverview = $ep['overview'] ?? '';
                            $epYear     = $ep['airYear']  ?? null;

                            $epProviders = $this->extractProvidersFromStreamingOptions(
                                $ep['streamingOptions'] ?? [],
                                $country
                            );

                            $episodesOut[] = [
                                'title'     => $epTitle,
                                'overview'  => $epOverview,
                                'year'      => $epYear,
                                'providers' => $epProviders,
                                // on remplira éventuellement runtime côté contrôleur avec TMDb si tu veux
                                'runtime'   => null,
                            ];
                        }
                    }

                    $result[] = [
                        'title'          => $seasonTitle,
                        'first_air_year' => $season['firstAirYear'] ?? null,
                        'last_air_year'  => $season['lastAirYear'] ?? null,
                        'providers'      => $seasonProviders,
                        'episodes'       => $episodesOut,
                    ];
                }

                return $result;

            } catch (\Throwable $e) {
                \Log::warning('StreamingAvailability seasons error', [
                    'tmdb_id' => $tmdbId,
                    'type'    => 'tv',
                    'country' => $country,
                    'message' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Transforme un bloc streamingOptions SA pour un pays donné
     * en liste normalisée de providers.
     *
     * $streamingOptions = [
     *   "fr" => [
     *      [
     *        "service" => [ "id" => "apple", "name" => "Apple TV", ... ],
     *        "type"    => "subscription",
     *        "link"    => "https://tv.apple.com/...",
     *        ...
     *      ],
     *      ...
     *   ],
     *   ...
     * ]
     */
    protected function extractProvidersFromStreamingOptions(array $streamingOptions, string $country): array
    {
        if (empty($streamingOptions)) {
            return [];
        }

        $country = strtolower($country);

        // on gère FR ou fr-fr etc.
        $normalized = [];
        foreach ($streamingOptions as $key => $val) {
            $normalized[strtolower($key)] = $val;
        }

        $options = $normalized[$country] ?? null;
        if (! $options) {
            foreach ($normalized as $key => $val) {
                if (str_starts_with($key, $country)) {
                    $options = $val;
                    break;
                }
            }
        }

        if (!is_array($options) || empty($options)) {
            return [];
        }

        $providers = [];

        foreach ($options as $opt) {
            if (!is_array($opt)) {
                continue;
            }

            $serviceData = $opt['service'] ?? null;
            $link        = $opt['videoLink'] ?? ($opt['link'] ?? null);
            $type        = $opt['streamingType'] ?? ($opt['type'] ?? null); // SA peut utiliser "type" ou "streamingType"

            if (! $serviceData || ! $link) {
                continue;
            }

            // le fameux cas: "service" est un tableau {id, name, ...}
            if (is_array($serviceData)) {
                $serviceId   = $serviceData['id']   ?? null;
                $serviceName = $serviceData['name'] ?? null;
            } else {
                $serviceId   = is_string($serviceData) ? $serviceData : null;
                $serviceName = null;
            }

            if (! $serviceId && ! $serviceName) {
                continue;
            }

            $slug = $this->mapServiceToSlug($serviceId ?? $serviceName);
            if (! $slug) {
                continue;
            }

            $providers[] = [
                'slug'    => $slug,
                'name'    => $serviceName ?? $serviceId,
                'type'    => $type ?: 'subscription',
                'link'    => $link,
            ];
        }

        return $providers;
    }


    /**
     * Retourne les deeplinks par plateforme, à partir d’un TMDb ID.
     *
     * @param  int    $tmdbId
     * @param  string $type   movie|tv
     * @param  string $country  FR, US...
     * @return array  ex: [ 'netflix' => [ ... ], 'prime' => [ ... ] ]
     */
    public function getDeepLinksForTmdbId(int $tmdbId, string $type = 'movie', string $country = 'FR'): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $country = strtolower($country ?: $this->defaultCountry);

        // 🔑 C’est ici qu’on corrige : on passe bien movie/ID ou tv/ID à l’API
        $saId = $this->buildTmdbShowId($tmdbId, $type);

        $cacheKey = "sa.deeplinks.tmdb.{$type}.{$tmdbId}.{$country}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($saId, $country) {
            try {
                $response = $this->http()
                    ->withHeaders([
                        'X-RapidAPI-Key'  => $this->apiKey,
                        'X-RapidAPI-Host' => $this->host,
                    ])
                    ->get($this->baseUrl . '/shows/' . $saId, [
                        'source'          => 'tmdb',
                        'country'         => $country,
                        'output_language' => $this->defaultLanguage,
                    ]);

                if (! $response->successful()) {
                    return [];
                }
                
                $data = $response->json(); //ok

//dd($data);
                
                // La doc SA indique streamingOptions par pays (fr, fr-FR, etc.)
                $optionsByCountry = $data['streamingOptions'] ?? [];
                if (!is_array($optionsByCountry) || empty($optionsByCountry)) {
                    return [];
                }
                
                // Normalisation des clés de pays (fr, FR, fr-FR, ...)
                $normalized = [];
                foreach ($optionsByCountry as $key => $val) {
                    $normalized[strtolower($key)] = $val;
                }

                // 1) clé exacte (fr)
                $options = $normalized[$country] ?? null;

                // 2) sinon on tente un startsWith (ex: fr matche fr-fr)
                if (! $options) {
                    foreach ($normalized as $key => $val) {
                        if (str_starts_with($key, $country)) {
                            $options = $val;
                            break;
                        }
                    }
                }

                if (!is_array($options) || empty($options)) {
                    return [];
                }

                $results = [];

                foreach ($options as $opt) {
                    if (! is_array($opt)) {
                        continue;
                    }

                    $serviceData   = $opt['service'] ?? null;
                    $streamingType = $opt['streamingType'] ?? null;
                    $link          = $opt['videoLink'] ?? ($opt['link'] ?? null);
/*dd($serviceData);
dd($link);
dd($opt['videoLink']);*/
                    
                    if (! $serviceData || ! $link) {
                        continue;
                    }

                    // serviceData est un tableau {"id": "...", "name": "..."}
                    $serviceId   = is_array($serviceData) ? ($serviceData['id'] ?? null)   : null;
                    $serviceName = is_array($serviceData) ? ($serviceData['name'] ?? null) : null;

                    if (! $serviceId && ! $serviceName) {
                        continue;
                    }

                    // On privilégie l'id, mais on peut fallback sur le name
                    $slug = $this->mapServiceToSlug($serviceId ?? $serviceName);
                    if (! $slug) {
                        \Log::debug('SA service non mappé', [
                            'service_id'   => $serviceId,
                            'service_name' => $serviceName,
                        ]);
                        continue;
                    }

                    $results[$slug][] = [
                        'service'       => $serviceId,
                        'slug'          => $slug,
                        'streamingType' => $streamingType,
                        'link'          => $link,
                        'price'         => $opt['price'] ?? null,
                        'quality'       => $opt['quality'] ?? null,
                    ];
                }

                
                return $results;

            } catch (\Throwable $e) {
                \Log::warning('StreamingAvailability error', [
                    'tmdb_id' => $saId,
                    'country' => $country,
                    'message' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }


    /**
     * Map des identifiants de service SA → nos slugs internes.
     *
     * D’après la doc SA:
     *   - Netflix:      service "netflix"
     *   - Prime Video:  service "prime"
     *   - Disney+:      service "disney" / "disney_plus"
     *   - Apple TV:     service "apple"
     *   - Max (HBO):    service "hbo" 
     */
    protected function mapServiceToSlug(string $service): ?string
    {
        $service = strtolower($service);

        $map = [
            'netflix'       => 'netflix',
            'prime'         => 'prime',
            'primevideo'    => 'prime',
            'amazon'        => 'prime',
            'amazonprime'   => 'prime',
            'disney'        => 'disneyplus',
            'disney_plus'   => 'disneyplus',
            'disneyplus'    => 'disneyplus',
            'apple'         => 'appletv',
            'appletv'       => 'appletv',
            'appletvplus'   => 'appletv',
            'hbo'           => 'hbomax',
            'hbomax'        => 'hbomax',
            'max'           => 'hbomax',
        ];

        return $map[$service] ?? null;
    }
    
        /**
     * Récupère le show complet (saisons + épisodes) via TMDb ID.
     * Utilisé pour les séries (type = tv) afin d'afficher saisons / épisodes.
     */
    public function getShowWithSeasonsFromTmdbId(int $tmdbId, string $type = 'tv', string $country = 'FR'): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $country = strtolower($country ?: $this->defaultCountry);

        // Pour Streaming Availability, on doit envoyer "tv/225171" ou "movie/359461" etc.
        // Donc on préfixe nous-même.
        $saId = sprintf('%s/%d', $type, $tmdbId);

        $cacheKey = "sa.show.{$type}.{$tmdbId}.{$country}";

//dd($this->baseUrl . '/shows/' . $saId);
    
        return Cache::remember($cacheKey, now()->addHours(6), function () use ($saId, $country) {
            try {
                $response = $this->http()
                    ->withHeaders([
                        'X-RapidAPI-Key'  => $this->apiKey,
                        'X-RapidAPI-Host' => $this->host,
                    ])
                    ->get($this->baseUrl . '/shows/' . $saId, [
                        'source'          => 'tmdb',
                        'country'         => $country,
                        'output_language' => $this->defaultLanguage,
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                return $response->json(); // contient seasons, streamingOptions, etc.
            } catch (\Throwable $e) {
                \Log::warning('StreamingAvailability getShowWithSeasonsFromTmdbId error', [
                    'sa_id'   => $saId,
                    'country' => $country,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    
    /**
     * Récupère un show via IMDb ou TMDb ID à partir des détails TMDb.
     */
    public function getShowFromTmdbDetails(array $details, string $country)
    {
        if (! $this->baseUrl || ! $this->apiKey || ! $this->host) {
            return null;
        }

        // 1) on privilégie l'IMDb id si dispo
        $imdbId = $details['imdb_id'] ?? null;

        // 2) sinon on tente l'id TMDb brut (numérique)
        $id = $imdbId ?: ($details['id'] ?? null);
        if (! $id) {
            return null;
        }

        $country = strtolower($country);

        $response = $this->http()
            ->withHeaders([
                'X-RapidAPI-Key'  => $this->apiKey,
                'X-RapidAPI-Host' => $this->host,
            ])
            ->get($this->baseUrl.'/shows/'.$id, [
                'country' => $country,
            ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json(); // doit contenir streamingOptions[$country][…]['link']
    }
}