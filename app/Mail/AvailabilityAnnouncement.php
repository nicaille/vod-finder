<?php

namespace App\Mail;

use App\Models\AvailabilityAlert;
use Illuminate\Mail\Mailable;

class AvailabilityAnnouncement extends Mailable
{
    public function __construct(public AvailabilityAlert $alert) {}
    public function build()
    {
        return $this->subject($this->alert->title.' · disponible dans tes abonnements')->view('emails.availability-announcement')->text('emails.text.availability-announcement');
    }
}
