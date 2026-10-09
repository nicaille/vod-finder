<?php

namespace App\Services;

use App\Mail\VerifyNotificationEmail;
use App\Models\NotificationMailSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;

class NotificationEmailVerification
{
    public function send(User $user): void
    {
        if ($user->hasVerifiedEmail()) return;
        if (NotificationMailSetting::find(1)?->brevo_enabled) {
            $verification = (new VerifyEmail())->toMail($user);
            app(NotificationEmailSender::class)->send($user->email, new VerifyNotificationEmail($verification->actionUrl));
        } else {
            $user->sendEmailVerificationNotification();
        }
    }
}
