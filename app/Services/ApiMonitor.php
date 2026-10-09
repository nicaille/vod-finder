<?php

namespace App\Services;

use App\Models\ApiHealth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApiMonitor
{
    public const SERVICES = ['TMDb', 'TVmaze', 'Streaming Availability', 'Brevo'];
    public function record(string $url, ?int $status): void
    {
        $service = [
            'api.themoviedb.org' => 'TMDb', 'api.tvmaze.com' => 'TVmaze',
            'streaming-availability.p.rapidapi.com' => 'Streaming Availability', 'api.brevo.com' => 'Brevo',
        ][parse_url($url, PHP_URL_HOST) ?? ''] ?? null;
        if (!$service) return;
        // Store only the service and HTTP status, never URLs, credentials, bodies or recipients.
        try {
            DB::transaction(function () use ($service, $status) {
                DB::table('api_health')->insertOrIgnore(['service' => $service, 'created_at' => now(), 'updated_at' => now()]);
                $health = ApiHealth::where('service', $service)->lockForUpdate()->firstOrFail();
                $failed = $status === null || $status >= 400;
                $health->requests++; $health->failures += $failed ? 1 : 0;
                $health->last_status = $status;
                $health->{$failed ? 'last_failure_at' : 'last_success_at'} = now();
                $health->save();
                $bucket = now()->utc()->startOfMinute()->format('Y-m-d H:i:s');
                DB::table('api_request_buckets')->insertOrIgnore([
                    'service' => $service, 'bucket_at' => $bucket, 'requests' => 0, 'failures' => 0,
                ]);
                DB::table('api_request_buckets')->where('service', $service)->where('bucket_at', $bucket)
                    ->update(['requests' => DB::raw('requests + 1'), 'failures' => DB::raw('failures + '.($failed ? 1 : 0))]);
            });
        } catch (Throwable $e) {
            // Monitoring must not break an API call during installation or a DB outage.
        }
    }
}
