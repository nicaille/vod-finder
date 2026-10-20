<?php

namespace App\Console\Commands;

use App\Services\AlertDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DeliverEpisodeNotifications extends Command
{
    protected $signature = 'notifications:deliver {--batch=0 : Limiter un passage HTTP à ce nombre de tentatives (1 à 20)}';
    protected $description = 'Envoie les notifications récentes d’épisodes, contacts, recommandations et disponibilités par e-mail et push, avec reprise des échecs';

    public function handle(AlertDeliveryService $delivery, \App\Services\SocialDeliveryService $social, \App\Services\AvailabilityDeliveryService $availability, \App\Services\TaskMonitor $monitor): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT);
        if ($batch === false || $batch < 0 || $batch > 20) return self::INVALID;
        $budget = $batch ? new \App\Services\NotificationDeliveryBudget($batch) : null;
        $lock = Cache::lock('episode-notification-delivery', 3600);
        if (!$lock->get()) {
            $this->info('Un envoi est déjà en cours.');
            return self::SUCCESS;
        }
        try {
            return $monitor->run($this->getName(), function () use ($delivery, $social, $availability, $budget) {
                $socialResult = $budget ? $social->deliver(null, $budget) : $social->deliver();
                $result = $budget ? $delivery->deliver($budget) : $delivery->deliver();
                $availableResult = $budget ? $availability->deliver($budget) : $availability->deliver();
                $result['sent'] += $availableResult['sent'];
                $result['failed'] += $availableResult['failed'];
                $result['sent'] += $socialResult['sent'];
                $result['failed'] += $socialResult['failed'];
                $this->info($result['sent'].' livraison(s) traitée(s), '.$result['failed'].' échec(s).');
                return $result['failed'] ? self::FAILURE : self::SUCCESS;
            });
        } finally {
            $lock->release();
        }
    }
}
