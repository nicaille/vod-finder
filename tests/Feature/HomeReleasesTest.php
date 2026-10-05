<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\User;
use App\Services\TmdbService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeReleasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
        config(['services.tmdb.key' => 'test-key', 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    private function subscribe(User $user, bool $active = true): void
    {
        $platform = Platform::firstOrCreate(['slug' => 'netflix'], ['name' => 'Netflix', 'position' => 1]);
        $user->platformSubscriptions()->attach($platform, ['is_active' => $active]);
    }

    public function test_guests_and_users_without_active_platforms_do_not_request_tmdb(): void
    {
        $this->getJson('/home/releases')->assertOk()->assertJsonPath('reason', 'guest')->assertJsonCount(0, 'results');
        $user = User::factory()->create();
        $this->subscribe($user, false);
        $this->actingAs($user)->getJson('/home/releases')->assertOk()->assertJsonPath('reason', 'no_platforms');
        Http::assertNothingSent();
    }

    public function test_recent_titles_are_filtered_by_subscription_availability_and_date_then_sorted(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $user->watchlist()->create(['tmdb_id' => 1, 'type' => 'tv', 'title' => 'Série récente']);
        $catalog = ['results' => [['provider_id' => 8, 'provider_name' => 'Netflix'], ['provider_id' => 337, 'provider_name' => 'Disney Plus']]];
        $netflix = ['results' => ['FR' => ['flatrate' => [['provider_name' => 'Netflix']]]]];
        Http::fake([
            'api.themoviedb.org/3/watch/providers/*' => Http::response($catalog),
            'api.themoviedb.org/3/discover/movie*' => Http::response(['results' => [
                ['id' => 1, 'title' => 'Film récent', 'release_date' => '2026-09-30'],
                ['id' => 2, 'title' => 'Location seulement', 'release_date' => '2026-10-02'],
                ['id' => 3, 'title' => 'Autre plateforme', 'release_date' => '2026-10-03'],
                ['id' => 4, 'title' => 'Futur', 'release_date' => '2026-11-01'],
                ['id' => 5, 'title' => 'Ancien', 'release_date' => '2025-01-01'],
                ['id' => 1, 'title' => 'Film récent', 'release_date' => '2026-09-30'],
            ]]),
            'api.themoviedb.org/3/discover/tv*' => Http::response(['results' => [['id' => 1, 'name' => 'Série récente', 'first_air_date' => '2026-10-04']]]),
            'api.themoviedb.org/3/movie/1/watch/providers*' => Http::response($netflix),
            'api.themoviedb.org/3/tv/1/watch/providers*' => Http::response($netflix),
            'api.themoviedb.org/3/movie/2/watch/providers*' => Http::response(['results' => ['FR' => ['rent' => [['provider_name' => 'Netflix']]]]]),
            'api.themoviedb.org/3/movie/3/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_name' => 'Disney Plus']]]]]),
        ]);
        $response = $this->actingAs($user)->getJson('/home/releases?country=US&providers[]=disneyplus')->assertOk()->assertJsonCount(2, 'results');
        $response->assertJsonPath('results.0.type', 'tv')->assertJsonPath('results.0.in_watchlist', true)
            ->assertJsonPath('results.1.type', 'movie')->assertJsonPath('results.1.in_watchlist', false)
            ->assertJsonPath('results.0.country', 'FR')->assertJsonPath('results.0.providers.0.slug', 'netflix');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/discover/movie') && $request['with_watch_providers'] === '8'
            && $request['watch_region'] === 'FR' && $request['with_watch_monetization_types'] === 'flatrate'
            && $request['primary_release_date.lte'] === '2026-10-05');
        Http::assertSentCount(8);
        $other = User::factory()->create();
        $this->subscribe($other);
        $this->actingAs($other)->getJson('/home/releases')->assertOk()->assertJsonPath('results.0.in_watchlist', false);
        Http::assertSentCount(8); // Shared catalog cache must not include private watchlist state.
    }

    public function test_temporarily_unavailable_catalog_returns_a_retryable_response(): void
    {
        $user = User::factory()->create();
        $this->subscribe($user);
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('discoverRecentReleases')->once()->andReturnNull());
        $this->actingAs($user)->getJson('/home/releases')->assertStatus(503)->assertJsonPath('reason', 'unavailable');
    }
}
