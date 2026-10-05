<?php

namespace App\Services;

use App\Models\EpisodeAlert;
use App\Models\TrackedSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SeriesCalendarService
{
    public function __construct(private TmdbService $tmdb)
    {
    }

    public function sync(TrackedSeries $series, ?array $details = null): bool
    {
        $details ??= $this->tmdb->getTvEpisodeCalendar($series->tmdb_id);
        if (!is_array($details) || !isset($details['seasons']) || !is_array($details['seasons'])) {
            return false;
        }

        // Fetch the entire snapshot before writing: an API failure must not produce alerts.
        $seasons = [];
        foreach ($details['seasons'] as $season) {
            $number = (int) ($season['season_number'] ?? 0);
            if ($number < 1) {
                continue; // Specials do not belong to the regular episode calendar.
            }
            $data = $this->tmdb->getTvSeason($series->tmdb_id, $number, null, true);
            if (!is_array($data) || !isset($data['episodes']) || !is_array($data['episodes'])) {
                return false;
            }
            $seasons[$number] = $data['episodes'];
        }

        DB::transaction(function () use ($series, $details, $seasons) {
            // Clear withdrawn dates while preserving episodes and notification history.
            $series->episodes()->update(['air_date' => null]);
            foreach ($seasons as $seasonNumber => $episodes) {
                foreach ($episodes as $episode) {
                    $number = (int) ($episode['episode_number'] ?? 0);
                    if ($number < 1) {
                        continue;
                    }
                    $date = $episode['air_date'] ?? null;
                    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
                        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                        $date = null;
                    }
                    $series->episodes()->updateOrCreate(
                        ['season_number' => $seasonNumber, 'episode_number' => $number],
                        ['name' => ($episode['name'] ?? '') ?: 'Épisode '.$number, 'air_date' => $date]
                    );
                }
            }
            $series->update(['name' => $details['name'] ?? $series->name, 'synced_at' => now()]);
        });

        return true;
    }

    public function createAlerts(TrackedSeries $series): int
    {
        $today = CarbonImmutable::now('Europe/Paris')->startOfDay();
        $episodes = $series->episodes()->whereBetween('air_date', [$today->subDays(7)->toDateString(), $today->toDateString()])->get();
        $count = 0;
        foreach ($series->follows()->where('alerts_enabled', true)->with('user')->lazyById(100) as $follow) {
            if (!$follow->user?->notify_opt_in) {
                continue;
            }
            $start = $follow->created_at->copy()->timezone('Europe/Paris')->toDateString();
            foreach ($episodes as $episode) {
                if ($episode->air_date->toDateString() < $start) {
                    continue;
                }
                $alert = EpisodeAlert::firstOrCreate(['user_id' => $follow->user_id, 'series_episode_id' => $episode->id]);
                $count += (int) $alert->wasRecentlyCreated;
            }
        }
        return $count;
    }
}
