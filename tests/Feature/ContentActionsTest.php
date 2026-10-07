<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentActionsTest extends TestCase
{
    use RefreshDatabase;

    private function movie(): void
    {
        config(['services.streaming_availability.key' => '']);
        $this->mock(TmdbService::class, function ($mock) {
            $mock->shouldReceive('getDetails')->andReturn(['id' => 42, 'title' => 'Exemple', 'credits' => [
                'cast' => [['id' => 7, 'name' => 'Acteur Test']],
                'crew' => [['id' => 8, 'name' => 'Réalisatrice Test', 'job' => 'Director'], ['id' => 9, 'name' => 'Producteur Test', 'job' => 'Producer']],
            ]]);
            $mock->shouldReceive('getAvailability', 'getRecommendations')->andReturn([]);
        });
    }

    public function test_credits_link_to_search_by_id_and_authenticated_actions_are_present(): void
    {
        $this->movie();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee(route('search.index', ['q' => 'Acteur Test', 'person_id' => 7, 'type' => 'all', 'country' => 'FR']))
            ->assertSee('person_id=8')->assertSee('person_id=9')
            ->assertSee('data-action="favorite"', false)->assertSee('data-action="list"', false)
            ->assertSee('data-action="playlist"', false)
            ->assertSee('data-action="recommend"', false)->assertDontSee('Recommander à un contact');
    }

    public function test_detail_playlist_state_is_scoped_by_user_and_content_type_and_survives_reopening(): void
    {
        $this->movie();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->watchlist()->create(['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Other movie']);
        $user->watchlist()->create(['tmdb_id' => 42, 'type' => 'tv', 'title' => 'Same ID series']);
        $this->actingAs($user)->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertViewHas('inWatchlist', false);
        $payload = ['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Exemple', 'year' => '2026', 'poster' => null];
        $this->postJson('/watchlist/toggle', $payload)->assertOk()->assertJsonPath('in_watchlist', true);
        $this->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertViewHas('inWatchlist', true)->assertSee('Dans la playlist');
        $this->postJson('/watchlist/toggle', $payload)->assertOk()->assertJsonPath('in_watchlist', false);
        $this->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertViewHas('inWatchlist', false);
        $this->assertDatabaseHas('watchlist_items', ['user_id' => $other->id, 'type' => 'movie', 'tmdb_id' => 42]);
        $this->assertDatabaseHas('watchlist_items', ['user_id' => $user->id, 'type' => 'tv', 'tmdb_id' => 42]);
    }

    public function test_cast_members_after_the_first_five_remain_accessible_with_their_role(): void
    {
        config(['services.streaming_availability.key' => '']);
        $this->mock(TmdbService::class, function ($mock) {
            $cast = array_map(fn ($i) => ['id' => $i, 'name' => 'Acteur '.$i], range(1, 5));
            $cast[] = ['id' => 192, 'name' => 'Morgan Freeman', 'character' => 'Patrick Meighan'];
            $mock->shouldReceive('getDetails')->andReturn(['id' => 214756, 'title' => 'Ted 2', 'credits' => ['cast' => $cast, 'crew' => []]]);
            $mock->shouldReceive('getAvailability', 'getRecommendations')->andReturn([]);
        });
        $this->get('/title/movie/214756', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Voir les autres interprètes (1)')->assertSee('Morgan Freeman')->assertSee('Patrick Meighan')->assertSee('person_id=192');
    }

    public function test_saved_states_use_only_the_current_users_favorites_and_lists(): void
    {
        $this->movie();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $list = $other->lists()->create(['name' => 'Other list']);
        $list->items()->create(['tmdb_id' => 42, 'type' => 'movie', 'added_by' => $other->id]);
        $this->actingAs($user)->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewHas('inUserList', false)->assertViewHas('isFavorite', false);
        $user->favorites()->create(['tmdb_id' => 42, 'type' => 'movie']);
        $own = $user->lists()->create(['name' => 'My list']);
        $own->items()->create(['tmdb_id' => 42, 'type' => 'movie', 'added_by' => $user->id]);
        $this->get('/title/movie/42', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertViewHas('inUserList', true)->assertViewHas('isFavorite', true)
            ->assertSee('aria-pressed="true"', false)->assertSee('Déjà dans une liste');
    }
}
