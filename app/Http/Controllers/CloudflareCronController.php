<?php

namespace App\Http\Controllers;

use App\Services\TaskMonitor;
use Illuminate\Support\Facades\{Artisan, Cache};

class CloudflareCronController extends Controller
{
    public function status()
    {
        // Authenticated probe: never sends messages or starts any synchronization.
        return response()->json(['ok' => true, 'service' => 'vod-notifications', 'version' => 1]);
    }

    public function tick(TaskMonitor $monitor)
    {
        if (!Cache::add('cloudflare-notification-minute:'.now()->format('YmdHi'), true, 120)) {
            return response()->json(['ok' => true, 'status' => 'already-triggered']);
        }
        // notifications:deliver shares its lock and delivery records with the OVH cron.
        $code = $monitor->run('cron:cloudflare', fn () => Artisan::call('notifications:deliver', ['--batch' => 8]));
        return response()->json(['ok' => $code === 0, 'status' => $code === 0 ? 'processed' : 'delivery-failed'], $code === 0 ? 200 : 503);
    }
}
