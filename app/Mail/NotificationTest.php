<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class NotificationTest extends Mailable
{
    public function build()
    {
        return $this->subject('VOD Finder · Test des notifications e-mail')->view('emails.notification-test');
    }
}
