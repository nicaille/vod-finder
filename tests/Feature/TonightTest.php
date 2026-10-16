<?php

namespace Tests\Feature;

use App\Models\{Platform, User};
use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TonightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $platform = Platform::firstOrCreate(['slug' => 'netflix'], ['name' => 'Netflix', 'position' => 1]);
        $user->platformSubscriptions()->attach($platform, ['is_active' => true]);
        return $user;
    }

    private function candidate(int $id, array $genres = [35]): array
    {
        return ['id' => $id, 'genre_ids' => $genres];
    }

    private function details(int $id, array $extra = []): array
    {
        return array_replace(['id' => $id, 'title' => 'Idée '.$id, 'runtime' => 90, 'overview' => 'Une idée de soirée', 'release_date' => '2020-01-01'], $extra);
    }

    private function offer(array $extra = []): array
    {
        return array_replace(['slug' => 'netflix', 'name' => 'Netflix', 'access' => 'flatrate', 'via' => null], $extra);
    }

    public function test_initial_page_guests_and_inactive_subscriptions_do_not_call_apis(): void
    {
        $this->get('/tonight')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/tonight')->assertOk()->assertSee('data-tonight-state="initial"', false);
        $this->get('/tonight?suggest=1')->assertOk()->assertSee('data-tonight-state="no_platforms"', false);
        $platform = Platform::firstOrCreate(['slug' => 'netflix'], ['name' => 'Netflix', 'position' => 1]);
        $user->platformSubscriptions()->attach($platform, ['is_active' => false]);
        $this->get('/tonight?suggest=1')->assertOk()->assertSee('data-tonight-state="no_platforms"', false);
        Http::assertNothingSent();
    }

    public function test_preferences_are_validated_before_any_api_calls(): void
    {
        $this->actingAs($this->subscriber())->getJson('/tonight?suggest=1&minutes=9999&type=person&genre=invalid&mood=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors(['minutes', 'type', 'genre', 'mood']);
        Http::assertNothingSent();
    }

    public function test_movies_respect_duration_genre_mood_dates_and_seen_titles(): void
    {
        $user = $this->subscriber();
        $user->watchedTitles()->create(['type' => 'movie', 'tmdb_id' => 1, 'title' => 'Déjà vu', 'watched_at' => now()]);
        $mock = $this->mock(TmdbService::class);
        $mock->shouldReceive('discoverTonight')->once()->with(['netflix'], 'movie', 90, [35])->andReturn([
            $this->candidate(1), $this->candidate(2, [18]), $this->candidate(3), $this->candidate(4), $this->candidate(5), $this->candidate(6),
        ]);
        $mock->shouldReceive('getDetails')->with(3, 'movie')->once()->andReturn($this->details(3, ['runtime' => 91]));
        $mock->shouldReceive('getDetails')->with(4, 'movie')->once()->andReturn($this->details(4));
        $mock->shouldReceive('getDetails')->with(5, 'movie')->once()->andReturn($this->details(5, ['release_date' => now()->addYear()->toDateString()]));
        $mock->shouldReceive('getDetails')->with(6, 'movie')->once()->andReturn($this->details(6, ['runtime' => 0]));
        $mock->shouldReceive('getAvailabilitySnapshot')->with(4, 'movie', 'FR', ['netflix'])->once()->andReturn([$this->offer()]);
        $this->actingAs($user)->get('/tonight?suggest=1&type=movie&minutes=90&genre=comedy&mood=light&providers[]=disneyplus&country=US')
            ->assertOk()->assertSee('Idée 4')->assertDontSee('Idée 1')->assertDontSee('Idée 3')->assertDontSee('Idée 5')->assertDontSee('Idée 6')
            ->assertSee('90 min')->assertSee('data-tonight-state="ready"', false);
    }

    public function test_series_use_episode_duration_and_do_not_confuse_movie_and_tv_ids(): void
    {
        $user = $this->subscriber();
        $user->watchedTitles()->create(['type' => 'movie', 'tmdb_id' => 1, 'title' => 'Autre film', 'watched_at' => now()]);
        $mock = $this->mock(TmdbService::class);
        $mock->shouldReceive('discoverTonight')->with(['netflix'], 'tv', 60, [])->once()->andReturn([$this->candidate(1), $this->candidate(2), $this->candidate(3)]);
        $mock->shouldReceive('getDetails')->with(1, 'tv')->once()->andReturn($this->details(1, ['name' => 'Série', 'first_air_date' => '2020-01-01', 'episode_run_time' => [45, 55]]));
        $mock->shouldReceive('getDetails')->with(2, 'tv')->once()->andReturn($this->details(2, ['first_air_date' => '2020-01-01', 'episode_run_time' => [], 'last_episode_to_air' => ['runtime' => 42]]));
        $mock->shouldReceive('getDetails')->with(3, 'tv')->once()->andReturn($this->details(3, ['first_air_date' => '2020-01-01', 'episode_run_time' => [45, 65]]));
        $mock->shouldReceive('getAvailabilitySnapshot')->twice()->andReturn([$this->offer()]);
        $this->actingAs($user)->get('/tonight?suggest=1&type=tv&minutes=60')->assertOk()
            ->assertSee('55 min / épisode environ')->assertSee('42 min / épisode environ')->assertDontSee('Idée 3');
    }

    public function test_only_active_subscription_and_exact_channel_match_are_included(): void
    {
        $user = User::factory()->create();
        $prime = Platform::firstOrCreate(['slug' => 'prime'], ['name' => 'Prime Video', 'position' => 2]);
        $paramount = Platform::firstOrCreate(['slug' => 'paramountplus'], ['name' => 'Paramount+', 'position' => 3]);
        $user->platformSubscriptions()->attach($paramount, ['is_active' => true, 'subscribed_via_platform_id' => $prime->id]);
        $mock = $this->mock(TmdbService::class);
        $mock->shouldReceive('discoverTonight')->once()->andReturn([$this->candidate(1), $this->candidate(2), $this->candidate(3), $this->candidate(4)]);
        $mock->shouldReceive('getDetails')->andReturnUsing(fn ($id) => $this->details($id));
        $mock->shouldReceive('getAvailabilitySnapshot')->with(1, 'movie', 'FR', ['paramountplus'])->andReturn([$this->offer(['slug' => 'paramountplus', 'via' => null])]);
        $mock->shouldReceive('getAvailabilitySnapshot')->with(2, 'movie', 'FR', ['paramountplus'])->andReturn([$this->offer(['slug' => 'paramountplus', 'via' => 'prime', 'name' => 'Paramount+'])]);
        $mock->shouldReceive('getAvailabilitySnapshot')->with(3, 'movie', 'FR', ['paramountplus'])->andReturn([$this->offer(['slug' => 'paramountplus', 'via' => 'prime', 'access' => 'buy'])]);
        $mock->shouldReceive('getAvailabilitySnapshot')->with(4, 'movie', 'FR', ['paramountplus'])->andReturn([$this->offer()]);
        $this->actingAs($user)->get('/tonight?suggest=1&type=movie')->assertOk()->assertSee('Idée 2')->assertSee('via Prime Video')
            ->assertDontSee('Idée 1')->assertDontSee('Idée 3')->assertDontSee('Idée 4');
    }

    public function test_shared_candidates_are_filtered_against_each_users_seen_history(): void
    {
        $first = $this->subscriber(); $second = $this->subscriber();
        $first->watchedTitles()->create(['type' => 'movie', 'tmdb_id' => 1, 'title' => 'Vu', 'watched_at' => now()]);
        $mock = $this->mock(TmdbService::class);
        $mock->shouldReceive('discoverTonight')->twice()->andReturn([$this->candidate(1)]);
        $mock->shouldReceive('getDetails')->once()->andReturn($this->details(1));
        $mock->shouldReceive('getAvailabilitySnapshot')->once()->andReturn([$this->offer()]);
        $this->actingAs($first)->get('/tonight?suggest=1&type=movie')->assertOk()->assertSee('data-tonight-state="empty"', false);
        $this->actingAs($second)->get('/tonight?suggest=1&type=movie')->assertOk()->assertSee('Idée 1');
    }

    public function test_both_types_are_considered_and_provider_lookups_are_bounded(): void
    {
        $user = $this->subscriber();
        $mock = $this->mock(TmdbService::class);
        $pool = array_map(fn ($id) => $this->candidate($id), range(1, 20));
        $mock->shouldReceive('discoverTonight')->twice()->andReturn($pool);
        $mock->shouldReceive('getDetails')->times(6)->andReturnUsing(fn ($id, $type) => $this->details($id, ['first_air_date' => '2020-01-01', 'episode_run_time' => [30]]));
        $mock->shouldReceive('getAvailabilitySnapshot')->times(6)->andReturn([]);
        $this->actingAs($user)->get('/tonight?suggest=1')->assertOk()->assertSee('data-tonight-state="empty"', false);
    }

    public function test_failures_are_distinguished_from_no_matching_titles_and_retry_works(): void
    {
        $user = $this->subscriber();
        $mock = $this->mock(TmdbService::class);
        $mock->shouldReceive('discoverTonight')->with(['netflix'], 'movie', 120, [])->twice()->andReturn(null, [$this->candidate(1)]);
        $mock->shouldReceive('getDetails')->once()->andReturn($this->details(1));
        $mock->shouldReceive('getAvailabilitySnapshot')->once()->andReturn([$this->offer()]);
        $this->actingAs($user)->get('/tonight?suggest=1&type=movie')->assertOk()->assertSee('data-tonight-state="unavailable"', false);
        $this->get('/tonight?suggest=1&type=movie')->assertOk()->assertSee('data-tonight-state="ready"', false);
    }

    public function test_rate_limit_is_per_member_and_still_allows_six_attempts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/tonight')->assertOk();
        for ($i = 0; $i < 6; $i++) $this->get('/tonight?suggest=1')->assertOk();
        $this->get('/tonight?suggest=1')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->actingAs(User::factory()->create())->get('/tonight?suggest=1')->assertOk();
        Http::assertNothingSent();
    }
}
