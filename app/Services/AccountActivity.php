<?php

namespace App\Services;

use App\Models\{Recommendation, SocialEvent, User, UserConnection};

class AccountActivity
{
    public function summary(User $user): array
    {
        // Reuse within this response only; never share personal counts between requests.
        $key = 'vod.account.activity.'.$user->id;
        if (request()->attributes->has($key)) return request()->attributes->get($key);
        $recommendations = Recommendation::where('recipient_id', $user->id)->whereNull('archived_at')->whereNull('read_at')->count();
        $pending = UserConnection::forUser($user->id)->where('status', 'pending')->where('requested_by', '!=', $user->id)->count();
        $accepted = SocialEvent::where('user_id', $user->id)->where('kind', 'accepted')->whereNull('read_at')
            ->whereHas('connection', fn ($query) => $query->forUser($user->id)->where('status', 'accepted'))
            ->distinct()->count('user_connection_id');
        $availability = $user->availabilityAlerts()->whereNull('read_at')->count();
        $summary = ['recommendations' => $recommendations, 'contacts' => $pending + $accepted,
            'pending' => $pending, 'accepted' => $accepted, 'availability' => $availability,
            'total' => $recommendations + $pending + $accepted + $availability];
        request()->attributes->set($key, $summary);
        return $summary;
    }
}
