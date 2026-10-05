<?php

namespace App\Http\Controllers;

use App\Models\{Recommendation, UserConnection, SocialEvent, User};
use App\Services\RecommendationContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class RecommendationController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['q' => 'nullable|string|max:100', 'type' => 'nullable|in:movie,tv,person', 'state' => 'nullable|in:all,unread,archived', 'sort' => 'nullable|in:newest,oldest,title', 'sender' => 'nullable|integer', 'platform' => 'nullable|string|max:30']);
        $query = Recommendation::where('recipient_id', $r->user()->id)->with('sender');
        $state = $data['state'] ?? 'all';
        if ($state === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
        if ($state === 'unread') {
            $query->whereNull('read_at');
        }
        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (!empty($data['sender'])) {
            $query->where('sender_id', $data['sender']);
        }
        if (!empty($data['q'])) {
            $query->where('content->title', 'like', '%' . $data['q'] . '%');
        }
        if (!empty($data['platform'])) {
            $query->whereJsonContains('content->provider_slugs', $data['platform']);
        }
        $sort = $data['sort'] ?? 'newest';
        if ($sort === 'title') {
            $query->orderBy('content->title');
        } else {
            $query->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc');
        }
        $recommendations = $query->orderBy('id')->paginate(12);
        $senders = User::whereIn('id', Recommendation::where('recipient_id', $r->user()->id)->select('sender_id'))->get();
        return view('social.recommendations', compact('recommendations', 'senders'));
    }
    public function compose(Request $r)
    {
        $data = $r->validate(['type' => 'required|in:movie,tv,person', 'id' => 'required|integer|min:1']);
        $connections = UserConnection::forUser($r->user()->id)->where('status', 'accepted')->with(['low', 'high'])->get();
        $content = app(RecommendationContent::class)->get($data['type'], (int) $data['id']);
        abort_unless($content, 503, 'La fiche est indisponible. Réessaie plus tard.');
        return view('social.compose', compact('data', 'connections', 'content'));
    }
    public function store(Request $r, RecommendationContent $content)
    {
        $data = $r->validate(['recipient_id' => 'required|integer', 'type' => 'required|in:movie,tv,person', 'tmdb_id' => 'required|integer|min:1', 'message' => 'nullable|string|max:1000']);
        $pair = [$r->user()->id, (int) $data['recipient_id']];
        sort($pair);
        $connection = UserConnection::where('user_low_id', $pair[0])->where('user_high_id', $pair[1])->where('status', 'accepted')->first();
        abort_unless($connection, 403);
        $snapshot = $content->get($data['type'], (int) $data['tmdb_id']);
        abort_unless($snapshot, 503, 'La fiche est indisponible. Réessaie plus tard.');
        DB::transaction(function () use ($r, $data, $snapshot, $connection, $pair) {
            User::whereIn('id', $pair)->orderBy('id')->lockForUpdate()->get();
            $connection->refresh();
            abort_unless($connection->status === 'accepted', 403);
            $duplicate = Recommendation::where('sender_id', $r->user()->id)->where('recipient_id', $data['recipient_id'])->where('type', $data['type'])->where('tmdb_id', $data['tmdb_id'])->where('created_at', '>=', now()->subMinute())->exists();
            if ($duplicate) {
                return;
            }
            $rec = Recommendation::create(['sender_id' => $r->user()->id, 'recipient_id' => $data['recipient_id'], 'type' => $data['type'], 'tmdb_id' => $data['tmdb_id'], 'message' => $data['message'] ?? null, 'content' => $snapshot]);
            SocialEvent::create(['user_id' => $rec->recipient_id, 'actor_id' => $rec->sender_id, 'recommendation_id' => $rec->id, 'kind' => 'recommendation']);
        });
        return redirect()->route('contacts.index')->with('status', 'Recommandation envoyée.');
    }
    public function show(Request $r, Recommendation $recommendation)
    {
        abort_unless($recommendation->recipient_id === $r->user()->id, 404);
        $recommendation->load('sender');
        $recommendation->update(['read_at' => now()]);
        SocialEvent::where('recommendation_id', $recommendation->id)->where('user_id', $r->user()->id)->update(['read_at' => now()]);
        return view('social.recommendation', compact('recommendation'));
    }
    public function update(Request $r, Recommendation $recommendation)
    {
        abort_unless($recommendation->recipient_id === $r->user()->id, 404);
        $action = $r->validate(['action' => 'required|in:read,unread,archive,restore,delete'])['action'];
        if ($action === 'delete') {
            $recommendation->delete();
        } else {
            $values = match ($action) {
                'archive' => ['archived_at' => now(), 'read_at' => now()],
                'restore' => ['archived_at' => null],
                'unread' => ['read_at' => null],
                default => ['read_at' => now()],
            };
            $recommendation->update($values);
            if (array_key_exists('read_at', $values)) {
                SocialEvent::where('recommendation_id', $recommendation->id)->update(['read_at' => $values['read_at']]);
            }
        }
        return redirect()->route('recommendations.index')->with('status', 'Recommandations mises à jour.');
    }
}
