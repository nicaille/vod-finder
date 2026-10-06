<?php

namespace App\Services;

class RecommendationContent
{
    public function __construct(private TmdbService $tmdb)
    {
    }
    public function get(string $type, int $id): ?array
    {
        $data = $type === 'person' ? $this->tmdb->getPersonProfile($id) : $this->tmdb->getDetails($id, $type);
        if (!is_array($data) || (int) ($data['id'] ?? 0) !== $id) {
            return null;
        }
        $path = $data[$type === 'person' ? 'profile_path' : 'poster_path'] ?? null;
        $providers = $type === 'person' ? [] : $this->tmdb->getAvailability($id, $type, 'FR');
        return ['title' => $data['title'] ?? $data['name'] ?? 'Sans titre', 'description' => $data['overview'] ?? $data['biography'] ?? '', 'image' => $path ? 'https://image.tmdb.org/t/p/w342' . $path : null, 'genres' => array_column($data['genres'] ?? [], 'name'), 'year' => substr($data['release_date'] ?? $data['first_air_date'] ?? '', 0, 4), 'providers' => $providers, 'provider_slugs' => array_values(array_unique(array_column($providers, 'slug'))), 'fetched_at' => now()->toIso8601String()];
    }
}
