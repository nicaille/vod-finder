<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ListItem;
use App\Models\MediaList;
use App\Services\TmdbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => '']);
        Http::preventStrayRequests();
    }

    private function title(string $type = 'movie'): array
    {
        return ['id' => 42, 'title' => 'Film test', 'name' => 'Série test', 'media_type' => $type,
            'release_date' => '2025-01-01', 'first_air_date' => '2025-01-01', 'overview' => 'Synopsis test'];
    }

    private function providers(): array
    {
        return ['results' => ['FR' => [
            'flatrate' => [['provider_name' => 'Netflix', 'provider_id' => 8]],
            'rent' => [['provider_name' => 'Amazon Video', 'provider_id' => 10]],
            'buy' => [['provider_name' => 'Apple iTunes', 'provider_id' => 2]],
        ]]];
    }

    public static function searchCases(): array
    {
        return [
            ['movie', 'all', [], 1, ['flatrate', 'rent', 'buy']],
            ['tv', 'all', [], 1, ['flatrate', 'rent', 'buy']],
            ['movie', 'flatrate', ['netflix'], 1, ['flatrate']],
            ['movie', 'rent', ['prime'], 1, ['rent']],
            ['tv', 'buy', ['appletv'], 1, ['buy']],
            ['movie', 'rent', ['netflix'], 0, []],
        ];
    }

    /** @dataProvider searchCases */
    public function test_movie_series_and_access_filters(string $type, string $access, array $providers, int $count, array $expectedAccess): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/'.$type.'*' => Http::response(['results' => [$this->title($type), $this->title($type)]]),
            'api.themoviedb.org/3/'.$type.'/42/watch/providers*' => Http::response($this->providers()),
        ]);
        $response = $this->getJson('/search?'.http_build_query(['q' => 'test', 'type' => $type, 'access' => $access, 'providers' => $providers]));
        $response->assertOk()->assertJsonCount($count, 'results');
        if ($count) {
            $this->assertSame($expectedAccess, array_column($response->json('results.0.providers'), 'access'));
        }
        // One search and one provider lookup, even with duplicate TMDb results.
        Http::assertSentCount(2);
    }

    public function test_person_search_deduplicates_titles(): void
    {
        Http::fake([
            'api.themoviedb.org/3/person/*/combined_credits*' => Http::response(['cast' => [$this->title(), $this->title('tv')]]),
            'api.themoviedb.org/3/movie/42/watch/providers*' => Http::response($this->providers()),
        ]);
        $this->getJson('/search?type=movie&person_ids[]=1&person_ids[]=2&person_ids[]=1')
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 42);
        Http::assertSentCount(3);
    }

    public function test_autocomplete_keeps_titles_and_merges_matching_people(): void
    {
        $person = ['media_type' => 'person', 'name' => 'Jean Test', 'profile_path' => '/jean.jpg',
            'known_for_department' => 'Acting', 'gender' => 2, 'known_for' => [['id' => 42]]];
        Http::fake(['api.themoviedb.org/3/search/multi*' => Http::response(['results' => [
            $this->title(), $this->title('tv'), $person + ['id' => 1, 'popularity' => 20], $person + ['id' => 2, 'popularity' => 10],
        ]])]);
        $response = $this->getJson('/autocomplete?q=Jean')->assertOk()->assertJsonCount(3, 'results');
        $people = array_values(array_filter($response->json('results'), fn ($item) => $item['type'] === 'person'));
        $this->assertCount(1, $people);
        $this->assertSame('1|2', $people[0]['id']);
        $this->assertSame(2, $people[0]['count_ids']);
        $this->getJson('/autocomplete?q=J')->assertOk()->assertJsonCount(0, 'results');
        Http::assertSentCount(1);
    }

    public static function detailCases(): array
    {
        return [['movie'], ['tv']];
    }

    /** @dataProvider detailCases */
    public function test_details_popup_renders_for_movies_and_series(string $type): void
    {
        Http::fake([
            'api.themoviedb.org/3/'.$type.'/42/watch/providers*' => Http::response($this->providers()),
            'api.themoviedb.org/3/'.$type.'/42/recommendations*' => Http::response(['results' => []]),
            'api.themoviedb.org/3/'.$type.'/42*' => Http::response($this->title($type)),
        ]);
        $this->get('/title/'.$type.'/42', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewIs('details-popup')->assertSee('Synopsis test')->assertSee('Netflix');
        $this->get('/title/'.$type.'/42')->assertRedirect('/');
    }

    public function test_provider_mapping_does_not_assume_an_access_platform(): void
    {
        $mapped = app(TmdbService::class)->mapProviders([
            ['provider_name' => 'Apple TV Plus'], ['provider_name' => 'Paramount Plus'], ['provider_name' => 'Max'],
        ]);
        $this->assertSame([null, null, null], array_column($mapped, 'via'));
        $this->assertSame(['https://tv.apple.com/', 'https://www.paramountplus.com/', 'https://www.max.com/'], array_column($mapped, 'url'));
    }

    public function test_watchlist_and_favorites_toggle_and_render(): void
    {
        Http::fake(['api.themoviedb.org/3/movie/42*' => Http::response($this->title())]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/watchlist/toggle', ['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Film test'])
            ->assertOk()->assertJsonPath('in_watchlist', true);
        $this->get('/watchlist')->assertOk()->assertSee('Film test');
        $this->postJson('/favorites/toggle', ['tmdb_id' => 42, 'type' => 'movie'])->assertOk()->assertJsonPath('favorited', true);
        $this->get('/lists')->assertOk()->assertSee('Film test');
        $this->postJson('/favorites/toggle', ['tmdb_id' => 42, 'type' => 'movie'])->assertJsonPath('favorited', false);
        $this->postJson('/watchlist/toggle', ['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Film test'])->assertJsonPath('in_watchlist', false);
        $this->assertDatabaseCount('watchlist_items', 0);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_lists_crud_items_and_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $created = $this->actingAs($owner)->postJson('/lists', ['name' => 'À voir'])->assertOk();
        $listId = $created->json('list.id');
        $item = $this->postJson('/lists/'.$listId.'/items', ['tmdb_id' => 42, 'type' => 'movie'])->assertOk();
        $itemId = $item->json('item.id');
        $this->postJson('/lists/'.$listId.'/items', ['tmdb_id' => 42, 'type' => 'movie'])->assertOk();
        $this->assertDatabaseCount('list_items', 1);
        $otherList = MediaList::create(['user_id' => $owner->id, 'name' => 'Autre liste']);
        $this->deleteJson('/lists/'.$otherList->id.'/items/'.$itemId)->assertNotFound();
        $this->actingAs($other)->deleteJson('/lists/'.$listId.'/items/'.$itemId)->assertForbidden();
        $this->putJson('/lists/'.$listId, ['name' => 'Interdit'])->assertForbidden();
        $this->actingAs($owner)->putJson('/lists/'.$listId, ['name' => 'Renommée'])->assertOk();
        $this->deleteJson('/lists/'.$listId.'/items/'.$itemId)->assertOk();
        $this->assertNull(ListItem::find($itemId));
        $this->deleteJson('/lists/'.$listId)->assertOk();
        $this->assertNull(MediaList::find($listId));
    }

    public function test_guests_cannot_mutate_personal_collections_or_account(): void
    {
        $this->get('/account')->assertRedirect('/login');
        foreach (['/watchlist/toggle', '/favorites/toggle', '/lists'] as $url) {
            $this->postJson($url, [])->assertUnauthorized();
        }
    }
}
