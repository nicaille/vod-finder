<?php

namespace App\Http\Controllers;

use App\Models\{User, UserConnection, SocialEvent};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use BaconQrCode\{Renderer\ImageRenderer, Renderer\Image\SvgImageBackEnd, Renderer\RendererStyle\RendererStyle, Writer};
class ContactController extends Controller
{
    public function index(Request $r)
    {
        SocialEvent::where('user_id', $r->user()->id)->where('kind', 'accepted')->whereNull('read_at')->update(['read_at' => now()]);
        $r->validate(['q' => 'nullable|string|max:80']);
        $q = trim((string) $r->input('q', ''));
        $directoryQuery = User::where('directory_visible', true)->whereKeyNot($r->user()->id);
        if ($q !== '') {
            $directoryQuery->where(function ($query) use ($q) {
                $query->where('nickname', 'like', '%'.$q.'%')->orWhere(function ($names) use ($q) {
                    $names->where('share_real_name', true);
                    foreach (preg_split('/\s+/u', $q) ?: [] as $word) {
                        $names->where(function ($part) use ($word) {
                            $part->where('first_name', 'like', '%'.$word.'%')->orWhere('last_name', 'like', '%'.$word.'%');
                        });
                    }
                });
            });
        }
        $directory = $directoryQuery->orderBy('id')->paginate(20);
        $connections = UserConnection::forUser($r->user()->id)->with(['low', 'high'])->latest()->get();
        return view('social.contacts', compact('directory', 'connections', 'q'));
    }
    public function invite(Request $r)
    {
        $data = $r->validate(['email' => 'nullable|email|max:255', 'user_id' => 'nullable|integer', 'token' => 'nullable|string|size:48']);
        $target = null;
        if (!empty($data['email'])) {
            $target = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
        } elseif (!empty($data['token'])) {
            $target = User::where('contact_token', $data['token'])->first();
        } elseif (!empty($data['user_id'])) {
            $target = User::where('directory_visible', true)->find($data['user_id']);
        }
        if ($target && $target->id !== $r->user()->id) {
            DB::transaction(function () use ($r, $target) {
                $ids = [$r->user()->id, $target->id];
                sort($ids);
                User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $connection = UserConnection::firstOrCreate(['user_low_id' => $ids[0], 'user_high_id' => $ids[1]], ['requested_by' => $r->user()->id]);
                if ($connection->wasRecentlyCreated) {
                    SocialEvent::create(['user_id' => $target->id, 'actor_id' => $r->user()->id, 'user_connection_id' => $connection->id, 'kind' => 'request']);
                }
            });
        }
        // Identical response for unknown addresses, hidden accounts, existing or blocked relations.
        return back()->with('status', 'Si ce compte peut recevoir ta demande, celle-ci est disponible dans ses contacts.');
    }
    public function update(Request $r, UserConnection $connection)
    {
        abort_unless($connection->involves($r->user()->id), 404);
        $action = $r->validate(['action' => 'required|in:accept,decline,cancel,disconnect,block,unblock'])['action'];
        DB::transaction(function () use ($r, $connection, $action) {
            User::whereIn('id', [$connection->user_low_id, $connection->user_high_id])->orderBy('id')->lockForUpdate()->get();
            $connection->refresh();
            $id = $r->user()->id;
            if ($action === 'accept' || $action === 'decline') {
                abort_unless($connection->status === 'pending' && $connection->requested_by !== $id, 403);
                $connection->update(['status' => $action === 'accept' ? 'accepted' : 'declined']);
                SocialEvent::where('user_connection_id', $connection->id)->update(['read_at' => now()]);
                if ($action === 'accept') {
                    SocialEvent::create(['user_id' => $connection->requested_by, 'actor_id' => $id, 'user_connection_id' => $connection->id, 'kind' => 'accepted']);
                }
            } elseif ($action === 'block') {
                abort_if($connection->status === 'blocked' && $connection->blocked_by !== $id, 403);
                $connection->update(['status' => 'blocked', 'blocked_by' => $id]);
                SocialEvent::where('user_connection_id', $connection->id)->update(['read_at' => now()]);
            } elseif ($action === 'unblock') {
                abort_unless($connection->status === 'blocked' && $connection->blocked_by === $id, 403);
                $connection->delete();
            } elseif ($action === 'cancel') {
                abort_unless($connection->status === 'pending' && $connection->requested_by === $id, 403);
                $connection->delete();
            } else {
                abort_unless($connection->status === 'accepted', 403);
                $connection->delete();
            }
        });
        return back()->with('status', 'Contacts mis à jour.');
    }
    public function rotate(Request $r)
    {
        $r->user()->forceFill(['contact_token' => Str::random(48)])->save();
        return back()->with('status', 'Nouveau lien créé. Les anciens liens et QR codes sont révoqués.');
    }
    public function join(Request $r, string $token)
    {
        $target = User::where('contact_token', $token)->firstOrFail();
        abort_if($target->id === $r->user()->id, 422, 'Ce lien est celui de ton compte.');
        return view('social.join', compact('target', 'token'));
    }
    public function qr(Request $r)
    {
        abort_unless($r->user()->contact_token, 404);
        $writer = new Writer(new ImageRenderer(new RendererStyle(280, 24), new SvgImageBackEnd()));
        return response($writer->writeString(route('contacts.join', $r->user()->contact_token)), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store']);
    }
}
