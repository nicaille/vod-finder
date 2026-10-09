<?php

namespace Tests\Feature;

use App\Services\{ApiCache, ApiMonitor, ApiStatistics, CacheMonitor, CacheStatistics};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

class CacheMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_hit_reuses_result_and_estimates_saved_calls_from_last_fill_only(): void
    {
        $cache = app(ApiCache::class); $loads = 0;
        $load = function () use (&$loads) {
            $loads++;
            app(ApiMonitor::class)->record('https://api.themoviedb.org/3/search/movie?api_key=private', 200);
            app(ApiMonitor::class)->record('https://api.themoviedb.org/3/search/movie?page=2', 200);
            app(ApiMonitor::class)->record('https://api.brevo.com/v3/smtp/email', 201);
            return ['results' => []];
        };
        $key = 'tmdb.cache-v3.search.private-query';
        $this->assertSame(['results' => []], $cache->remember('TMDb', 'search', $key, now()->addDay(), $load));
        $this->assertSame(['results' => []], $cache->remember('TMDb', 'search', $key, now()->addDay(), $load));
        $this->assertSame(1, $loads);
        app(CacheMonitor::class)->flush(); app(CacheMonitor::class)->flush();
        $this->assertDatabaseHas('cache_metric_buckets', ['service' => 'TMDb', 'usage' => 'search', 'hits' => 1, 'misses' => 1, 'missing' => 1, 'avoided' => 2, 'unknown_hits' => 0]);
        $this->assertDatabaseCount('cache_metric_buckets', 1);
        $encoded = json_encode(DB::table('cache_metric_buckets')->get());
        $this->assertStringNotContainsString('private-query', $encoded); $this->assertStringNotContainsString('api_key', $encoded);
    }

    public function test_old_cache_hit_is_valid_but_not_counted_as_a_known_saving(): void
    {
        Cache::put('old-result', [], now()->addDay());
        $this->assertSame([], app(ApiCache::class)->remember('TMDb', 'search', 'old-result', now()->addDay(), function () { $this->fail('Cache hit must not reload'); }));
        app(CacheMonitor::class)->flush();
        $this->assertDatabaseHas('cache_metric_buckets', ['hits' => 1, 'avoided' => 0, 'unknown_hits' => 1]);
    }

    public function test_expiration_is_identified_and_original_ttl_is_preserved(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00', 'Europe/Paris'));
        $cache = app(ApiCache::class);
        $cache->remember('TVmaze', 'calendar', 'short-result', now()->addMinute(), fn () => [1]);
        $this->travel(2)->minutes();
        $this->assertSame([2], $cache->remember('TVmaze', 'calendar', 'short-result', now()->addMinute(), fn () => [2]));
        app(CacheMonitor::class)->flush();
        $this->assertSame(1, (int) DB::table('cache_metric_buckets')->sum('expired'));
        $this->assertSame(1, (int) DB::table('cache_metric_buckets')->sum('missing'));
    }

    public function test_failures_are_not_reusable_and_observation_lock_never_blocks_loading(): void
    {
        $key = 'failed-result'; $cache = app(ApiCache::class);
        $lock = Cache::lock('cache-monitor.loading.'.hash('sha256', $key), 300); $lock->get();
        try {
            $this->assertNull($cache->remember('TVmaze', 'calendar', $key, now()->addDay(), fn () => null));
        } finally { $lock->release(); }
        $this->assertSame([], $cache->remember('TVmaze', 'calendar', $key, now()->addDay(), fn () => []));
        app(CacheMonitor::class)->flush();
        $this->assertDatabaseHas('cache_metric_buckets', ['misses' => 2, 'hits' => 0, 'concurrent' => 1, 'failures' => 1]);
    }

    public function test_loader_exception_is_recorded_and_safe_cache_rejects_false(): void
    {
        foreach ([fn () => throw new \RuntimeException('Private details'), fn () => false] as $loader) {
            try { app(ApiCache::class)->remember('TMDb', 'details', 'failure', now()->addDay(), $loader, true); $this->fail('Failure must propagate'); }
            catch (\RuntimeException) {}
        }
        app(CacheMonitor::class)->flush();
        $this->assertDatabaseHas('cache_metric_buckets', ['failures' => 2, 'misses' => 2]);
        $this->assertNull(Cache::get('failure'));
    }

    public function test_statistics_filter_by_service_and_period_with_histogram_percentiles(): void
    {
        $from = CarbonImmutable::parse('2026-10-10', 'Europe/Paris');
        $this->travelTo($from->addHours(2));
        $monitor = app(CacheMonitor::class);
        $monitor->record('TMDb', 'search', true, 200, ['avoided' => 2, 'saved_us' => 200000]);
        $monitor->record('TMDb', 'search', true, 4000, ['avoided' => 2]);
        $monitor->record('TMDb', 'search', false, 200000, ['expired' => 1]);
        $monitor->record('TVmaze', 'calendar', true, 50);
        $monitor->flush();
        $this->travelTo($from->subHour()); $monitor->record('TMDb', 'search', true, 50); $monitor->flush();
        $api = app(ApiStatistics::class)->report($from, $from->addDay(), 'hour', 'TMDb');
        $report = app(CacheStatistics::class)->report($from, $from->addDay(), 'hour', 'TMDb', $api['points']);
        $this->assertSame(2, $report['totals']['hits']); $this->assertSame(1, $report['totals']['misses']);
        $this->assertEqualsWithDelta(66.6667, $report['totals']['hit_rate'], .001);
        $this->assertSame(4, $report['totals']['avoided']);
        $this->assertSame(2, $report['hits'][2]); $this->assertSame(0, $report['hits'][3]);
        $this->assertSame(1, $report['totals']['hit_p50_ms']);
        $this->assertSame(4, $report['totals']['hit_p95_ms']);
        $this->assertEquals(200, $report['totals']['miss_avg_ms']);
        $this->assertCount(1, $report['usages']);
    }

    public function test_quota_headers_are_whitelisted_and_rate_limit_errors_are_counted_by_period(): void
    {
        $now = CarbonImmutable::now('Europe/Paris');
        app(ApiMonitor::class)->record('https://streaming-availability.p.rapidapi.com/shows/movie/1', 429, [
            'X-RateLimit-Requests-Limit' => ['1000'], 'X-RateLimit-Requests-Remaining' => ['0'],
            'retry-after' => ['60'], 'authorization' => ['private-key'], 'set-cookie' => ['private-cookie'], 'x-ratelimit-limit' => ['private-key'],
        ]);
        $health = \App\Models\ApiHealth::where('service', 'Streaming Availability')->firstOrFail();
        $this->assertSame(['x-ratelimit-requests-limit' => '1000', 'x-ratelimit-requests-remaining' => '0', 'retry-after' => '60'], $health->quota_headers);
        $this->assertSame(1, $health->rate_limited);
        $report = app(ApiStatistics::class)->report($now->startOfDay(), $now->addDay()->startOfDay(), 'day', 'Streaming Availability');
        $this->assertSame(1, $report['totals']['Streaming Availability']['rate_limited']);
        $this->assertStringNotContainsString('private', $health->toJson());
    }

    public function test_monitoring_database_failure_does_not_break_cache_result(): void
    {
        DB::shouldReceive('transaction')->once()->andThrow(new \RuntimeException('Unavailable'));
        $this->assertSame([42], app(ApiCache::class)->remember('TMDb', 'details', 'working-result', now()->addDay(), fn () => [42]));
        app(CacheMonitor::class)->flush();
    }
}
