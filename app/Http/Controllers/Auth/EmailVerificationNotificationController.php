<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\NotificationEmailVerification;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        try {
            app(NotificationEmailVerification::class)->send($request->user());
        } catch (\Throwable) {
            return back()->withErrors(['email_verification' => 'Impossible d’envoyer le lien de confirmation. Réessaie ou contacte un administrateur.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
