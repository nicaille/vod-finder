<?php

namespace App\Http\Controllers;

use App\Models\{User, Recommendation, UserConnection, ListItem};
use Illuminate\Http\Request;
use App\Http\Requests\AdminUserUpdateRequest;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Support\Str;

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
    public function edit(User $user)
    {
        $user->loadCount('pushSubscriptions');
        return response()->view('admin.user-edit', compact('user'))->header('Cache-Control', 'private, no-store');
    }

    public function update(AdminUserUpdateRequest $request, User $user)
    {
        $data = $request->safe()->only([
            'first_name', 'last_name', 'nickname', 'email', 'notify_opt_in', 'notify_email',
            'notify_web', 'notify_platform_updates', 'directory_visible', 'share_real_name',
        ]);
        $result = DB::transaction(function () use ($request, $user, $data) {
            // Match role management's locking order and recheck privileges before writing.
            $admins = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->contains('id', $request->user()->id), 403);
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $emailChanged = mb_strtolower($target->email) !== $data['email'];
            $target->fill($data);
            $target->nickname = filled($target->nickname) ? $target->nickname : null;
            $target->name = trim($target->first_name.' '.$target->last_name);
            if ($emailChanged) {
                DB::table(config('auth.passwords.users.table'))->whereIn('email', [$target->getOriginal('email'), $target->email])->delete();
                $target->email_verified_at = null;
                $target->setRememberToken(Str::random(60));
            }
            $changed = array_values(array_diff(array_keys($target->getDirty()), ['remember_token']));
            $target->save();
            return ['email_changed' => $emailChanged, 'fields' => $changed];
        }, 3);
        Log::channel('security')->info('admin.user.updated', [
            'actor_id' => $request->user()->id, 'target_user_id' => $user->id, 'fields' => $result['fields'],
        ]);
        $message = 'Compte utilisateur mis à jour.';
        if ($result['email_changed']) $message .= ' La nouvelle adresse doit être confirmée depuis Mon compte avant de recevoir des alertes e-mail.';
        return redirect()->route('admin.user-data.show', $user)->with('status', $message);
    }

}
