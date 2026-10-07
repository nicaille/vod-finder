<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CombinedSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => '']);
        Http::preventStrayRequests();
    }

    public function test_combined_search_preserves_type_platforms_and_watchlist_for_shared_ids(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [['id' => 42, 'title' => 'Dune', 'popularity' => 10]]]),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [['id' => 42, 'name' => 'Dune', 'popularity' => 20]]]),
            'api.themoviedb.org/3/movie/42/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]]]),
            'api.themoviedb.org/3/tv/42/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 337, 'provider_name' => 'Disney Plus']]]]]),
        ]);
        $user = User::factory()->create();
        $user->watchlist()->create(['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Dune']);
        $this->actingAs($user)->getJson('/search?q=Dune&type=all')->assertOk()->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.type', 'tv')->assertJsonPath('results.0.providers.0.slug', 'disneyplus')->assertJsonPath('results.0.in_watchlist', false)
            ->assertJsonPath('results.1.type', 'movie')->assertJsonPath('results.1.providers.0.slug', 'netflix')->assertJsonPath('results.1.in_watchlist', true);
        Http::assertSentCount(4);
        $this->getJson('/search?q=Dune&type=all&providers[]=disneyplus')->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.type', 'tv');
    }

    public function test_large_combined_title_search_is_paginated_before_platform_requests(): void
    {
        $titles = fn ($type) => array_map(fn ($id) => ['id' => $id, $type === 'movie' ? 'title' : 'name' => 'Dune', 'popularity' => $id], range(1, 10));
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => $titles('movie')]),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => $titles('tv')]),
            'api.themoviedb.org/3/*/watch/providers*' => Http::response(['results' => ['FR' => ['buy' => [['provider_id' => 10, 'provider_name' => 'Amazon Video']]]]]),
        ]);
        $response = $this->getJson('/search?q=Dune&type=all')->assertOk()->assertJsonCount(12, 'results')
            ->assertJsonPath('pagination.next_offset', 12)->assertJsonPath('pagination.kind', 'titles');
        $this->assertEqualsCanonicalizing(['movie', 'tv'], array_unique(array_column($response->json('results'), 'type')));
        Http::assertSentCount(14);
        $this->getJson('/search?q=Dune&type=all&offset=12')->assertOk()->assertJsonCount(8, 'results')->assertJsonPath('pagination.next_offset', null);
        Http::assertSentCount(22);
    }

    public function test_combined_filmography_includes_series_and_keeps_identical_ids_separate(): void
    {
        Http::fake([
            'api.themoviedb.org/3/person/500/combined_credits*' => Http::response(['cast' => [
                ['id' => 7, 'media_type' => 'movie', 'title' => 'Un film', 'vote_count' => 100, 'order' => 0],
                ['id' => 7, 'media_type' => 'tv', 'name' => 'Une série', 'vote_count' => 50, 'order' => 0],
            ]]),
            'api.themoviedb.org/3/*/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]]]),
        ]);
        $this->getJson('/search?q=Tom%20Cruise&type=all&person_id=500')->assertOk()->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.type', 'movie')->assertJsonPath('results.1.type', 'tv');
    }

    public function test_unknown_search_type_is_rejected_without_external_requests(): void
    {
        $this->getJson('/search?q=Dune&type=person')->assertUnprocessable();
        Http::assertNothingSent();
    }
}
