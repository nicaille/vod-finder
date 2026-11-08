<?php

namespace App\Services;

use App\Models\{ContactInvitation, SocialEvent, User, UserConnection};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactInvitations
{
    public static function continuation(Request $request, bool $consume = true): ?string
    {
        $token = $consume ? $request->session()->pull('contact_invitation') : $request->session()->get('contact_invitation');
        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)
            ? route('contact-invitations.show', ['token' => $token]) : null;
    }

    public function prune(): int
    {
        return ContactInvitation::where('expires_at', '<', now()->subDays(30))->delete();
    }

    public function create(User $sender): array
    {
        $token = bin2hex(random_bytes(32));
        $invitation = ContactInvitation::create(['sender_id' => $sender->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
        return [$invitation, route('contact-invitations.show', ['token' => $token])];
    }

    public function find(string $token): ?ContactInvitation
    {
        return ContactInvitation::with('sender')->where('token_hash', hash('sha256', $token))->first();
    }

    public function accept(string $token, User $recipient): void
    {
        $initial = $this->find($token);
        abort_unless($initial && $initial->usable(), 410, 'Cette invitation est indisponible.');
        abort_if($initial->sender_id === $recipient->id, 422, 'Tu ne peux pas accepter ta propre invitation.');
        DB::transaction(function () use ($initial, $recipient, $token) {
            // Match the lock order of the existing contact actions.
            $ids = [$initial->sender_id, $recipient->id]; sort($ids);
            abort_unless(User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->count() === 2, 410);
            $invitation = ContactInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($invitation && $invitation->usable(), 410, 'Cette invitation est indisponible.');
            $connection = UserConnection::firstOrCreate(['user_low_id' => $ids[0], 'user_high_id' => $ids[1]],
                ['requested_by' => $invitation->sender_id]);
            abort_if(in_array($connection->status, ['blocked', 'declined'], true), 403, 'Cette mise en relation ne peut pas être acceptée.');
            if ($connection->status !== 'accepted') {
                $connection->update(['status' => 'accepted']);
                SocialEvent::where('user_connection_id', $connection->id)->whereNull('read_at')->update(['read_at' => now()]);
                SocialEvent::create(['user_id' => $invitation->sender_id, 'actor_id' => $recipient->id,
                    'user_connection_id' => $connection->id, 'kind' => 'accepted']);
            }
            $invitation->update(['accepted_at' => now(), 'accepted_by' => $recipient->id]);
        });
    }
}
