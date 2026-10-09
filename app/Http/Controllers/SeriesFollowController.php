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
        $follows = $request->user()->seriesFollows()->with('series')->latest()->get();
        $episodes = SeriesEpisode::with('series')->whereIn('tracked_series_id', $follows->pluck('tracked_series_id'))
            ->upcoming()->orderByRaw('air_date IS NULL')->orderBy('air_date')->orderBy('airs_at')->orderBy('season_number')->orderBy('episode_number')->paginate(30, ['*'], 'calendar_page');
        $alerts = $request->user()->episodeAlerts()->announced()->with('episode.series')->latest()->paginate(20, ['*'], 'alerts_page');
        return view('series.index', compact('follows', 'episodes', 'alerts'));
    }

    public function store(Request $request, TmdbService $tmdb, SeriesCalendarService $calendar)
    {
        $data = $request->validate(['tmdb_id' => ['required', 'integer', 'min:1']]);
        $details = $tmdb->getTvEpisodeCalendar($data['tmdb_id']);
        if (!$details || empty($details['name']) || (int) ($details['id'] ?? 0) !== (int) $data['tmdb_id']) {
            if ($request->expectsJson()) return response()->json(['message' => 'Impossible de récupérer cette série. Réessaie dans quelques instants.'], 503);
            return back()->withErrors(['series' => 'Impossible de récupérer cette série. Réessaie dans quelques instants.']);
        }
        $series = TrackedSeries::firstOrCreate(['tmdb_id' => $data['tmdb_id']], ['name' => $details['name']]);
        $follow = $request->user()->seriesFollows()->firstOrCreate(['tracked_series_id' => $series->id], ['alerts_enabled' => true]);
        $synced = $calendar->sync($series, $details);
        $message = $synced
            ? 'Série dans ton suivi. Le calendrier est à jour.'
            : 'Série suivie. Le calendrier sera actualisé lors de la prochaine synchronisation.';
        if ($request->expectsJson()) return response()->json(['followed' => true, 'follow_id' => $follow->id, 'message' => $message]);
        return back()->with('status', $message);
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
        if ($request->expectsJson()) {
            return response()->json(['followed' => false, 'message' => 'Série retirée du suivi.']);
        }
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
