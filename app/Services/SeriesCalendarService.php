<?php

namespace App\Services;

use App\Models\EpisodeAlert;
use App\Models\TrackedSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SeriesCalendarService
{
    public function __construct(private TmdbService $tmdb, private TvmazeCalendarService $tvmaze)
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
            foreach ($data['episodes'] as $episode) {
                $episodeNumber = (int) ($episode['episode_number'] ?? 0);
                if ($episodeNumber > 0) $seasons[$number][$episodeNumber] = $episode;
            }
        }

        // The series-level next episode can appear before the season endpoint is updated.
        $next = $details['next_episode_to_air'] ?? [];
        $nextSeason = (int) ($next['season_number'] ?? 0);
        $nextNumber = (int) ($next['episode_number'] ?? 0);
        if ($nextSeason > 0 && $nextNumber > 0) {
            $seasons[$nextSeason][$nextNumber] = array_merge($seasons[$nextSeason][$nextNumber] ?? [], $next);
        }

        $timings = $this->tvmaze->episodes($details['external_ids']['imdb_id'] ?? null);
        // Do not replace known instants with date-only guesses during an upstream outage.
        if ($timings === null && $series->episodes()->whereNotNull('airs_at')->exists()) return false;
        foreach ($timings ?? [] as $episode) {
            $seasonNumber = (int) ($episode['season'] ?? 0);
            $number = (int) ($episode['number'] ?? 0);
            if ($seasonNumber < 1 || $number < 1) continue;
            $instant = $this->broadcastInstant($episode['airstamp'] ?? null);
            if (!$instant) {
                // TVmaze can announce a future date before supplying its broadcast time.
                // Keep it as a source date, without inventing an offset or overriding TMDb.
                $date = $episode['airdate'] ?? null;
                if (!isset($seasons[$seasonNumber][$number]) && is_string($date)
                    && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
                    && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    $seasons[$seasonNumber][$number] = ['episode_number' => $number,
                        'name' => $episode['name'] ?? 'Épisode '.$number, 'air_date' => $date, 'calendar_source' => 'tvmaze'];
                }
                continue;
            }
            $seasons[$seasonNumber][$number] = array_merge(
                ['episode_number' => $number, 'name' => $episode['name'] ?? 'Épisode '.$number],
                $seasons[$seasonNumber][$number] ?? [],
                ['airs_at' => $instant, 'air_date' => $instant->timezone('Europe/Paris')->toDateString(), 'calendar_source' => 'tvmaze']
            );
        }

        DB::transaction(function () use ($series, $details, $seasons) {
            // Clear withdrawn dates while preserving episodes and notification history.
            $series->episodes()->update(['air_date' => null, 'airs_at' => null, 'is_next_announced' => false]);
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
                        ['name' => ($episode['name'] ?? '') ?: 'Épisode '.$number, 'air_date' => $date,
                            'airs_at' => $episode['airs_at'] ?? null, 'calendar_source' => $episode['calendar_source'] ?? 'tmdb',
                            'is_next_announced' => (int) ($details['next_episode_to_air']['season_number'] ?? 0) === (int) $seasonNumber
                                && (int) ($details['next_episode_to_air']['episode_number'] ?? 0) === $number]
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
        $episodes = $series->episodes()->announced()->whereBetween('air_date', [$today->subDays(7)->toDateString(), $today->toDateString()])->get();
        $count = 0;
        foreach ($series->follows()->where('alerts_enabled', true)->with('user')->lazyById(100) as $follow) {
            if (!$follow->user?->notify_opt_in) {
                continue;
            }
            $start = $follow->created_at->copy()->timezone('Europe/Paris')->toDateString();
            foreach ($episodes as $episode) {
                if ($episode->airs_at && $episode->airs_at->lessThan($follow->created_at)) {
                    continue;
                }
                if ($episode->air_date->toDateString() < $start) {
                    continue;
                }
                $alert = EpisodeAlert::firstOrCreate(['user_id' => $follow->user_id, 'series_episode_id' => $episode->id]);
                $count += (int) $alert->wasRecentlyCreated;
            }
        }
        return $count;
    }

    private function broadcastInstant(mixed $value): ?CarbonImmutable
    {
        // A timezone-less datetime cannot be converted to the user's local timezone.
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|[+-](\d{2}):(\d{2}))$/', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || (int) $parts[4] > 23 || (int) $parts[5] > 59 || (int) $parts[6] > 59
            || (int) ($parts[8] ?? 0) > 14 || (int) ($parts[9] ?? 0) > 59) return null;
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
