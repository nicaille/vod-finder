<?php

namespace App\Console\Commands;

use App\Models\TrackedSeries;
use App\Services\SeriesCalendarService;
use App\Support\ExternalApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSeriesCalendar extends Command
{
    protected $signature = 'series:sync';
    protected $description = 'Actualise les épisodes des séries suivies et crée les alertes de diffusion';

    public function handle(SeriesCalendarService $calendar): int
    {
        $alerts = 0;
        $failures = 0;
        foreach (TrackedSeries::whereHas('follows')->cursor() as $series) {
            try {
                if (!$calendar->sync($series)) {
                    $failures++;
                    $this->warn('Calendrier indisponible pour '.$series->name.' ; nouvelle tentative au prochain passage.');
                    continue;
                }
                $alerts += $calendar->createAlerts($series);
            } catch (Throwable $e) {
                $failures++;
                Log::warning('Series calendar sync failed', ['tmdb_id' => $series->tmdb_id, ...ExternalApiClient::failureContext($e)]);
                $this->warn('Échec de synchronisation pour la série '.$series->tmdb_id.'.');
            }
        }
        $this->info($alerts.' nouvelle(s) alerte(s), '.$failures.' échec(s).');
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
