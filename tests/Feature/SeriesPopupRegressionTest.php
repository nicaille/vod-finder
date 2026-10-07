<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{StreamingAvailabilityService, TmdbService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesPopupRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_series_popup_accepts_announced_seasons_without_episodes(): void
    {
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getDetails')->andReturn(['id' => 236235, 'name' => 'The Gentlemen',
                'seasons' => [['season_number' => 1], ['season_number' => 2]]]);
            $mock->shouldReceive('getAvailability', 'getRecommendations')->andReturn([]);
            $mock->shouldReceive('getTvSeason')->with(236235, 1, 'fr-FR')->andReturn(['episodes' => [
                ['episode_number' => 1, 'name' => 'Double Héritage', 'overview' => 'Premier épisode.', 'air_date' => '2024-03-07'],
            ]]);
            $mock->shouldReceive('getTvSeason')->with(236235, 1, 'en-US')->andReturn(['episodes' => []]);
            $mock->shouldReceive('getTvSeason')->with(236235, 2, 'fr-FR')->andReturn(['episodes' => []]);
        });
        $this->mock(StreamingAvailabilityService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('getShowWithSeasonsFromTmdbId')->andReturn(['seasons' => [
                ['seasonNumber' => 1, 'episodes' => [['episodeNumber' => 1]]],
                ['seasonNumber' => 2, 'title' => 'Saison 2'],
            ]]);
        });
        $this->actingAs(User::factory()->create())->get('/title/tv/236235?country=FR', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('The Gentlemen')->assertSee('Double Héritage')->assertSee('popup-content')
            ->assertViewHas('seasons', fn ($seasons) => $seasons[1]['episodes'] === []);
    }
}
