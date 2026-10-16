<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TonightDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    public function test_discovery_uses_subscription_filters_and_shared_daily_cache_without_availability_calls(): void
    {
        Http::fake([
            'api.themoviedb.org/3/watch/providers/movie*' => Http::response(['results' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]),
            'api.themoviedb.org/3/discover/movie*' => Http::response(['results' => [['id' => 42, 'genre_ids' => [35]]]]),
        ]);
        $service = app(TmdbService::class);
        $this->assertSame(42, $service->discoverTonight(['netflix'], 'movie', 90, [35])[0]['id']);
        $this->assertSame(42, app(TmdbService::class)->discoverTonight(['netflix'], 'movie', 90, [35])[0]['id']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/movie')
            && $request['with_watch_providers'] === '8' && $request['watch_region'] === 'FR'
            && $request['with_watch_monetization_types'] === 'flatrate' && (int) $request['with_runtime.lte'] === 90
            && $request['with_genres'] === '35' && $request['primary_release_date.lte'] === now('Europe/Paris')->toDateString());
        $service->discoverTonight(['netflix'], 'movie', 120, [35]);
        Http::assertSentCount(3);
        $this->travel(25)->hours();
        $service->discoverTonight(['netflix'], 'movie', 90, [35]);
        Http::assertSentCount(5);
    }

    public function test_api_failure_is_not_cached_as_empty_selection(): void
    {
        Http::fake([
            'api.themoviedb.org/3/watch/providers/tv*' => Http::response(['results' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]),
            'api.themoviedb.org/3/discover/tv*' => Http::sequence()->push([], 503)->push(['results' => [['id' => 43, 'genre_ids' => [18]]]]),
        ]);
        $service = app(TmdbService::class);
        $this->assertNull($service->discoverTonight(['netflix'], 'tv', 60, []));
        $this->assertSame(43, $service->discoverTonight(['netflix'], 'tv', 60, [])[0]['id']);
        Http::assertSentCount(3);
    }
}
