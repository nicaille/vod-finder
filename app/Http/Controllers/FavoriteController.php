<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'tmdb_id' => 'required|integer',
            'type'    => 'required|string|in:movie,tv',
        ]);

        $user = $request->user();

        $existing = Favorite::where('user_id', $user->id)
            ->where('tmdb_id', $data['tmdb_id'])
            ->where('type', $data['type'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'success'  => true,
                'favorited'=> false,
            ]);
        }

        Favorite::create([
            'user_id' => $user->id,
            'tmdb_id' => $data['tmdb_id'],
            'type'    => $data['type'],
        ]);

        return response()->json([
            'success'  => true,
            'favorited'=> true,
        ]);
    }
}
