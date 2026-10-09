<?php

namespace App\Services;

use App\Models\ApiHealth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApiMonitor
{
    public const SERVICES = ['TMDb', 'TVmaze', 'Streaming Availability', 'Brevo'];
    private array $calls = [];
    public function calls(string $service): int { return $this->calls[$service] ?? 0; }
    public function record(string $url, ?int $status, array $headers = []): void
    {
        $service = [
            'api.themoviedb.org' => 'TMDb', 'api.tvmaze.com' => 'TVmaze',
            'streaming-availability.p.rapidapi.com' => 'Streaming Availability', 'api.brevo.com' => 'Brevo',
        ][parse_url($url, PHP_URL_HOST) ?? ''] ?? null;
        if (!$service) return;
        $this->calls[$service] = $this->calls($service) + 1;
        $headers = array_change_key_case($headers, CASE_LOWER);
        $quota = [];
        foreach (['x-ratelimit-requests-limit', 'x-ratelimit-requests-remaining', 'x-ratelimit-requests-reset',
            'x-ratelimit-api-request-limit', 'x-ratelimit-api-request-remaining', 'x-ratelimit-api-request-reset',
            'x-ratelimit-api-requests-limit', 'x-ratelimit-api-requests-remaining', 'x-ratelimit-api-requests-reset',
            'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset',
            'x-sib-ratelimit-limit', 'x-sib-ratelimit-remaining', 'x-sib-ratelimit-reset', 'retry-after'] as $name) {
            $value = $headers[$name] ?? null;
            if (is_array($value)) $value = $value[0] ?? null;
            if (is_scalar($value) && preg_match('/^[0-9]{1,16}$/D', (string) $value)) $quota[$name] = (string) $value;
        }
        // Store only the service and HTTP status, never URLs, credentials, bodies or recipients.
        try {
            DB::transaction(function () use ($service, $status, $quota) {
                DB::table('api_health')->insertOrIgnore(['service' => $service, 'created_at' => now(), 'updated_at' => now()]);
                $health = ApiHealth::where('service', $service)->lockForUpdate()->firstOrFail();
                $failed = $status === null || $status >= 400;
                $health->requests++; $health->failures += $failed ? 1 : 0;
                $health->last_status = $status;
                if ($status === 429) $health->rate_limited++;
                if ($quota) { $health->quota_headers = $quota; $health->quota_observed_at = now(); }
                $health->{$failed ? 'last_failure_at' : 'last_success_at'} = now();
                $health->save();
                $bucket = now()->utc()->startOfMinute()->format('Y-m-d H:i:s');
                DB::table('api_request_buckets')->insertOrIgnore([
                    'service' => $service, 'bucket_at' => $bucket, 'requests' => 0, 'failures' => 0,
                ]);
                DB::table('api_request_buckets')->where('service', $service)->where('bucket_at', $bucket)
                    ->update(['requests' => DB::raw('requests + 1'), 'failures' => DB::raw('failures + '.($failed ? 1 : 0)), 'rate_limited' => DB::raw('rate_limited + '.($status === 429 ? 1 : 0))]);
            });
        } catch (Throwable $e) {
            // Monitoring must not break an API call during installation or a DB outage.
        }
    }
}
