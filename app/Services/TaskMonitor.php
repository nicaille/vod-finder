<?php

namespace App\Services;

use App\Models\TaskRun;
use Throwable;

class TaskMonitor
{
    public function run(string $name, callable $work): int
    {
        $task = TaskRun::updateOrCreate(['name' => $name], ['status' => 'running', 'started_at' => now(), 'finished_at' => null, 'error_class' => null]);
        try {
            $code = $work();
            $task->update(['status' => $code === 0 ? 'success' : 'failed', 'finished_at' => now()]);
            return $code;
        } catch (Throwable $e) {
            $task->update(['status' => 'failed', 'finished_at' => now(), 'error_class' => get_class($e)]);
            throw $e;
        }
    }
}
