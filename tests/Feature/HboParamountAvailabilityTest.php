<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use App\Services\StreamingAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HboParamountAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_and_channel_offers_remain_distinct(): void
    {
        $mapped = app(TmdbService::class)->mapProviders(array_map(fn ($id) => [
            'provider_id' => $id, 'provider_name' => 'Renamed service', 'access' => 'flatrate',
        ], [1899, 1825, 2284, 531, 582, 633, 1853, 2303, 2304, 2616]));
        $this->assertCount(7, $mapped);
        $this->assertSame(['hbomax', 'hbomax', 'hbomax', 'paramountplus', 'paramountplus', 'paramountplus', 'paramountplus'], array_column($mapped, 'slug'));
        $this->assertSame([null, 'prime', 'unext', null, 'prime', 'roku', 'appletv'], array_column($mapped, 'via'));
        $this->assertSame('https://www.primevideo.com/', $mapped[1]['url']);
        $this->assertSame('https://tv.apple.com/', $mapped[6]['url']);
    }

    public function test_exact_aliases_do_not_confuse_unrelated_providers(): void
    {
        foreach (['HBO', 'HBO Go', 'HBO Now', 'HBO Max', 'Max', ' hBo MaX '] as $name) {
            $this->assertSame('hbomax', app(TmdbService::class)->mapProviders([['provider_name' => $name]])[0]['slug']);
        }
        foreach (['Paramount+', 'Paramount Plus Premium', 'Paramount Plus Basic with Ads', 'Paramount Plus Essential'] as $name) {
            $this->assertSame('paramountplus', app(TmdbService::class)->mapProviders([['provider_name' => $name]])[0]['slug']);
        }
        $this->assertSame([], app(TmdbService::class)->mapProviders([
            ['provider_id' => 483, 'provider_name' => 'MAX Stream'],
            ['provider_id' => 187, 'provider_name' => 'Paramount Pictures'],
        ]));
    }

    public function test_channel_subscription_does_not_count_as_a_base_prime_subscription(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
                ['id' => 123, 'name' => 'HBO show', 'first_air_date' => '2026-01-01'],
            ]]),
            'api.themoviedb.org/3/tv/123/watch/providers*' => Http::response(['results' => ['FR' => [
                'flatrate' => [['provider_id' => 1825, 'provider_name' => 'HBO Max Amazon Channel']],
            ]]]),
        ]);
        $this->getJson('/search?q=HBO&type=tv&access=flatrate&providers[]=hbomax')
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.providers.0.via', 'prime');
        $this->getJson('/search?q=HBO&type=tv&access=flatrate&providers[]=prime')
            ->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_catalog_migration_preserves_existing_platform_entries(): void
    {
        $this->assertDatabaseHas('platforms', ['slug' => 'paramountplus']);
        $this->assertDatabaseHas('platforms', ['slug' => 'hbomax']);
        $id = DB::table('platforms')->where('slug', 'hbomax')->value('id');
        DB::table('platforms')->where('id', $id)->update(['name' => 'My HBO', 'position' => 42]);
        $migration = require database_path('migrations/2026_10_06_010000_ensure_hbo_and_paramount_platforms.php');
        $migration->up();
        $this->assertDatabaseHas('platforms', ['id' => $id, 'name' => 'My HBO', 'position' => 42]);
        $this->assertSame(1, DB::table('platforms')->where('slug', 'hbomax')->count());
    }

    public function test_channel_detail_link_is_not_replaced_by_a_direct_service_link(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.themoviedb.org/3/movie/123/watch/providers*' => Http::response(['results' => ['FR' => [
                'flatrate' => [['provider_id' => 582, 'provider_name' => 'Paramount+ Amazon Channel']],
            ]]]),
            'api.themoviedb.org/3/movie/123/recommendations*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/movie/123*' => Http::response(['id' => 123, 'title' => 'Example']),
        ]);
        $this->mock(StreamingAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('getDeepLinksForTmdbId')->once()->andReturn([
                'paramountplus' => [['link' => 'https://www.paramountplus.com/direct-content']],
            ]);
        });
        $this->get('/title/movie/123', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('via Prime Video')->assertSee('https://www.primevideo.com/')
            ->assertDontSee('https://www.paramountplus.com/direct-content');
    }

    public function test_episode_provider_aliases_cover_both_families(): void
    {
        $method = new \ReflectionMethod(StreamingAvailabilityService::class, 'mapServiceToSlug');
        $service = app(StreamingAvailabilityService::class);
        foreach (['paramount', 'paramountplus', 'paramount_plus', 'Paramount+'] as $name) {
            $this->assertSame('paramountplus', $method->invoke($service, $name));
        }
        foreach (['hbo', 'hbo_max', 'hbogo', 'HBO Max', 'max'] as $name) {
            $this->assertSame('hbomax', $method->invoke($service, $name));
        }
        $this->assertNull($method->invoke($service, 'MAX Stream'));
    }
}
