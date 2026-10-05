<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => '']);
        Http::preventStrayRequests();
    }

    public static function identityCases(): array
    {
        return [
            'shared IMDb overrides incomplete dates' => [['imdb_id' => 'nm123'], ['imdb_id' => 'nm123'], '1977-09-15', null, 1],
            'shared Wikidata' => [['wikidata_id' => 'Q123'], ['wikidata_id' => 'Q123'], null, null, 1],
            'matching birthdays without external IDs' => [[], [], '1977-09-15', '1977-09-15', 1],
            'contradictory IMDb blocks birthday match' => [['imdb_id' => 'nm123'], ['imdb_id' => 'nm456'], '1977-09-15', '1977-09-15', 2],
            'shared IMDb but contradictory Wikidata' => [['imdb_id' => 'nm123', 'wikidata_id' => 'Q123'], ['imdb_id' => 'nm123', 'wikidata_id' => 'Q456'], null, null, 2],
            'missing birthdays never match' => [[], [], null, null, 2],
            'different birthdays' => [[], [], '1977-09-15', '1978-09-15', 2],
            'impossible birthdays' => [[], [], '1977-02-31', '1977-02-31', 2],
            'invalid external IDs never match' => [['imdb_id' => 'unknown'], ['imdb_id' => 'unknown'], null, null, 2],
        ];
    }

    /** @dataProvider identityCases */
    public function test_merges_only_supported_identities(array $left, array $right, ?string $birthLeft, ?string $birthRight, int $expected): void
    {
        $person = ['name' => 'Tom Hardy', 'media_type' => 'person', 'known_for_department' => 'Acting',
            'profile_path' => '/same.jpg', 'known_for' => [['id' => 7, 'title' => 'Film partagé']]];
        Http::fake([
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => [
                $person + ['id' => 1, 'popularity' => 100], $person + ['id' => 2, 'popularity' => 1],
            ]]),
            'api.themoviedb.org/3/person/1?*' => Http::response(['id' => 1, 'birthday' => $birthLeft, 'external_ids' => $left]),
            'api.themoviedb.org/3/person/2?*' => Http::response(['id' => 2, 'birthday' => $birthRight, 'external_ids' => $right]),
        ]);
        $response = $this->getJson('/autocomplete?q=Tom%20Hardy')->assertOk()->assertJsonCount($expected, 'results');
        $response->assertJsonPath('results.0.id', $expected === 1 ? '1|2' : '1')
            ->assertJsonPath('results.0.known_titles.0', 'Film partagé')
            ->assertJsonPath('results.0.profile', 'https://image.tmdb.org/t/p/w92/same.jpg');
        $this->getJson('/autocomplete?q=Tom%20Hardy')->assertOk();
        Http::assertSentCount(3); // Identity requests and search are cached.
    }

    public function test_missing_identity_does_not_bridge_contradictory_homonyms(): void
    {
        $people = [];
        foreach ([1, 2, 3] as $id) $people[] = ['id' => $id, 'name' => 'Tom Hardy', 'media_type' => 'person', 'popularity' => 10 - $id];
        Http::fake([
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => $people]),
            'api.themoviedb.org/3/person/1?*' => Http::response(['id' => 1, 'birthday' => '1977-09-15', 'external_ids' => ['imdb_id' => 'nm123']]),
            'api.themoviedb.org/3/person/2?*' => Http::response(['id' => 2, 'birthday' => '1977-09-15']),
            'api.themoviedb.org/3/person/3?*' => Http::response(['id' => 3, 'birthday' => '1977-09-15', 'external_ids' => ['imdb_id' => 'nm456']]),
        ]);
        $this->getJson('/autocomplete?q=Tom%20Hardy')->assertOk()->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.id', '1|2')->assertJsonPath('results.1.id', '3');
    }

    public function test_identity_failure_keeps_suggestions_and_does_not_merge_them(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/multi*' => Http::response(['results' => [
                ['id' => 1, 'name' => 'Tom Hardy', 'media_type' => 'person'],
                ['id' => 2, 'name' => 'Tom Hardy', 'media_type' => 'person'],
            ]]),
            'api.themoviedb.org/3/person/*' => Http::response([], 503),
        ]);
        $this->getJson('/autocomplete?q=Tom%20Hardy')->assertOk()->assertJsonCount(2, 'results');
    }

    public function test_merged_suggestion_combines_filmographies_without_duplicate_films(): void
    {
        $film = ['id' => 10, 'title' => 'Film commun', 'media_type' => 'movie', 'release_date' => '2020-01-01'];
        Http::fake([
            'api.themoviedb.org/3/person/1/combined_credits*' => Http::response(['cast' => [$film]]),
            'api.themoviedb.org/3/person/2/combined_credits*' => Http::response(['cast' => [$film], 'crew' => [
                ['id' => 11, 'title' => 'Autre film', 'media_type' => 'movie', 'release_date' => '2024-01-01'],
            ]]),
            'api.themoviedb.org/3/movie/*/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]]]),
        ]);
        $response = $this->getJson('/search?type=movie&person_id=1%7C2')->assertOk()->assertJsonCount(2, 'results');
        $this->assertSame([11, 10], array_column($response->json('results'), 'id'));
    }
}
