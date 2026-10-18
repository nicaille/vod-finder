<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ResetAccountPassword extends Mailable
{
    public function __construct(public string $resetUrl) {}
    public function build()
    {
        return $this->subject('VOD Finder · Définir ou réinitialiser mon mot de passe')
            ->view('emails.reset-account-password')->text('emails.text.reset-account-password');
    }
}
