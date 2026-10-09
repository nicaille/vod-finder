<?php

namespace Tests\Feature;

use App\Models\EpisodeAlert;
use App\Models\SeriesEpisode;
use App\Models\SeriesFollow;
use App\Models\TrackedSeries;
use App\Models\User;
use App\Services\SeriesCalendarService;
use App\Services\TmdbService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesFollowingTest extends TestCase
{
    use RefreshDatabase;

    private function series(): TrackedSeries
    {
        return TrackedSeries::create(['tmdb_id' => 247718, 'name' => 'MobLand']);
    }

    private function details(): array
    {
        return ['id' => 247718, 'name' => 'MobLand', 'seasons' => [['season_number' => 0], ['season_number' => 2]]];
    }

    private function episodes(?string $date = '2026-10-09'): array
    {
        return ['episodes' => [['episode_number' => 4, 'name' => 'Blank Curtain', 'air_date' => $date]]];
    }

    private function follow(TrackedSeries $series, User $user, bool $alerts = true, string $since = '2026-10-05 10:00:00'): SeriesFollow
    {
        $follow = SeriesFollow::create(['user_id' => $user->id, 'tracked_series_id' => $series->id, 'alerts_enabled' => $alerts]);
        $follow->forceFill(['created_at' => $since])->save();
        return $follow;
    }

    public function test_follow_fetches_calendar_and_repeated_requests_preserve_preferences(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
        $user = User::factory()->create();
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getTvEpisodeCalendar')->twice()->with(247718)->andReturn($this->details());
            $mock->shouldReceive('getTvSeason')->twice()->with(247718, 2, null, true)->andReturn($this->episodes());
        });
        $this->actingAs($user)->from('/title/tv/247718')->post('/series', ['tmdb_id' => 247718])->assertRedirect('/title/tv/247718');
        $follow = $user->seriesFollows()->firstOrFail();
        $this->patch(route('series.update', $follow), ['alerts_enabled' => 0])->assertRedirect('/series');
        $this->from('/title/tv/247718')->post('/series', ['tmdb_id' => 247718])->assertRedirect('/title/tv/247718');
        $this->assertSame(1, $user->seriesFollows()->count());
        $this->assertFalse($follow->fresh()->alerts_enabled);
        $this->assertDatabaseHas('series_episodes', ['season_number' => 2, 'episode_number' => 4, 'air_date' => '2026-10-09']);
        $this->get('/series')->assertOk()->assertSee('MobLand')->assertSee('S02E04')->assertSee('09/10/2026')->assertSee('Activer les alertes');
        $this->assertSame(0, EpisodeAlert::count());
    }

    public function test_guests_cannot_follow_or_read_private_calendar(): void
    {
        $this->get('/series')->assertRedirect('/login');
        $this->post('/series', ['tmdb_id' => 247718])->assertRedirect('/login');
    }

    public function test_ajax_follow_returns_the_saved_state_without_redirecting(): void
    {
        $user = User::factory()->create();
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('getTvEpisodeCalendar')->twice()->with(247718)->andReturn($this->details()));
        $this->mock(SeriesCalendarService::class, fn ($mock) => $mock->shouldReceive('sync')->twice()->andReturn(false));

        $this->actingAs($user)->postJson('/series', ['tmdb_id' => 247718])->assertOk()
            ->assertJsonPath('followed', true)->assertJsonPath('message', 'Série suivie. Le calendrier sera actualisé lors de la prochaine synchronisation.');
        $follow = $user->seriesFollows()->firstOrFail();
        $follow->update(['alerts_enabled' => false]);
        $this->postJson('/series', ['tmdb_id' => 247718])->assertOk()->assertJsonPath('follow_id', $follow->id);
        $this->assertSame(1, $user->seriesFollows()->count());
        $this->assertFalse($follow->fresh()->alerts_enabled);
    }

    public function test_ajax_follow_failure_is_reported_without_creating_a_follow(): void
    {
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('getTvEpisodeCalendar')->once()->andReturnNull());
        $this->actingAs(User::factory()->create())->postJson('/series', ['tmdb_id' => 247718])->assertServiceUnavailable()
            ->assertJsonPath('message', 'Impossible de récupérer cette série. Réessaie dans quelques instants.');
        $this->assertSame(0, SeriesFollow::count());
    }

    public function test_ajax_unfollow_and_refollow_keep_the_user_on_the_content_and_preserve_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $series = $this->series();
        $follow = $this->follow($series, $user);
        $otherFollow = $this->follow($series, $other);
        $this->actingAs($user);
        $render = fn () => view('partials.content-actions', ['details'=>$this->details(), 'type'=>'tv', 'isTv'=>true, 'title'=>'MobLand'])->render();
        $this->assertStringContainsString('aria-label="Ne plus suivre cette série"', $render());
        $this->assertStringContainsString(route('series.destroy', $follow), $render());
        $this->deleteJson(route('series.destroy', $follow))->assertOk()->assertJsonPath('followed', false)->assertHeaderMissing('Location');
        $this->assertDatabaseMissing('series_follows', ['id'=>$follow->id]);
        $this->assertDatabaseHas('series_follows', ['id'=>$otherFollow->id]);
        $this->assertStringContainsString('aria-label="Suivre la série"', $render());
        $this->deleteJson(route('series.destroy', $otherFollow))->assertForbidden();
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('getTvEpisodeCalendar')->once()->with(247718)->andReturn($this->details()));
        $this->mock(SeriesCalendarService::class, fn ($mock) => $mock->shouldReceive('sync')->once()->andReturn(true));
        $this->postJson('/series', ['tmdb_id'=>247718])->assertOk()->assertJsonPath('followed', true)->assertHeaderMissing('Location');
        $newFollow = $user->seriesFollows()->firstOrFail();
        $this->assertNotSame($follow->id, $newFollow->id);
        $this->deleteJson(route('series.destroy', $newFollow))->assertOk()->assertJsonPath('followed', false)->assertHeaderMissing('Location');
    }

    public function test_invalid_series_or_api_failure_does_not_create_a_follow(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/series', ['tmdb_id' => -1])->assertUnprocessable()->assertJsonValidationErrors('tmdb_id');
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldReceive('getTvEpisodeCalendar')->once()->andReturnNull());
        $this->from('/')->post('/series', ['tmdb_id' => 247718])->assertRedirect('/')->assertSessionHasErrors('series');
        $this->assertSame(0, SeriesFollow::count());
    }

    public function test_follows_and_alerts_are_private_and_reading_is_idempotent(): void
    {
        $series = $this->series();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $follow = $this->follow($series, $owner);
        $episode = $series->episodes()->create(['season_number' => 2, 'episode_number' => 4, 'name' => 'Blank Curtain', 'air_date' => '2026-10-01']);
        $alert = EpisodeAlert::create(['user_id' => $owner->id, 'series_episode_id' => $episode->id]);
        $this->actingAs($other)->patch(route('series.update', $follow), ['alerts_enabled' => false])->assertForbidden();
        $this->delete(route('series.destroy', $follow))->assertForbidden();
        $this->patch(route('series.alerts.read', $alert))->assertForbidden();
        $this->get('/series')->assertOk()->assertDontSee('MobLand')->assertDontSee('Blank Curtain');
        $this->actingAs($owner)->patch(route('series.alerts.read', $alert))->assertRedirect('/series');
        $readAt = $alert->fresh()->read_at;
        $this->travel(1)->minutes();
        $this->patch(route('series.alerts.read', $alert))->assertRedirect('/series');
        $this->assertTrue($readAt->equalTo($alert->fresh()->read_at));
        $this->delete(route('series.destroy', $follow))->assertRedirect('/series');
        $this->assertSame(0, SeriesFollow::count());
        $this->assertSame(1, EpisodeAlert::count());
    }

    public function test_alerts_use_the_paris_day_and_are_not_duplicated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-08 22:30:00', 'UTC')); // October 9 in Paris.
        $series = $this->series();
        $user = User::factory()->create(['notify_opt_in' => true]);
        $this->follow($series, $user);
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getTvEpisodeCalendar')->twice()->andReturn($this->details());
            $mock->shouldReceive('getTvSeason')->twice()->andReturn($this->episodes());
        });
        $this->artisan('series:sync')->assertSuccessful();
        $this->artisan('series:sync')->assertSuccessful();
        $this->assertSame(1, EpisodeAlert::count());
        $this->actingAs($user)->get('/series')->assertOk()->assertSee('Diffusion annoncée le 09/10/2026.')->assertSee('1 alertes non lues');
    }

    public function test_alerts_respect_global_and_per_series_preferences_and_follow_start(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00', 'UTC'));
        $series = $this->series();
        $on = User::factory()->create(['notify_opt_in' => true]);
        $off = User::factory()->create(['notify_opt_in' => false]);
        $muted = User::factory()->create(['notify_opt_in' => true]);
        $this->follow($series, $on);
        $this->follow($series, $off);
        $this->follow($series, $muted, false);
        foreach (['2026-09-01', '2026-10-04', '2026-10-08', '2026-10-10', null] as $i => $date) {
            $series->episodes()->create(['season_number' => 2, 'episode_number' => $i + 1, 'name' => 'Episode', 'air_date' => $date]);
        }
        $this->assertSame(1, app(SeriesCalendarService::class)->createAlerts($series));
        $this->assertSame($on->id, EpisodeAlert::firstOrFail()->user_id);
        $this->assertSame(3, EpisodeAlert::firstOrFail()->episode->episode_number);
    }

    public function test_rescheduling_and_withdrawn_or_invalid_dates_do_not_leave_stale_dates(): void
    {
        $series = $this->series();
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getTvSeason')->times(4)->andReturn(
                $this->episodes(), $this->episodes('2026-10-16'), $this->episodes('2026-02-30'), ['episodes' => []]
            );
        });
        $service = app(SeriesCalendarService::class);
        $this->assertTrue($service->sync($series, $this->details()));
        $this->assertTrue($service->sync($series, $this->details()));
        $this->assertSame('2026-10-16', $series->episodes()->firstOrFail()->air_date->toDateString());
        $this->assertTrue($service->sync($series, $this->details()));
        $this->assertNull($series->episodes()->firstOrFail()->air_date);
        $this->assertTrue($service->sync($series, $this->details()));
        $this->assertSame(1, $series->episodes()->count());
        $this->assertNull($series->episodes()->firstOrFail()->air_date);
    }

    public function test_partial_api_failure_preserves_calendar_and_creates_no_alerts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00', 'UTC'));
        $series = $this->series();
        $this->follow($series, User::factory()->create(['notify_opt_in' => true]));
        $series->episodes()->create(['season_number' => 2, 'episode_number' => 4, 'name' => 'Blank Curtain', 'air_date' => '2026-10-09']);
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getTvEpisodeCalendar')->once()->andReturn(['name' => 'MobLand', 'seasons' => [['season_number' => 1], ['season_number' => 2]]]);
            $mock->shouldReceive('getTvSeason')->with(247718, 1, null, true)->once()->andReturn(['episodes' => []]);
            $mock->shouldReceive('getTvSeason')->with(247718, 2, null, true)->once()->andReturnNull();
        });
        $this->artisan('series:sync')->assertFailed();
        $this->assertDatabaseHas('series_episodes', ['air_date' => '2026-10-09']);
        $this->assertSame(0, EpisodeAlert::count());
        $this->assertNull($series->fresh()->synced_at);
    }

    public function test_no_longer_followed_series_are_not_fetched_and_old_episodes_do_not_alert(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'UTC'));
        $series = $this->series();
        $this->mock(TmdbService::class, fn ($mock) => $mock->shouldNotReceive('getTvEpisodeCalendar'));
        $this->artisan('series:sync')->assertSuccessful();
        $this->follow($series, User::factory()->create(['notify_opt_in' => true]));
        $series->episodes()->create(['season_number' => 2, 'episode_number' => 4, 'name' => 'Episode', 'air_date' => '2026-10-09']);
        $this->assertSame(0, app(SeriesCalendarService::class)->createAlerts($series));
    }

    public function test_postponed_alerts_are_hidden_until_the_new_date_and_unknown_dates_stay_in_following(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00', 'UTC'));
        $series = $this->series();
        $user = User::factory()->create();
        $this->follow($series, $user);
        $episode = $series->episodes()->create(['season_number' => 2, 'episode_number' => 4, 'name' => 'Episode', 'air_date' => '2026-10-16']);
        EpisodeAlert::create(['user_id' => $user->id, 'series_episode_id' => $episode->id]);
        $this->assertSame(0, $user->episodeAlerts()->announced()->count());
        $episode->update(['air_date' => null]);
        $this->actingAs($user)->get('/series')->assertOk()->assertSee('MobLand')->assertSee('Aucune date à venir')->assertSee('Aucune alerte de diffusion');
    }

    public function test_scheduler_registers_hourly_sync_with_overlap_protection(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $event = $events->first(fn ($event) => str_contains($event->command ?? '', 'series:sync'));
        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_popup_offers_series_following_only_for_series_and_reflects_existing_follow(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = ['details' => ['id' => 247718, 'name' => 'MobLand'], 'type' => 'tv', 'watchNow' => null];
        $this->view('details-popup', $data)->assertSee('Suivre la série');
        $this->follow($this->series(), $user);
        $this->view('details-popup', $data)->assertSee('Série suivie')->assertSee('vod-action-pill is-active')->assertDontSee('Suivre la série');
        $this->view('details-popup', ['details' => ['id' => 603, 'title' => 'Matrix'], 'type' => 'movie', 'watchNow' => null])->assertDontSee('Suivre la série');
    }

    public function test_global_notifications_can_be_disabled_from_account_without_losing_following(): void
    {
        $user = User::factory()->create(['notify_opt_in' => true]);
        $series = $this->series();
        $this->follow($series, $user);
        $this->actingAs($user)->put('/account', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => $user->email, 'notify_opt_in' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect('/account');
        $this->assertFalse($user->fresh()->notify_opt_in);
        $this->get('/series')->assertOk()->assertSee('Tes notifications sont désactivées')->assertSee('MobLand');
        $this->assertSame(1, $user->seriesFollows()->count());
    }

    public function test_calendar_cache_refreshes_without_waiting_for_the_regular_detail_cache(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'Europe/Paris'));
        config(['services.tmdb.key' => 'test-key', 'cache.default' => 'array']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake([
            'api.themoviedb.org/3/tv/247718/season/2*' => \Illuminate\Support\Facades\Http::response($this->episodes()),
            'api.themoviedb.org/3/tv/247718*' => \Illuminate\Support\Facades\Http::response($this->details()),
        ]);
        $tmdb = new TmdbService();
        $this->assertNotNull($tmdb->getTvSeason(247718, 2));
        $this->assertNotNull($tmdb->getTvSeason(247718, 2, null, true));
        $this->assertNotNull($tmdb->getTvEpisodeCalendar(247718));
        $tmdb->getTvSeason(247718, 2, null, true);
        $tmdb->getTvEpisodeCalendar(247718);
        \Illuminate\Support\Facades\Http::assertSentCount(3);
        $this->travel(31)->minutes();
        $tmdb->getTvSeason(247718, 2);
        $tmdb->getTvSeason(247718, 2, null, true);
        $tmdb->getTvEpisodeCalendar(247718);
        \Illuminate\Support\Facades\Http::assertSentCount(5);
    }
}
