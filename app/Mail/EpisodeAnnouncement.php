<?php

namespace App\Mail;

use App\Models\EpisodeAlert;
use Illuminate\Mail\Mailable;

class EpisodeAnnouncement extends Mailable
{
    public function __construct(public EpisodeAlert $alert)
    {
    }

    public function build()
    {
        return $this->subject($this->alert->episode->series->name.' · '.$this->alert->episode->code.' : diffusion annoncée')
            ->view('emails.episode-announcement');
    }
}
