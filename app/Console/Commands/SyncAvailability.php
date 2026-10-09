<?php

namespace App\Console\Commands;

use App\Services\{AvailabilityWatchService, TaskMonitor};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncAvailability extends Command
{
    protected $signature = 'availability:sync';
    protected $description = 'Vérifie les disponibilités françaises des titres en playlist et crée les nouvelles alertes';
    public function handle(AvailabilityWatchService $service, TaskMonitor $monitor): int
    {
        $lock = Cache::lock('availability-sync',7200);
        if (!$lock->get()) { $this->info('Une vérification est déjà en cours.'); return self::SUCCESS; }
        try {
            return $monitor->run($this->getName(),function () use ($service) {
                $result = $service->sync();
                $this->info($result['checked'].' titre(s) vérifié(s), '.$result['alerts'].' nouvelle(s) alerte(s), '.$result['failed'].' échec(s).');
                return $result['failed'] ? self::FAILURE : self::SUCCESS;
            });
        } finally { $lock->release(); }
    }
}
