<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvailabilityPriorityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => 'test-key', 'cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    private function fake(array $options, int $status = 200, int $coverageStatus = 200): void
    {
        Http::fake([
            'streaming-availability.p.rapidapi.com/countries/fr*' => Http::response(['services' => [
                ['id' => 'disney', 'name' => 'Disney+'], ['id' => 'prime', 'name' => 'Prime Video'],
            ]], $coverageStatus),
            'streaming-availability.p.rapidapi.com/shows/movie/105*' => Http::response(['tmdbId' => 'movie/105', 'streamingOptions' => $options], $status),
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
                ['id' => 105, 'title' => 'Retour vers le futur', 'release_date' => '1985-07-03'],
            ]]),
            'api.themoviedb.org/3/movie/105/watch/providers*' => Http::response(['results' => ['FR' => [
                'flatrate' => [['provider_name' => 'Amazon Prime Video'], ['provider_name' => 'Canal+']],
                'rent' => [['provider_name' => 'Amazon Video']],
            ]]]),
            'api.themoviedb.org/3/movie/105/recommendations*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/movie/105*' => Http::response(['id' => 105, 'title' => 'Retour vers le futur', 'overview' => 'Résumé TMDb']),
        ]);
    }

    private function disney(): array
    {
        return ['service' => ['id' => 'disney', 'name' => 'Disney+'], 'type' => 'subscription', 'link' => 'https://www.disneyplus.com/play/example'];
    }

    public function test_disney_search_and_detail_find_back_to_the_future_missing_from_tmdb(): void
    {
        $this->fake(['fr' => [$this->disney()]]);
        $this->getJson('/search?q=Retour%20vers%20le%20futur&providers[]=disneyplus&access=flatrate')
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.providers.0.slug', 'disneyplus')
            ->assertJsonPath('results.0.providers.0.source', 'streaming_availability');
        $this->get('/title/movie/105', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Résumé TMDb')->assertSee('Disney+')->assertSee('https://www.disneyplus.com/play/example');
        $this->assertSame(['disneyplus', 'canalplus'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
        // Shared 24-hour caches avoid extra external calls for the same title.
        Http::assertSentCount(6);
    }

    public function test_empty_success_does_not_resurrect_stale_offers_but_keeps_uncovered_canal(): void
    {
        $this->fake(['fr' => []]);
        $this->assertSame(['canalplus'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
        $this->getJson('/search?q=Retour&providers[]=prime&access=flatrate')->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_offers_from_another_country_are_not_used(): void
    {
        $this->fake(['us' => [$this->disney()]]);
        $this->assertSame(['canalplus'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
    }

    public function test_api_error_uses_tmdb_fallback(): void
    {
        $this->fake([], 503);
        $this->assertSame(['prime', 'canalplus', 'prime'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
    }

    public function test_coverage_error_uses_tmdb_fallback(): void
    {
        $this->fake([], 200, 429);
        $this->assertSame(['prime', 'canalplus', 'prime'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
    }

    public function test_paid_channel_and_purchase_are_not_treated_as_base_prime_subscription(): void
    {
        $this->fake(['fr' => [
            ['service' => ['id' => 'prime', 'name' => 'Prime Video'], 'type' => 'addon', 'addon' => ['name' => 'Paramount+'], 'link' => 'https://www.primevideo.com/paramount'],
            ['service' => ['id' => 'prime', 'name' => 'Prime Video'], 'type' => 'buy', 'link' => 'https://www.primevideo.com/buy', 'price' => ['amount' => '9.99', 'currency' => 'EUR']],
        ]]);
        $this->getJson('/search?q=Retour&providers[]=prime&access=flatrate')->assertOk()->assertJsonCount(0, 'results');
        $this->getJson('/search?q=Retour&providers[]=paramountplus&access=flatrate')->assertOk()
            ->assertJsonCount(1, 'results')->assertJsonPath('results.0.providers.0.via', 'prime');
        $this->getJson('/search?q=Retour&providers[]=prime&access=buy')->assertOk()
            ->assertJsonPath('results.0.providers.0.access', 'buy')->assertJsonPath('results.0.providers.0.sa_price.amount', '9.99');
    }

    public function test_unknown_addon_and_unsafe_link_are_never_promoted_to_base_subscriptions(): void
    {
        $this->fake(['fr' => [
            ['service' => ['id' => 'prime', 'name' => 'Prime Video'], 'type' => 'addon', 'addon' => ['name' => 'Unknown channel'], 'link' => 'https://www.primevideo.com/channel'],
            $this->disney() + ['expiresOn' => now()->subDay()->timestamp],
            array_replace($this->disney(), ['link' => 'javascript:alert(1)']),
        ]]);
        $this->assertSame(['canalplus'], array_column(app(TmdbService::class)->getAvailability(105), 'slug'));
    }
}
