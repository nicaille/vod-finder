<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class VerifyNotificationEmail extends Mailable
{
    public function __construct(public string $verificationUrl)
    {
    }

    public function build()
    {
        return $this->subject('VOD Finder · Confirmer mon adresse e-mail')->view('emails.verify-notification-email');
    }
}
