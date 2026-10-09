<?php

namespace App\Services;

use App\Support\ExternalApiClient;
use Illuminate\Support\Facades\Log;

class TvmazeCalendarService
{
    /** null = temporary failure; [] = no matching calendar. Never match by title alone. */
    public function episodes(?string $imdbId): ?array
    {
        if (!is_string($imdbId) || !preg_match('/^tt[0-9]+$/', $imdbId)) {
            return [];
        }

        try {
            $show = app(ApiCache::class)->remember('TVmaze', 'catalog', 'tvmaze.identity.'.$imdbId, now()->addDays(7), function () use ($imdbId) {
                $response = ExternalApiClient::make()->get('https://api.tvmaze.com/lookup/shows', ['imdb' => $imdbId]);
                if ($response->status() === 404) return [];
                if (!$response->successful()) return null;
                $data = $response->json();
                return is_array($data) && ($data['externals']['imdb'] ?? null) === $imdbId && !empty($data['id']) ? $data : [];
            });
            if ($show === null) return null;
            if (!$show) return [];

            return app(ApiCache::class)->remember('TVmaze', 'calendar', 'tvmaze.episodes.'.$show['id'], now()->addMinutes(30), function () use ($show) {
                $response = ExternalApiClient::make()->get('https://api.tvmaze.com/shows/'.(int) $show['id'].'/episodes');
                $data = $response->successful() ? $response->json() : null;
                return is_array($data) && array_is_list($data) ? $data : null;
            });
        } catch (\Throwable $exception) {
            Log::warning('TVmaze calendar unavailable', ExternalApiClient::failureContext($exception));
            return null;
        }
    }
}
