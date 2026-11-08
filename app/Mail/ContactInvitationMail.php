<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ContactInvitationMail extends Mailable
{
    public function __construct(public string $senderName, public string $invitationUrl, public string $expiry) {}
    public function build()
    {
        return $this->subject('VOD Finder · Une invitation à échanger des recommandations')
            ->view('emails.contact-invitation')->text('emails.text.contact-invitation');
    }
}
