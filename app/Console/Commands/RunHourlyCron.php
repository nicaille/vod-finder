<?php

namespace App\Console\Commands;

use App\Models\TaskRun;
use App\Services\TaskMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunHourlyCron extends Command
{
    protected $signature = 'cron:hourly';
    protected $description = 'Exécuter les tâches avec le cron horaire OVH';

    public function handle(TaskMonitor $monitor): int
    {
        if (app()->isDownForMaintenance()) {
            return self::SUCCESS;
        }

        $lock = Cache::lock('cron-hourly', 7200);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            return $monitor->run('cron:hourly', function () {
                $commands = ['series:sync'];
                $lastAvailability = TaskRun::where('name', 'availability:sync')->first();
                $today = now()->timezone('Europe/Paris')->startOfDay();
                if (! $lastAvailability || $lastAvailability->status !== 'success'
                    || ! $lastAvailability->finished_at || $lastAvailability->finished_at->lt($today)) {
                    $commands[] = 'availability:sync';
                }
                $commands[] = 'notifications:deliver';

                $status = self::SUCCESS;
                foreach ($commands as $command) {
                    try {
                        if ($this->call($command) !== self::SUCCESS) {
                            $status = self::FAILURE;
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->error($command.' : échec (voir les logs).');
                        $status = self::FAILURE;
                    }
                }

                return $status;
            });
        } finally {
            $lock->release();
        }
    }
}
