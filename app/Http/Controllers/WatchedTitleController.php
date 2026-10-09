<?php

namespace App\Http\Controllers;

use App\Models\{WatchedTitle, WatchlistItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WatchedTitleController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:100','type'=>'nullable|in:all,movie,tv','sort'=>'nullable|in:newest,oldest,title']);
        $q = $data['q'] ?? ''; $type = $data['type'] ?? 'all'; $sort = $data['sort'] ?? 'newest';
        $items = $request->user()->watchedTitles()->when($q !== '', fn ($query) => $query->where('title','like','%'.$q.'%'))
            ->when($type !== 'all', fn ($query) => $query->where('type',$type));
        $items = ($sort === 'title' ? $items->orderBy('title') : $items->orderBy('watched_at',$sort === 'oldest' ? 'asc' : 'desc'))->orderBy('id')->paginate(24)->withQueryString();
        return view('history.index', compact('items','q','type','sort'));
    }

    public function toggle(Request $request)
    {
        $data = $request->validate(['tmdb_id'=>'required|integer|min:1','type'=>'required|in:movie,tv','title'=>'required|string|max:255','year'=>'nullable|string|regex:/^\d{4}$/','poster'=>'nullable|url:http,https|max:255']);
        $watched = DB::transaction(function () use ($request, $data) {
            // Serialize changes for this user and preserve all their other collections.
            \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $query = $request->user()->watchedTitles()->where('type',$data['type'])->where('tmdb_id',$data['tmdb_id']);
            $existing = $query->first();
            if ($existing) $existing->delete();
            else $request->user()->watchedTitles()->create([...$data, 'watched_at'=>now()]);
            WatchlistItem::where('user_id',$request->user()->id)->where('type',$data['type'])->where('tmdb_id',$data['tmdb_id'])->update(['availability_checked_at'=>null,'availability_providers'=>null]);
            return !$existing;
        });
        return response()->json(['watched'=>$watched]);
    }
}
