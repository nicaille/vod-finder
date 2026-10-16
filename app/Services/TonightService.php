<?php

namespace App\Services;

use App\Models\User;
use App\Support\TonightPreferences;

class TonightService
{
    public function __construct(private TmdbService $tmdb) {}

    public function suggest(User $user, array $preferences): array
    {
        $subscriptions = $user->platformSubscriptions()->wherePivot('is_active', true)->get();
        if ($subscriptions->isEmpty()) return ['results' => [], 'state' => 'no_platforms'];
        $slugs = $subscriptions->pluck('slug')->sort()->values()->all();
        $watched = $user->watchedTitles()->get(['type', 'tmdb_id'])
            ->mapWithKeys(fn ($item) => [$item->type.':'.$item->tmdb_id => true])->all();
        $types = $preferences['type'] === 'all' ? ['movie', 'tv'] : [$preferences['type']];
        $pools = []; $failed = false;
        foreach ($types as $type) {
            $genres = TonightPreferences::genres($preferences['genre'], $type);
            $mood = TonightPreferences::moodGenres($preferences['mood'], $type);
            $pool = $this->tmdb->discoverTonight($slugs, $type, $preferences['minutes'], $genres ?: $mood);
            if ($pool === null) $failed = true;
            $pools[$type] = $pool ?? [];
        }
        // Interleave films and series; neither type consumes the entire lookup budget.
        $candidates = [];
        for ($i = 0; $i < 20; $i++) {
            foreach ($types as $type) if (isset($pools[$type][$i])) $candidates[] = [$type, $pools[$type][$i]];
        }
        $results = []; $seen = []; $detailsChecked = 0; $offersChecked = 0;
        $deadline = microtime(true) + 15;
        foreach ($candidates as [$type, $candidate]) {
            $id = (int) ($candidate['id'] ?? 0); $key = $type.':'.$id;
            if ($id < 1 || isset($watched[$key]) || isset($seen[$key])) continue;
            $seen[$key] = true;
            $genres = TonightPreferences::genres($preferences['genre'], $type);
            $mood = TonightPreferences::moodGenres($preferences['mood'], $type);
            $ids = $candidate['genre_ids'] ?? [];
            if (($genres && !array_intersect($genres, $ids)) || ($mood && !array_intersect($mood, $ids))) continue;
            if ($detailsChecked >= 12 || $offersChecked >= 6) break;
            if (microtime(true) >= $deadline) { $failed = true; break; }
            $detailsChecked++;
            $details = $this->tmdb->getDetails($id, $type);
            if (!$details || (int) ($details['id'] ?? 0) !== $id) { $failed = true; continue; }
            if (!empty($details['adult'])) continue;
            $runtime = $type === 'movie' ? (int) ($details['runtime'] ?? 0)
                : max(array_merge([0], array_map('intval', $details['episode_run_time'] ?? [])));
            if ($type === 'tv' && $runtime <= 0) $runtime = (int) ($details['last_episode_to_air']['runtime'] ?? 0);
            if ($runtime <= 0 || $runtime > $preferences['minutes']) continue;
            $date = $details[$type === 'movie' ? 'release_date' : 'first_air_date'] ?? '';
            if (!$date || $date > now('Europe/Paris')->toDateString()) continue;
            $offersChecked++;
            $offers = $this->tmdb->getAvailabilitySnapshot($id, $type, 'FR', $slugs);
            if ($offers === null) { $failed = true; continue; }
            $included = array_values(array_filter($offers, fn ($offer) => $subscriptions->contains(fn ($sub) => AvailabilityWatchService::matches($offer, $sub))));
            if (!$included) continue;
            $title = $details['title'] ?? $details['name'] ?? 'Sans titre';
            $path = $details['poster_path'] ?? null;
            $results[] = ['id' => $id, 'type' => $type, 'title' => $title, 'runtime' => $runtime,
                'year' => substr($date, 0, 4), 'overview' => $details['overview'] ?? '',
                'poster' => $path ? 'https://image.tmdb.org/t/p/w342'.$path : null, 'providers' => $included];
            if (count($results) >= 6) break;
        }
        return ['results' => $results, 'state' => $failed ? ($results ? 'partial' : 'unavailable') : ($results ? 'ready' : 'empty')];
    }
}
