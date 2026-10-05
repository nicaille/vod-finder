<?php

namespace Tests\Feature;

use App\Services\StreamingAvailabilityService;
use App\Services\TmdbService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class EpisodeReleasePillsTest extends TestCase
{
    private function popup(?string $date): string
    {
        return view('details-popup', [
            'details' => ['id' => 247718, 'name' => 'MobLand'], 'type' => 'tv', 'watchNow' => null,
            'seasons' => [['seasonNumber' => 2, 'episodes' => [['episodeNumber' => 4, 'streamingOptions' => ['fr' => [
                ['service' => ['name' => 'Paramount+'], 'type' => 'subscription', 'link' => 'https://example.test/episode'],
            ]]]]]],
            'episodeMeta' => [2 => [4 => ['name' => 'Blank Curtain', 'air_date' => $date]]],
        ])->render();
    }

    public function test_future_date_replaces_the_episode_viewing_link(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
        $html = $this->popup('2026-10-09');
        $this->assertStringContainsString('Diffusion prévue le 09/10/2026', $html);
        $this->assertStringContainsString('datetime="2026-10-09"', $html);
        $this->assertStringNotContainsString('https://example.test/episode', $html);
    }

    public function test_released_episodes_keep_their_viewing_links_using_the_paris_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 22:30:00', 'UTC'));
        foreach (['2026-10-08', '2026-10-09', null] as $date) {
            $html = $this->popup($date);
            $this->assertStringContainsString('https://example.test/episode', $html);
            $this->assertStringNotContainsString('Diffusion prévue le', $html);
        }
    }

    public function test_future_episodes_missing_from_availability_are_added_from_tmdb(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getDetails')->andReturn(['id' => 247718, 'name' => 'MobLand', 'seasons' => [['season_number' => 2, 'name' => 'Saison 2']]]);
            $mock->shouldReceive('getWatchProviders', 'mapProviders', 'getRecommendations')->andReturn([]);
            $mock->shouldReceive('getTvSeason')->andReturn(['episodes' => [
                ['episode_number' => 4, 'name' => 'Blank Curtain', 'air_date' => '2026-10-09'],
                ['episode_number' => 5, 'name' => 'Sans date', 'air_date' => null],
                ['episode_number' => 6, 'name' => 'Date invalide', 'air_date' => '2026-02-30'],
            ]]);
        });
        foreach ([false, true] as $enabled) {
            $this->mock(StreamingAvailabilityService::class, function ($mock) use ($enabled) {
                $mock->shouldReceive('getDeepLinksForTmdbId')->andReturn([]);
                $mock->shouldReceive('isEnabled')->andReturn($enabled);
                if ($enabled) {
                    $mock->shouldReceive('getShowWithSeasonsFromTmdbId')->andReturn(['seasons' => [
                        ['seasonNumber' => 2, 'episodes' => [['episodeNumber' => 3, 'title' => 'Déjà disponible', 'streamingOptions' => ['fr' => [
                            ['service' => ['name' => 'Paramount+'], 'type' => 'subscription', 'link' => 'https://example.test/released'],
                        ]]]]],
                    ]]);
                }
            });
            $response = $this->get('/title/tv/247718', ['X-Requested-With' => 'XMLHttpRequest']);
            $response->assertOk()->assertSee('Blank Curtain')->assertSee('Diffusion prévue le 09/10/2026')
                ->assertSee('Date de diffusion non annoncée')->assertDontSee('Diffusion prévue le 02/03/2026');
            $seasons = $response->viewData('seasons');
            $this->assertSame($enabled ? [3, 4, 5, 6] : [4, 5, 6], array_column($seasons[0]['episodes'], 'episodeNumber'));
            if ($enabled) {
                $response->assertSee('https://example.test/released');
            }
        }
    }
}
