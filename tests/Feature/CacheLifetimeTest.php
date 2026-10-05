<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CacheLifetimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-06 12:00:00', 'Europe/Paris'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public static function detailCases(): array
    {
        return [
            'movie stays cached for 30 days' => ['movie', [], 30 * 86400],
            'ended series for seven days' => ['tv', ['status' => 'Ended'], 7 * 86400],
            'canceled series for seven days' => ['tv', ['status' => 'Canceled'], 7 * 86400],
            'ongoing without a date' => ['tv', ['status' => 'Returning Series'], 86400],
            'distant episode caps at a day' => ['tv', ['next_episode_to_air' => ['air_date' => '2026-10-12']], 86400],
            'tomorrow expires at Paris midnight' => ['tv', ['next_episode_to_air' => ['air_date' => '2026-10-07']], 12 * 3600],
            'episode today refreshes quickly' => ['tv', ['next_episode_to_air' => ['air_date' => '2026-10-06']], 1800],
            'stale expected episode refreshes quickly' => ['tv', ['next_episode_to_air' => ['air_date' => '2026-10-05']], 1800],
            'invalid date is bounded' => ['tv', ['next_episode_to_air' => ['air_date' => 'bad-date']], 86400],
        ];
    }

    /** @dataProvider detailCases */
    public function test_detail_cache_expires_at_the_expected_boundary(string $type, array $metadata, int $seconds): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::response(['id' => 42] + $metadata)]);
        $service = app(TmdbService::class);
        $this->assertSame(42, $service->getDetails(42, $type)['id']);
        $this->travel($seconds - 1)->seconds();
        $this->assertSame(42, $service->getDetails(42, $type)['id']);
        Http::assertSentCount(1);
        $this->travel(2)->seconds();
        $service->getDetails(42, $type);
        Http::assertSentCount(2);
    }

    public function test_search_and_platform_availability_refresh_after_one_day(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [['id' => 42, 'title' => 'Example']]]),
            'api.themoviedb.org/3/movie/42/watch/providers*' => Http::response(['results' => ['FR' => [
                'flatrate' => [['provider_id' => 1899, 'provider_name' => 'HBO Max']],
            ]]]),
        ]);
        $service = app(TmdbService::class);
        $service->search('Example');
        $service->getWatchProviders(42);
        $this->travel(23)->hours();
        $service->search('Example');
        $service->getWatchProviders(42);
        Http::assertSentCount(2);
        $this->travel(2)->hours();
        $service->search('Example');
        $service->getWatchProviders(42);
        Http::assertSentCount(4);
    }

    public function test_calendar_stays_fresh_independently_of_a_long_lived_detail_cache(): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::response(['id' => 42, 'status' => 'Ended'])]);
        $service = app(TmdbService::class);
        $service->getDetails(42, 'tv');
        $service->getTvEpisodeCalendar(42);
        $this->travel(31)->minutes();
        $service->getDetails(42, 'tv');
        $service->getTvEpisodeCalendar(42);
        Http::assertSentCount(3);
    }

    public function test_season_cache_also_expires_before_an_upcoming_episode(): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::response(['episodes' => [
            ['air_date' => '2026-10-01'], ['air_date' => '2026-10-07'],
        ]])]);
        $service = app(TmdbService::class);
        $service->getTvSeason(42, 1);
        $this->travel(11)->hours();
        $service->getTvSeason(42, 1);
        Http::assertSentCount(1);
        $this->travel(2)->hours();
        $service->getTvSeason(42, 1);
        Http::assertSentCount(2);
    }

    public function test_failed_detail_requests_do_not_poison_the_cache(): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::sequence()
            ->push([], 503)->push([], 503)->push(['id' => 42])]);
        $service = app(TmdbService::class);
        $this->assertEmpty($service->getDetails(42, 'movie'));
        $this->assertSame(42, $service->getDetails(42, 'movie')['id']);
    }

    public function test_failed_provider_requests_are_retried_instead_of_cached_for_a_day(): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::sequence()->push([], 503)->push([], 503)
            ->push(['results' => ['FR' => ['flatrate' => [['provider_name' => 'Netflix']]]]])]);
        $service = app(TmdbService::class);
        $this->assertSame([], $service->getWatchProviders(42));
        $this->assertSame('Netflix', $service->getWatchProviders(42)[0]['provider_name']);
    }

    public function test_search_filtering_recovers_after_a_temporary_availability_error(): void
    {
        Http::fake(['api.themoviedb.org/3/*' => Http::sequence()->push([], 503)->push([], 503)
            ->push(['results' => ['FR' => ['flatrate' => [['provider_name' => 'Netflix']]]]])]);
        $service = app(TmdbService::class);
        $results = [['id' => 42, 'title' => 'Example']];
        $this->assertSame([], $service->hydrateAndFilterByProvidersAccess($results, 'movie', 'FR', ['netflix'], 'flatrate'));
        $this->assertCount(1, $service->hydrateAndFilterByProvidersAccess($results, 'movie', 'FR', ['netflix'], 'flatrate'));
    }

    public function test_streaming_availability_recovers_from_an_error_and_caches_valid_links_for_a_day(): void
    {
        config(['services.streaming_availability.key' => 'test-key']);
        Http::fake(['streaming-availability.p.rapidapi.com/*' => Http::sequence()->push([], 503)
            ->push(['streamingOptions' => ['fr' => [[
                'service' => ['id' => 'paramount', 'name' => 'Paramount+'],
                'streamingType' => 'subscription', 'link' => 'https://www.paramountplus.com/example',
            ]]]])->push(['streamingOptions' => []])]);
        $service = app(\App\Services\StreamingAvailabilityService::class);
        $this->assertSame([], $service->getDeepLinksForTmdbId(42));
        $this->assertArrayHasKey('paramountplus', $service->getDeepLinksForTmdbId(42));
        $this->travel(23)->hours();
        $this->assertArrayHasKey('paramountplus', $service->getDeepLinksForTmdbId(42));
        Http::assertSentCount(2);
        $this->travel(2)->hours();
        $this->assertSame([], $service->getDeepLinksForTmdbId(42));
        Http::assertSentCount(3);
    }
}
