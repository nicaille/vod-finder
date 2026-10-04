<?php

namespace App\Http\Controllers;

use App\Models\WatchlistItem;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Page "Ma liste"
     */
    public function index(Request $request, \App\Services\TmdbService $tmdb)
    {
        $items = \App\Models\WatchlistItem::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        $hydrated = $items->map(function (\App\Models\WatchlistItem $item) use ($tmdb) {
            $details = $tmdb->getDetails($item->tmdb_id, $item->type);

            $overview = $details['overview'] ?? null;

            $poster = $item->poster;
            if (!$poster && !empty($details['poster_path'])) {
                $poster = 'https://image.tmdb.org/t/p/w342' . $details['poster_path'];
            }

            $year = $item->year;
            if (!$year) {
                $date = $item->type === 'tv'
                    ? ($details['first_air_date'] ?? null)
                    : ($details['release_date'] ?? null);
                if ($date) {
                    $year = substr($date, 0, 4);
                }
            }

            return (object) [
                'tmdb_id'   => $item->tmdb_id,
                'type'      => $item->type,
                'title'     => $item->title,
                'poster'    => $poster,
                'year'      => $year,
                'overview'  => $overview,
                'added_at'  => $item->created_at ? $item->created_at->timestamp : null,
            ];
        });

        return view('watchlist', [
            'items' => $hydrated,
        ]);
    }


    /**
     * Ajoute ou retire un item de la watchlist (toggle).
     * Retourne JSON: { in_watchlist: bool }
     */
    public function toggle(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'tmdb_id' => 'required|integer',
            'type'    => 'required|in:movie,tv',
            'title'   => 'required|string|max:255',
            'poster'  => 'nullable|string|max:255',
            'year'    => 'nullable|string|max:4',
        ]);

        $item = WatchlistItem::where('user_id', $user->id)
            ->where('tmdb_id', $data['tmdb_id'])
            ->where('type', $data['type'])
            ->first();

        if ($item) {
            $item->delete();

            return response()->json([
                'in_watchlist' => false,
            ]);
        }

        WatchlistItem::create([
            'user_id' => $user->id,
            'tmdb_id' => $data['tmdb_id'],
            'type'    => $data['type'],
            'title'   => $data['title'],
            'poster'  => $data['poster'] ?: null,
            'year'    => $data['year'] ?: null,
        ]);

        return response()->json([
            'in_watchlist' => true,
        ]);
    }
}
