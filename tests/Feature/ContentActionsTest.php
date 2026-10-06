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
            ->assertSee(route('search.index', ['q' => 'Acteur Test', 'person_id' => 7, 'type' => 'movie', 'country' => 'FR']))
            ->assertSee('person_id=8')->assertSee('person_id=9')
            ->assertSee('data-action="favorite"', false)->assertSee('data-action="list"', false)
            ->assertSee('data-action="recommend"', false)->assertDontSee('Recommander à un contact');
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
