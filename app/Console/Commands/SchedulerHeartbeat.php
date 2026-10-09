<?php

namespace App\Console\Commands;

use App\Services\TaskMonitor;
use Illuminate\Console\Command;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'schedule:heartbeat';
    protected $description = 'Enregistre un passage de l’ordonnanceur pour le tableau de bord admin';
    public function handle(TaskMonitor $monitor): int { return $monitor->run($this->getName(),fn () => self::SUCCESS); }
}
