<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Mail\VerifyNotificationEmail;
use App\Models\NotificationMailSetting;
use App\Services\NotificationEmailSender;
use Illuminate\Auth\Notifications\VerifyEmail;

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
            if (NotificationMailSetting::find(1)?->brevo_enabled) {
                // Use Laravel's existing expiring signed verification URL.
                $verification = (new VerifyEmail())->toMail($request->user());
                app(NotificationEmailSender::class)->send($request->user()->email, new VerifyNotificationEmail($verification->actionUrl));
            } else {
                $request->user()->sendEmailVerificationNotification();
            }
        } catch (\Throwable) {
            return back()->withErrors(['email_verification' => 'Impossible d’envoyer le lien de confirmation. Réessaie ou contacte un administrateur.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
