<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminConfirmation
{
    public static function remember(Request $request): void
    {
        $user = $request->user();
        $request->session()->put([
            'auth.password_confirmed_at' => time(),
            'auth.password_confirmed_user' => $user->getAuthIdentifier(),
            'auth.password_confirmed_hash' => hash('sha256', $user->getAuthPassword()),
        ]);
    }

    public static function valid(Request $request, $user): bool
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        return $confirmedAt > 0 && $confirmedAt <= time()
            && time() - $confirmedAt < config('security.admin_confirmation_seconds')
            && (string) $request->session()->get('auth.password_confirmed_user') === (string) $user->getAuthIdentifier()
            && hash_equals(hash('sha256', $user->getAuthPassword()), (string) $request->session()->get('auth.password_confirmed_hash', ''));
    }
}
