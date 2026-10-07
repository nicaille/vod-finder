<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonSearchPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => '']);
        Http::preventStrayRequests();
    }

    private function fakeFilmography(int $count = 105): void
    {
        $films = array_map(fn ($id) => ['id' => $id, 'media_type' => 'movie', 'title' => 'Film '.$id, 'release_date' => sprintf('%04d-01-01', 1900 + $id)], range(1, $count));
        Http::fake([
            'api.themoviedb.org/3/person/*/combined_credits*' => Http::response(['cast' => $films, 'crew' => [$films[0]]]),
            'api.themoviedb.org/3/movie/*/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]]]),
        ]);
    }

    public function test_large_filmography_only_checks_availability_for_the_requested_slice(): void
    {
        $this->fakeFilmography();
        $first = $this->getJson('/search?q=Tom%20Cruise&person_id=500')->assertOk()->assertJsonCount(12, 'results')
            ->assertJsonPath('pagination.next_offset', 12)->assertJsonPath('pagination.total', 105);
        $this->assertSame(range(105, 94), array_column($first->json('results'), 'id'));
        Http::assertSentCount(13); // One filmography and twelve provider lookups, not 105.

        $second = $this->getJson('/search?q=Tom%20Cruise&person_id=500&offset=12')->assertOk()->assertJsonCount(12, 'results')
            ->assertJsonPath('pagination.next_offset', 24);
        $this->assertSame(range(93, 82), array_column($second->json('results'), 'id'));
        Http::assertSentCount(25); // Cached filmography reused.

        $this->getJson('/search?person_id=500&offset=96')->assertOk()->assertJsonCount(9, 'results')->assertJsonPath('pagination.next_offset', null);
    }

    public function test_a_fully_filtered_slice_keeps_a_cursor_to_the_remaining_films(): void
    {
        $this->fakeFilmography(25);
        $this->getJson('/search?person_id=500&providers[]=disneyplus')->assertOk()->assertJsonCount(0, 'results')
            ->assertJsonPath('pagination.next_offset', 12)->assertJsonPath('pagination.total', 25);
        Http::assertSentCount(13);
    }

    public function test_merged_people_are_deduplicated_before_pagination(): void
    {
        $this->fakeFilmography(15);
        $this->getJson('/search?person_ids[]=500&person_ids[]=501&offset=12')->assertOk()->assertJsonCount(3, 'results')
            ->assertJsonPath('pagination.total', 15)->assertJsonPath('pagination.next_offset', null);
        Http::assertSentCount(5);
    }

    public function test_invalid_offset_is_rejected_before_any_external_request(): void
    {
        $this->getJson('/search?person_id=500&offset=-1')->assertUnprocessable();
        Http::assertNothingSent();
    }
}
