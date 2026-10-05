<?php

namespace App\Console\Commands;

use App\Services\AlertDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DeliverEpisodeNotifications extends Command
{
    protected $signature = 'notifications:deliver';
    protected $description = 'Envoie les notifications récentes d’épisodes, contacts et recommandations par e-mail et push, avec reprise des échecs';

    public function handle(AlertDeliveryService $delivery, \App\Services\SocialDeliveryService $social): int
    {
        $lock = Cache::lock('episode-notification-delivery', 3600);
        if (!$lock->get()) {
            $this->info('Un envoi est déjà en cours.');
            return self::SUCCESS;
        }
        try {
            $result = $delivery->deliver();
            $socialResult = $social->deliver();
            $result['sent'] += $socialResult['sent'];
            $result['failed'] += $socialResult['failed'];
            $this->info($result['sent'].' livraison(s) traitée(s), '.$result['failed'].' échec(s).');
            return $result['failed'] ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
