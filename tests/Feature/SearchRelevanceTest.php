<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchRelevanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_title_matches_beat_popularity_and_apostrophe_false_matches(): void
    {
        $items = [
            ['id' => 4, 'title' => "Anatomie d'une chute", 'popularity' => 900],
            ['id' => 5, 'title' => 'Planet Dune', 'popularity' => 1],
            ['id' => 2, 'title' => 'Dune : Deuxième partie', 'popularity' => 300],
            ['id' => 3, 'title' => 'Dune', 'popularity' => 20],
            ['id' => 1, 'title' => 'Dune', 'popularity' => 100],
            ['id' => 6, 'title' => 'La Dune', 'popularity' => 2],
            ['id' => 7, 'title' => 'Un autre titre', 'original_title' => 'Dune', 'popularity' => 1000],
        ];
        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('search')->once()->with('Dune', 'movie')->andReturn($items);
        $tmdb->shouldReceive('hydrateAndFilterByProvidersAccess')->once()->andReturnUsing(fn ($results) => $results);

        $response = $this->getJson('/search?q=Dune&type=movie')->assertOk();
        $this->assertSame([1, 3, 2, 6, 5, 7, 4], array_column($response->json('results'), 'id'));
    }

    public function test_autocomplete_reserves_content_places_and_ranks_people_by_name(): void
    {
        $items = [];
        for ($id = 1; $id <= 10; $id++) {
            $items[] = ['id' => $id, 'media_type' => 'person', 'name' => 'Dune Personne '.$id, 'popularity' => 1000];
        }
        $items[] = ['id' => 20, 'media_type' => 'person', 'name' => 'Dune', 'known_for_department' => 'Directing', 'popularity' => 1];
        $items[] = ['id' => 100, 'media_type' => 'movie', 'title' => 'Dune', 'release_date' => '2021-09-15'];
        $items[] = ['id' => 101, 'media_type' => 'tv', 'name' => 'Dune : Les origines', 'first_air_date' => '2024-11-17'];
        $this->mock(TmdbService::class)->shouldReceive('searchMulti')->once()->with('Dune')->andReturn($items);

        $response = $this->getJson('/autocomplete?q=Dune')->assertOk()->assertJsonCount(8, 'results');
        $response->assertJsonPath('results.0.id', 100)->assertJsonPath('results.1.id', 101)
            ->assertJsonPath('results.2.id', '20')->assertJsonPath('results.2.department', 'Directing');
    }

    public function test_director_filmography_includes_crew_and_preserves_date_order(): void
    {
        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('getPersonCombinedCredits')->once()->with(12)->andReturn([
            'cast' => [['id' => 1, 'media_type' => 'movie', 'title' => 'Ancien', 'release_date' => '2000-01-01']],
            'crew' => [
                ['id' => 2, 'media_type' => 'movie', 'title' => 'Récent', 'release_date' => '2024-01-01'],
                ['id' => 2, 'media_type' => 'movie', 'title' => 'Récent', 'release_date' => '2024-01-01'],
                ['id' => 3, 'media_type' => 'tv', 'name' => 'Une série'],
            ],
        ]);
        $tmdb->shouldReceive('hydrateAndFilterByProvidersAccess')->once()->andReturnUsing(fn ($results) => $results);
        $response = $this->getJson('/search?q=Réalisateur&type=movie&person_id=12')->assertOk();
        $this->assertSame([2, 1], array_column($response->json('results'), 'id'));
    }

    public function test_series_title_matching_ignores_accents_and_punctuation(): void
    {
        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('search')->once()->with('serie ete', 'tv')->andReturn([
            ['id' => 1, 'name' => 'Une autre série', 'popularity' => 100],
            ['id' => 2, 'name' => 'Série : Été', 'popularity' => 1],
        ]);
        $tmdb->shouldReceive('hydrateAndFilterByProvidersAccess')->once()->andReturnUsing(fn ($results) => $results);
        $this->getJson('/search?q=serie%20ete&type=tv')->assertOk()->assertJsonPath('results.0.id', 2);
    }
}
