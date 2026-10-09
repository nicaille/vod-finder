<?php

namespace App\Http\Controllers;

use App\Models\{User, Recommendation, UserConnection, ListItem};
use Illuminate\Http\Request;

class AdminUserDataController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']); $q = $data['q'] ?? '';
        $users = User::when($q !== '', fn ($query) => $query->where(fn ($query) => $query->where('email', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%')->orWhere('nickname', 'like', '%'.$q.'%')))
            ->withCount(['watchlist', 'watchedTitles', 'seriesFollows'])->orderBy('id')->paginate(25)->withQueryString();
        return response()->view('admin.user-directory', compact('users', 'q'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, User $user)
    {
        $data = $request->validate(['tab' => 'nullable|in:profile,playlist,watched,favorites,lists,series,subscriptions,recommendations,contacts']); $tab = $data['tab'] ?? 'profile';
        $rows = match ($tab) {
            'playlist' => $user->watchlist()->latest()->paginate(25),
            'watched' => $user->watchedTitles()->orderByDesc('watched_at')->paginate(25),
            'favorites' => $user->favorites()->latest()->paginate(25),
            'lists' => ListItem::whereHas('list', fn ($q) => $q->where('user_id', $user->id)->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)))->with('list')->latest()->paginate(25),
            'series' => $user->seriesFollows()->with('series')->latest()->paginate(25),
            'subscriptions' => $user->platformSubscriptions()->orderBy('name')->paginate(25),
            'recommendations' => Recommendation::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))->with(['sender', 'recipient'])->latest()->paginate(25),
            'contacts' => UserConnection::forUser($user->id)->with(['low', 'high'])->latest()->paginate(25),
            default => null,
        };
        $rows?->withQueryString();
        $viaNames = $tab === 'subscriptions' ? \App\Models\Platform::pluck('name', 'id') : collect();
        $lists = $tab === 'lists' ? $user->lists()->withCount('items')->orderBy('name')->paginate(25, ['*'], 'lists_page')->withQueryString() : null;
        $knownTitles = collect();
        if (in_array($tab, ['favorites', 'lists'], true)) {
            $ids = $rows->pluck('tmdb_id');
            $knownTitles = $user->watchlist()->whereIn('tmdb_id', $ids)->get(['type', 'tmdb_id', 'title'])
                ->concat($user->watchedTitles()->whereIn('tmdb_id', $ids)->get(['type', 'tmdb_id', 'title']))
                ->mapWithKeys(fn ($item) => [$item->type.':'.$item->tmdb_id => $item->title]);
        }
        return response()->view('admin.user-detail', compact('user', 'tab', 'rows', 'viaNames', 'lists', 'knownTitles'))->header('Cache-Control', 'private, no-store');
    }
}
