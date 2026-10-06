<?php

namespace Tests\Feature;

use App\Models\{EpisodeAlert, SeriesEpisode, TrackedSeries, User};
use App\Services\{SeriesCalendarService, TmdbService, TvmazeCalendarService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EpisodeBroadcastTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    private function details(): array
    {
        return ['id' => 97546, 'name' => 'Ted Lasso', 'external_ids' => ['imdb_id' => 'tt10986410'],
            'seasons' => [['season_number' => 4]],
            'next_episode_to_air' => ['season_number' => 4, 'episode_number' => 10, 'name' => 'Un dernier verre', 'air_date' => '2026-10-06']];
    }

    private function prepare(?string $stamp = '2026-10-06T18:00:00-07:00', array $extra = []): TrackedSeries
    {
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('getTvSeason')->andReturn(['episodes' => []]));
        Http::fake([
            'api.tvmaze.com/lookup/shows*' => Http::response(['id' => 49944, 'externals' => ['imdb' => 'tt10986410']]),
            'api.tvmaze.com/shows/49944/episodes*' => Http::response(array_merge([
                ['season' => 4, 'number' => 10, 'name' => 'One Last Drink', 'airstamp' => $stamp],
            ], $extra)),
        ]);
        return TrackedSeries::create(['tmdb_id' => 97546, 'name' => 'Ted Lasso']);
    }

    public function test_midnight_crossing_keeps_the_episode_upcoming_until_its_actual_instant_and_delays_alerts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 23:00:00', 'UTC')); // 7 October, 01:00 in Paris.
        $series = $this->prepare();
        $user = User::factory()->create(['notify_opt_in' => true]);
        $follow = $user->seriesFollows()->create(['tracked_series_id' => $series->id]);
        $calendar = app(SeriesCalendarService::class);
        $this->assertTrue($calendar->sync($series, $this->details()));
        $episode = $series->episodes()->firstOrFail();
        $this->assertSame('2026-10-07 01:00:00', $episode->getRawOriginal('airs_at'));
        $this->assertSame('2026-10-07', $episode->air_date->toDateString());
        $this->assertSame('Un dernier verre', $episode->name);
        $this->actingAs($user)->get('/series')->assertOk()->assertSee('07/10/2026 à 03:00')->assertSee('Heure de Paris');
        $this->assertSame(0, $calendar->createAlerts($series));
        // Even a previously created alert is hidden until the known instant.
        EpisodeAlert::create(['user_id' => $user->id, 'series_episode_id' => $episode->id]);
        $this->assertSame(0, EpisodeAlert::announced()->count());
        EpisodeAlert::query()->delete();
        $this->travelTo(CarbonImmutable::parse('2026-10-07 01:00:00', 'UTC'));
        $this->assertSame(0, SeriesEpisode::upcoming()->count());
        $this->assertSame(1, $calendar->createAlerts($series));
        $this->assertSame(1, EpisodeAlert::announced()->count());
        $this->assertSame(0, $calendar->createAlerts($series));
    }

    public function test_source_dates_are_not_shifted_and_a_recent_next_episode_remains_explicitly_uncertain(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 23:00:00', 'UTC'));
        $series = $this->prepare(null);
        $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $this->details()));
        $episode = $series->episodes()->firstOrFail();
        $this->assertNull($episode->airs_at);
        $this->assertSame('2026-10-06', $episode->air_date->toDateString());
        $user = User::factory()->create(['notify_opt_in' => true]);
        $follow = $user->seriesFollows()->create(['tracked_series_id' => $series->id]);
        $follow->forceFill(['created_at' => '2026-10-05 10:00:00'])->save();
        $this->assertSame(0, app(SeriesCalendarService::class)->createAlerts($series));
        $this->actingAs($user)->get('/series')->assertOk()->assertSee('S04E10')->assertSee('06/10/2026')
            ->assertSee('horaire et date locale à confirmer')->assertDontSee('03:00');
        $this->travel(1)->days();
        $this->assertSame(0, SeriesEpisode::upcoming()->count());
        $this->assertSame(1, app(SeriesCalendarService::class)->createAlerts($series));
    }

    public function test_all_future_episodes_are_imported_even_when_the_tmdb_season_is_incomplete(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01', 'UTC'));
        $series = $this->prepare('2026-10-07T01:00:00Z', [
            ['season' => 5, 'number' => 1, 'name' => 'Future premiere', 'airstamp' => '2027-08-01T01:00:00Z'],
            ['season' => 5, 'number' => 2, 'name' => 'Future episode', 'airstamp' => '2027-08-08T01:00:00Z'],
            ['season' => 5, 'number' => 3, 'name' => 'Future date only', 'airdate' => '2027-08-15', 'airstamp' => null],
            ['season' => 0, 'number' => 1, 'name' => 'Special', 'airstamp' => '2027-08-08T01:00:00Z'],
        ]);
        $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $this->details()));
        $this->assertSame(4, SeriesEpisode::upcoming()->count());
        $this->assertDatabaseHas('series_episodes', ['season_number' => 5, 'episode_number' => 2, 'air_date' => '2027-08-08']);
        $this->assertDatabaseHas('series_episodes', ['season_number' => 5, 'episode_number' => 3, 'air_date' => '2027-08-15', 'airs_at' => null, 'calendar_source' => 'tvmaze']);
    }

    public function test_unknown_next_episode_date_is_visible_without_inventing_a_release_date(): void
    {
        $series = $this->prepare(null);
        $details = $this->details();
        $details['next_episode_to_air']['air_date'] = null;
        $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $details));
        $user = User::factory()->create();
        $user->seriesFollows()->create(['tracked_series_id' => $series->id]);
        $this->actingAs($user)->get('/series')->assertOk()->assertSee('S04E10')->assertSee('Épisode annoncé · date à confirmer');
        $this->assertSame(0, app(SeriesCalendarService::class)->createAlerts($series));
    }

    public function test_followed_series_detail_uses_the_same_local_time_and_missing_future_episodes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 23:00:00', 'UTC'));
        config(['services.streaming_availability.key' => '']);
        $series = $this->prepare('2026-10-07T01:00:00Z', [
            ['season' => 5, 'number' => 1, 'name' => 'Future premiere', 'airstamp' => '2027-08-01T01:00:00Z'],
        ]);
        $mock = app(TmdbService::class);
        $mock->shouldReceive('getDetails')->andReturn($this->details());
        $mock->shouldReceive('getAvailability', 'getRecommendations')->andReturn([]);
        $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $this->details()));
        $user = User::factory()->create();
        $user->seriesFollows()->create(['tracked_series_id' => $series->id]);
        $this->actingAs($user)->get('/title/tv/97546', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Diffusion prévue le 07/10/2026 à 03:00')->assertSee('Heure de Paris · TVmaze')
            ->assertSee('Future premiere')->assertSee('01/08/2027 à 03:00');
    }

    public function test_following_after_the_known_instant_does_not_send_a_historical_same_day_alert(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 02:00:00', 'UTC'));
        $series = $this->prepare();
        $user = User::factory()->create(['notify_opt_in' => true]);
        $user->seriesFollows()->create(['tracked_series_id' => $series->id]);
        $calendar = app(SeriesCalendarService::class);
        $this->assertTrue($calendar->sync($series, $this->details()));
        $this->assertSame(0, $calendar->createAlerts($series));
    }

    public function test_paris_conversion_respects_winter_time_and_the_application_timezone(): void
    {
        config(['app.timezone' => 'Europe/Paris']);
        $series = $this->prepare('2026-10-25T01:30:00Z');
        $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $this->details()));
        $episode = $series->episodes()->firstOrFail();
        $this->assertSame('2026-10-25 01:30:00', $episode->getRawOriginal('airs_at'));
        $this->assertSame('2026-10-25T02:30:00+01:00', $episode->airs_at->timezone('Europe/Paris')->toIso8601String());
    }

    public function test_timezone_less_or_invalid_timestamps_do_not_override_a_tmdb_date(): void
    {
        foreach (['2026-10-07T03:00:00', '2026-02-30T01:00:00Z', '2026-10-07T27:00:00Z'] as $stamp) {
            \Illuminate\Support\Facades\Cache::flush();
            $series = $this->prepare($stamp);
            $this->assertTrue(app(SeriesCalendarService::class)->sync($series, $this->details()));
            $this->assertNull($series->episodes()->firstOrFail()->airs_at);
            $this->assertSame('2026-10-06', $series->episodes()->firstOrFail()->air_date->toDateString());
            $series->delete();
        }
    }

    public function test_provider_outage_preserves_existing_precise_calendar_without_generating_alerts(): void
    {
        $series = $this->prepare();
        $service = app(SeriesCalendarService::class);
        $this->assertTrue($service->sync($series, $this->details()));
        \Illuminate\Support\Facades\Cache::flush();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['api.tvmaze.com/*' => Http::response([], 503)]);
        $this->assertFalse($service->sync($series, $this->details()));
        $this->assertSame('2026-10-07 01:00:00', $series->episodes()->firstOrFail()->getRawOriginal('airs_at'));
    }

    public function test_external_identity_must_match_and_lookup_failure_falls_back_without_title_search(): void
    {
        Http::fake(['api.tvmaze.com/lookup/shows*' => Http::response(['id' => 1, 'externals' => ['imdb' => 'tt999']])]);
        $service = app(TvmazeCalendarService::class);
        $this->assertSame([], $service->episodes('tt10986410'));
        $this->assertSame([], $service->episodes(null));
        Http::assertSentCount(1);
    }

    public function test_withdrawn_precise_timestamp_does_not_leave_a_stale_future_episode(): void
    {
        $series = $this->prepare();
        $service = app(SeriesCalendarService::class);
        $this->assertTrue($service->sync($series, $this->details()));
        \Illuminate\Support\Facades\Cache::flush();
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([
            'api.tvmaze.com/lookup/shows*' => Http::response(['id' => 49944, 'externals' => ['imdb' => 'tt10986410']]),
            'api.tvmaze.com/shows/49944/episodes*' => Http::response([]),
        ]);
        $details = $this->details();
        $details['next_episode_to_air'] = null;
        $this->assertTrue($service->sync($series, $details));
        $this->assertNull($series->episodes()->firstOrFail()->air_date);
        $this->assertNull($series->episodes()->firstOrFail()->airs_at);
        $this->assertSame(0, SeriesEpisode::upcoming()->count());
    }
}
