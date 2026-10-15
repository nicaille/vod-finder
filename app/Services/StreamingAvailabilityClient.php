<?php

namespace App\Services;

use App\Support\ExternalApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\{Cache, Log};

class StreamingAvailabilityClient
{
    public const DIRECT_SERVICE = 'Streaming Availability Direct';

    private function connections(): array
    {
        $config = config('services.streaming_availability', []);
        $connections = [];
        if (!empty($config['key'])) {
            // Never send credentials to an arbitrary override or a redirect target.
            $base = rtrim($config['base_url'] ?? 'https://streaming-availability.p.rapidapi.com', '/');
            if ($base !== 'https://streaming-availability.p.rapidapi.com') {
                throw new \InvalidArgumentException('Unsupported Streaming Availability RapidAPI endpoint.');
            }
            $connections['rapidapi'] = ['base' => $base, 'key' => $config['key'], 'headers' => [
                'X-RapidAPI-Key' => $config['key'], 'X-RapidAPI-Host' => 'streaming-availability.p.rapidapi.com',
            ]];
        }
        if (!empty($config['direct_key'])) {
            $connections['direct'] = ['base' => 'https://api.movieofthenight.com/v4', 'key' => $config['direct_key'],
                'headers' => ['X-API-Key' => $config['direct_key']]];
        }
        return $connections;
    }

    public function enabled(): bool
    {
        return !empty(config('services.streaming_availability.key')) || !empty(config('services.streaming_availability.direct_key'));
    }

    public function get(string $path, array $query = []): ?Response
    {
        if (!preg_match('~^/(?:shows/(?:movie/|tv/)?[A-Za-z0-9]+|countries/[a-z]{2})$~D', $path)) {
            throw new \InvalidArgumentException('Unsupported Streaming Availability resource.');
        }
        $connections = $this->connections();
        $identity = hash('sha256', json_encode(array_column($connections, 'key'), JSON_THROW_ON_ERROR));
        $activeKey = 'sa.connection.active.'.$identity;
        $active = Cache::get($activeKey);
        if (isset($connections[$active ?? ''])) $connections = [$active => $connections[$active]] + $connections;
        $lastResponse = null;
        foreach ($connections as $name => $connection) {
            $pauseKey = 'sa.connection.pause.'.$name.'.'.hash('sha256', $connection['key']);
            if (Cache::has($pauseKey)) continue;
            $resource = $path;
            $parameters = $query;
            if ($name === 'direct') {
                // The v4 SDK encodes compound TMDb IDs as one path parameter.
                if (str_starts_with($path, '/shows/')) $resource = '/shows/'.rawurlencode(substr($path, 7));
                unset($parameters['source']);
            }
            try {
                $response = ExternalApiClient::make()->withoutRedirecting()->withHeaders($connection['headers'])
                    ->get($connection['base'].$resource, $parameters);
            } catch (ConnectionException $e) {
                Cache::put($pauseKey, true, now()->addSeconds(30));
                Log::warning('Streaming Availability connection failed', ['connection' => $name] + ExternalApiClient::failureContext($e));
                continue;
            }
            $lastResponse = $response;
            if ($response->successful()) {
                // Stay on the working account; do not probe an exhausted quota on every request.
                Cache::put($activeKey, $name, now()->addDays(32));
                foreach (['x-ratelimit-requests-remaining', 'x-ratelimit-api-request-remaining', 'x-ratelimit-api-requests-remaining', 'x-ratelimit-remaining'] as $header) {
                    if ($response->header($header) === '0') {
                        Cache::put($pauseKey, true, now()->addSeconds($this->retryDelay($response, 60)));
                        break;
                    }
                }
                return $response;
            }
            if (!in_array($response->status(), [401, 403, 429], true) && !$response->serverError()) {
                return $response; // A missing title or invalid query must not spend a second quota.
            }
            $delay = $response->status() === 429 ? 60 : ($response->serverError() ? 30 : 300);
            $delay = $this->retryDelay($response, $delay);
            Cache::put($pauseKey, true, now()->addSeconds($delay));
            Log::notice('Streaming Availability connection paused', ['connection' => $name, 'http_status' => $response->status(), 'retry_seconds' => $delay]);
        }
        return $lastResponse;
    }
    private function retryDelay(Response $response, int $fallback): int
    {
        // Retry-After has standardized units; other reset headers vary by provider.
        $retry = $response->header('Retry-After');
        if (is_string($retry) && preg_match('/^\d{1,9}$/D', $retry)) {
            return min(32 * 86400, max(1, (int) $retry));
        }
        if (is_string($retry) && ($until = strtotime($retry)) !== false) {
            return min(32 * 86400, max(1, $until - now()->timestamp));
        }
        return $fallback;
    }

}
