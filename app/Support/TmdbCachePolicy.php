<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class TmdbCachePolicy
{
    public static function seriesExpiry(array $series): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        if (in_array($series['status'] ?? null, ['Ended', 'Canceled'], true)) {
            return $now->addDays(7);
        }

        $expiry = $now->addDay();
        $nextDate = $series['next_episode_to_air']['air_date'] ?? null;
        $dates = $nextDate ? [$nextDate] : [];
        // Season payloads have episode dates instead of next_episode_to_air.
        foreach ($series['episodes'] ?? [] as $episode) {
            $date = $episode['air_date'] ?? null;
            if ($date && $date >= $now->setTimezone('Europe/Paris')->toDateString()) {
                $dates[] = $date;
            }
        }
        foreach ($dates as $date) {
            try {
                // TMDb supplies a day, not an exact broadcast time.
                $airing = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Europe/Paris');
                if (!$airing || $airing->format('Y-m-d') !== $date) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }
            if ($airing->lessThanOrEqualTo($now)) {
                return $now->addMinutes(30);
            }
            if ($airing->lessThan($expiry)) {
                $expiry = $airing;
            }
        }

        return $expiry;
    }
}
