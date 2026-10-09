<?php

namespace Tests\Feature;

use App\Console\Commands\RunHourlyCron;
use App\Models\TaskRun;
use App\Services\TaskMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HourlyCronTest extends TestCase
{
    use RefreshDatabase;

    private function runner(bool $failAvailability = false): RunHourlyCron
    {
        return new class($failAvailability) extends RunHourlyCron {
            public array $calls = [];
            public function __construct(private bool $failAvailability) { parent::__construct(); }
            public function call($command, array $arguments = [])
            {
                $this->calls[] = $command;
                if ($command === 'availability:sync') {
                    return app(TaskMonitor::class)->run($command, fn () => $this->failAvailability ? 1 : 0);
                }
                return 0;
            }
        };
    }

    public function test_daily_check_runs_once_per_paris_day_and_notifications_run_every_hour(): void
    {
        $this->travelTo(now()->setTimezone('Europe/Paris')->setDate(2026, 10, 10)->setTime(23, 30));
        $first = $this->runner();
        $this->assertSame(0, $first->handle(app(TaskMonitor::class)));
        $this->assertSame(['series:sync', 'availability:sync', 'notifications:deliver'], $first->calls);
        $again = $this->runner();
        $again->handle(app(TaskMonitor::class));
        $this->assertSame(['series:sync', 'notifications:deliver'], $again->calls);
        $this->travel(1)->hours();
        $tomorrow = $this->runner();
        $tomorrow->handle(app(TaskMonitor::class));
        $this->assertContains('availability:sync', $tomorrow->calls);
        $this->assertDatabaseHas('task_runs', ['name' => 'cron:hourly', 'status' => 'success']);
    }

    public function test_failed_daily_sync_does_not_block_notifications_and_is_retried(): void
    {
        $failed = $this->runner(true);
        $this->assertSame(1, $failed->handle(app(TaskMonitor::class)));
        $this->assertContains('notifications:deliver', $failed->calls);
        $this->assertDatabaseHas('task_runs', ['name' => 'cron:hourly', 'status' => 'failed']);
        $retry = $this->runner();
        $this->assertSame(0, $retry->handle(app(TaskMonitor::class)));
        $this->assertContains('availability:sync', $retry->calls);
    }

    public function test_existing_lock_skips_all_tasks(): void
    {
        $lock = Cache::lock('cron-hourly', 7200);
        $lock->get();
        try {
            $runner = $this->runner();
            $this->assertSame(0, $runner->handle(app(TaskMonitor::class)));
            $this->assertSame([], $runner->calls);
            $this->assertSame(0, TaskRun::count());
        } finally {
            $lock->release();
        }
    }
}
