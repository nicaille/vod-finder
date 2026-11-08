<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnavailableTitleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suggested_title_without_local_offers_keeps_an_accessible_detail_page(): void
    {
        config(['services.tmdb.key' => 'isolated-test', 'services.streaming_availability.key' => '', 'services.streaming_availability.direct_key' => '']);
        Http::preventStrayRequests();
        $series = ['id' => 85789, 'name' => "M'entends-tu?", 'first_air_date' => '2019-01-16', 'overview' => 'Trois amies à Montréal.'];
        Http::fake([
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => [$series + ['media_type' => 'tv']]]),
            'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [$series]]),
            'api.themoviedb.org/3/search/movie*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/tv/85789/watch/providers*' => Http::response(['results' => ['CA' => ['flatrate' => [['provider_name' => 'Netflix']]]]]),
            'api.themoviedb.org/3/tv/85789/recommendations*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/tv/85789*' => Http::response($series + ['seasons' => [], 'credits' => ['cast' => [], 'crew' => []]]),
        ]);
        $query = http_build_query(['q' => "M'entends tu ?", 'type' => 'all', 'country' => 'FR']);
        $this->getJson('/autocomplete?'.$query)->assertOk()->assertJsonPath('results.0.id', 85789);
        // Availability filters remain strict: Canadian offers do not become French offers.
        $this->getJson('/search?'.$query)->assertOk()->assertJsonCount(0, 'results')->assertJsonPath('pagination.total', 1);
        $this->get('/title/tv/85789?country=FR', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee("M'entends-tu?")->assertSee('Trois amies à Montréal.');
    }
}
