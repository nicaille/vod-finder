<?php

namespace Tests\Feature;

use App\Services\RecommendationContent;
use App\Models\{User, UserConnection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class RecommendationContentTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key', 'services.streaming_availability.key' => '']);
        Http::preventStrayRequests();
    }
    public function test_movie_metadata_and_providers_come_from_tmdb_and_use_france(): void
    {
        Http::fake(['api.themoviedb.org/3/movie/42/watch/providers*' => Http::response(['results' => ['FR' => ['flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]]]), 'api.themoviedb.org/3/movie/42*' => Http::response(['id' => 42, 'title' => 'Dune', 'overview' => 'Synopsis', 'poster_path' => '/dune.jpg', 'genres' => [['name' => 'Science-fiction']], 'release_date' => '2021-09-15', 'credits' => ['cast' => [['id' => 2524, 'name' => 'Tom Hardy']]]])]);
        $content = app(RecommendationContent::class)->get('movie', 42);
        $this->assertSame(['netflix'], $content['provider_slugs']);
        $this->assertSame('https://image.tmdb.org/t/p/w342/dune.jpg', $content['image']);
        $this->get('/content/movie/42')->assertOk()->assertSee('Synopsis')->assertSee(route('search.index', ['q' => 'Tom Hardy', 'person_id' => 2524, 'type' => 'all', 'country' => 'FR']))->assertSee('Netflix');
    }
    public function test_person_profile_can_be_recommended_and_linked_to_their_films(): void
    {
        Http::fake(['api.themoviedb.org/3/person/2524/combined_credits*' => Http::response(['cast' => [['id' => 42, 'media_type' => 'movie', 'title' => 'Film connu']]]), 'api.themoviedb.org/3/person/2524*' => Http::response(['id' => 2524, 'name' => 'Tom Hardy', 'biography' => 'Biographie', 'profile_path' => '/tom.jpg', 'birthday' => '1977-09-15'])]);
        $this->get('/content/person/2524')->assertOk()->assertSee('Biographie')->assertSee('/content/movie/42');
        $a = User::factory()->create();
        $b = User::factory()->create();
        UserConnection::create(['user_low_id' => $a->id, 'user_high_id' => $b->id, 'requested_by' => $a->id, 'status' => 'accepted']);
        $this->actingAs($a)->get('/account/recommend?type=person&id=2524')->assertOk()->assertSee('Recommander Tom Hardy');
        $this->post('/account/recommendations', ['recipient_id' => $b->id, 'type' => 'person', 'tmdb_id' => 2524])->assertRedirect();
        $this->actingAs($b)->get('/account/recommendations')->assertOk()->assertSee('Tom Hardy')->assertSee('Personne');
    }
    public function test_invalid_upstream_content_is_not_saved(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 999, 'title' => 'Wrong content'])]);
        $a = User::factory()->create();
        $b = User::factory()->create();
        UserConnection::create(['user_low_id' => $a->id, 'user_high_id' => $b->id, 'requested_by' => $a->id, 'status' => 'accepted']);
        $this->actingAs($a)->post('/account/recommendations', ['recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 42])->assertStatus(503);
        $this->assertDatabaseCount('recommendations', 0);
        $this->assertDatabaseCount('social_events', 0);
    }
}
