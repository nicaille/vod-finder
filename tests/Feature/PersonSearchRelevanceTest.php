<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonSearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private function filmography(): void
    {
        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('getPersonCombinedCredits')->with(500)->andReturn(['cast' => [
            ['id' => 1, 'media_type' => 'movie', 'title' => 'Top Gun', 'release_date' => '1986-05-16', 'character' => 'Maverick', 'order' => 0, 'vote_count' => 9966],
            ['id' => 2, 'media_type' => 'movie', 'title' => 'Top Gun : Maverick', 'release_date' => '2022-05-21', 'character' => 'Maverick', 'order' => 0, 'vote_count' => 11663],
            ['id' => 3, 'media_type' => 'movie', 'title' => 'Euro 2020', 'release_date' => '2024-05-07', 'character' => 'Self (archive footage)', 'order' => 18, 'genre_ids' => [99], 'popularity' => 1000, 'vote_count' => 999999],
            ['id' => 4, 'media_type' => 'movie', 'title' => 'Documentaire', 'release_date' => '2026-01-01', 'character' => 'Self', 'genre_ids' => [99]],
        ], 'crew' => [
            ['id' => 1, 'media_type' => 'movie', 'title' => 'Top Gun', 'job' => 'Producer', 'vote_count' => 9966],
            ['id' => 5, 'media_type' => 'movie', 'title' => 'Production', 'release_date' => '2025-01-01', 'job' => 'Producer', 'vote_count' => 20000],
        ]]);
        $tmdb->shouldReceive('hydrateAndFilterByProvidersAccess')->andReturnUsing(fn ($items) => $items);
    }

    public function test_known_acting_roles_beat_productions_documentaries_and_archives(): void
    {
        $this->filmography();
        $response = $this->getJson('/search?person_id=500&sort=relevance')->assertOk();
        $this->assertSame([2, 1, 5, 4, 3], array_column($response->json('results'), 'id'));
        $response->assertJsonPath('results.0.person_role', 'Interprétation')->assertJsonPath('results.2.person_role', 'Production')->assertJsonPath('results.4.person_role', 'Images d’archives');
    }

    public function test_date_sort_is_applied_to_the_filmography_before_pagination(): void
    {
        $this->filmography();
        $response = $this->getJson('/search?person_id=500&sort=year_asc')->assertOk();
        $this->assertSame([1, 2, 3, 5, 4], array_column($response->json('results'), 'id'));
    }
}
