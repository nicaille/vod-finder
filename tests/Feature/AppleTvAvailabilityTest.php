<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppleTvAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ted_lasso_is_available_with_the_current_apple_tv_name(): void
    {
        config(['services.tmdb.key' => 'test-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
                ['id' => 97546, 'name' => 'Ted Lasso', 'first_air_date' => '2020-08-14'],
            ]]),
            'api.themoviedb.org/3/tv/97546/watch/providers*' => Http::response(['results' => ['FR' => [
                'flatrate' => [['provider_id' => 350, 'provider_name' => 'Apple TV']],
            ]]]),
        ]);
        $this->getJson('/search?q=Ted%20Lasso&type=tv&access=flatrate&providers[]=appletv')
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.title', 'Ted Lasso')
            ->assertJsonPath('results.0.providers.0.slug', 'appletv');
        // Correct recognition must preserve strict filtering for unrelated subscriptions.
        $this->getJson('/search?q=Ted%20Lasso&type=tv&access=flatrate&providers[]=netflix')
            ->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_apple_subscription_id_survives_renames_without_confusing_the_store(): void
    {
        $service = app(TmdbService::class);
        $mapped = $service->mapProviders([
            ['provider_id' => 350, 'provider_name' => 'Another Apple brand', 'access' => 'flatrate'],
            ['provider_id' => 2, 'provider_name' => 'Apple iTunes', 'access' => 'buy'],
        ]);
        $this->assertSame(['flatrate', 'buy'], array_column($mapped, 'access'));
        $this->assertSame(['appletv', 'appletv'], array_column($mapped, 'slug'));
    }
}
