<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ApiCache
{
    public function remember(string $service, string $usage, string $key, mixed $ttl, callable $loader, bool $rejectFailure = false): mixed
    {
        $start = hrtime(true);
        $metaKey = 'cache-monitor.meta.'.hash('sha256', $key);
        $meta = null;
        try { $meta = Cache::get($metaKey); } catch (Throwable) {}
        $value = Cache::get($key);
        if ($value !== null) {
            $duration = (int) ((hrtime(true) - $start) / 1000);
            $known = is_array($meta) && isset($meta['calls'], $meta['duration_us']);
            app(CacheMonitor::class)->record($service, $usage, true, $duration, [
                'avoided' => $known ? $meta['calls'] : 0, 'unknown_hits' => $known ? 0 : 1,
                'saved_us' => $known ? max(0, $meta['duration_us'] - $duration) : 0,
            ]);
            return $value;
        }

        $lock = null; $ownsLock = false; $concurrent = false;
        try {
            $lock = Cache::lock('cache-monitor.loading.'.hash('sha256', $key), 300);
            $ownsLock = $lock->get(); $concurrent = !$ownsLock;
        } catch (Throwable) {}
        $before = app(ApiMonitor::class)->calls($service);
        $failed = true;
        try {
            $value = $loader();
            if ($rejectFailure && ($value === null || $value === false)) throw new \RuntimeException('API call failed - cache not updated.');
            $failed = $value === null;
            if ($failed) return null;
            $expiry = is_callable($ttl) ? $ttl($value) : $ttl;
            // Preserve existing null semantics: null is never a reusable cache hit.
            if (Cache::put($key, $value, $expiry) && !$failed) {
                $timestamp = $expiry instanceof DateTimeInterface ? $expiry->getTimestamp() : now()->timestamp + (int) $expiry;
                try {
                    Cache::put($metaKey, ['expires_at' => $timestamp,
                        'calls' => max(0, app(ApiMonitor::class)->calls($service) - $before),
                        'duration_us' => (int) ((hrtime(true) - $start) / 1000)], now()->addDays(120));
                } catch (Throwable) {}
            }
            return $value;
        } catch (Throwable $exception) {
            $failed = true;
            throw $exception;
        } finally {
            $expired = is_array($meta) && ($meta['expires_at'] ?? PHP_INT_MAX) <= now()->timestamp;
            app(CacheMonitor::class)->record($service, $usage, false, (int) ((hrtime(true) - $start) / 1000), [
                'expired' => $expired ? 1 : 0, 'missing' => $expired ? 0 : 1,
                'failures' => $failed ? 1 : 0, 'concurrent' => $concurrent ? 1 : 0,
            ]);
            if ($ownsLock) { try { $lock->release(); } catch (Throwable) {} }
        }
    }

    public function tmdbUsage(string $key): string
    {
        foreach (['searchMulti' => 'autocomplete', 'search.' => 'search', 'providers.' => 'availability',
            'reco.' => 'suggestions', 'calendar.' => 'calendar', 'season.' => 'calendar',
            'home-releases.' => 'releases', 'provider-catalog.' => 'catalog', 'genres.' => 'catalog',
            'person.' => 'people', 'discover.people.' => 'people'] as $fragment => $usage) {
            if (str_contains($key, $fragment)) return $usage;
        }
        return 'details';
    }
}
