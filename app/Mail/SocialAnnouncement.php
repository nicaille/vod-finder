<?php

namespace App\Mail;

use App\Models\SocialEvent;
use Illuminate\Mail\Mailable;
class SocialAnnouncement extends Mailable
{
    public function __construct(public SocialEvent $event)
    {
    }
    public function build()
    {
        return $this->subject('VOD Finder · ' . ($this->event->kind === 'recommendation' ? 'Une recommandation pour toi' : 'Une nouvelle demande ou relation'))
            ->view('emails.social-announcement')->text('emails.text.social-announcement');
    }
}
