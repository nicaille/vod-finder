<?php

namespace App\Http\Controllers;

use App\Models\EpisodeAlert;
use App\Models\SeriesEpisode;
use App\Models\SeriesFollow;
use App\Models\TrackedSeries;
use App\Services\SeriesCalendarService;
use App\Services\TmdbService;
use Illuminate\Http\Request;

class SeriesFollowController extends Controller
{
    public function index(Request $request)
    {
        $today = now('Europe/Paris')->toDateString();
        $follows = $request->user()->seriesFollows()->with('series')->latest()->get();
        $episodes = SeriesEpisode::with('series')->whereIn('tracked_series_id', $follows->pluck('tracked_series_id'))
            ->where('air_date', '>=', $today)->orderBy('air_date')->orderBy('season_number')->orderBy('episode_number')->paginate(30, ['*'], 'calendar_page');
        $alerts = $request->user()->episodeAlerts()->announced()->with('episode.series')->latest()->paginate(20, ['*'], 'alerts_page');
        return view('series.index', compact('follows', 'episodes', 'alerts'));
    }

    public function store(Request $request, TmdbService $tmdb, SeriesCalendarService $calendar)
    {
        $data = $request->validate(['tmdb_id' => ['required', 'integer', 'min:1']]);
        $details = $tmdb->getTvEpisodeCalendar($data['tmdb_id']);
        if (!$details || empty($details['name']) || (int) ($details['id'] ?? 0) !== (int) $data['tmdb_id']) {
            return back()->withErrors(['series' => 'Impossible de récupérer cette série. Réessaie dans quelques instants.']);
        }
        $series = TrackedSeries::firstOrCreate(['tmdb_id' => $data['tmdb_id']], ['name' => $details['name']]);
        $request->user()->seriesFollows()->firstOrCreate(['tracked_series_id' => $series->id], ['alerts_enabled' => true]);
        $synced = $calendar->sync($series, $details);
        return redirect()->route('series.index')->with('status', $synced
            ? 'Série dans ton suivi. Le calendrier est à jour.'
            : 'Série suivie. Le calendrier sera actualisé lors de la prochaine synchronisation.');
    }

    public function update(Request $request, SeriesFollow $follow)
    {
        abort_unless($follow->user_id === $request->user()->id, 403);
        $data = $request->validate(['alerts_enabled' => ['required', 'boolean']]);
        $follow->update($data);
        return redirect()->route('series.index')->with('status', 'Préférence de la série enregistrée.');
    }

    public function destroy(Request $request, SeriesFollow $follow)
    {
        abort_unless($follow->user_id === $request->user()->id, 403);
        $follow->delete();
        return redirect()->route('series.index')->with('status', 'Série retirée du suivi.');
    }

    public function read(Request $request, EpisodeAlert $alert)
    {
        abort_unless($alert->user_id === $request->user()->id, 403);
        if (!$alert->read_at) {
            $alert->update(['read_at' => now()]);
        }
        return redirect()->route('series.index');
    }
}
