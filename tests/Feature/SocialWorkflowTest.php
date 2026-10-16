<?php

namespace Tests\Feature;

use App\Models\{User, UserConnection, Recommendation, SocialEvent};
use App\Services\RecommendationContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SocialWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private function pair(User $a, User $b, string $status = 'accepted'): UserConnection
    {
        $ids = [$a->id, $b->id];
        sort($ids);
        return UserConnection::create(['user_low_id' => $ids[0], 'user_high_id' => $ids[1], 'requested_by' => $a->id, 'status' => $status]);
    }
    private function content(): array
    {
        return ['title' => 'Dune', 'description' => 'Une découverte', 'image' => null, 'genres' => ['Science-fiction'], 'year' => '2021', 'providers' => [['slug' => 'netflix', 'name' => 'Netflix', 'access' => 'flatrate']], 'provider_slugs' => ['netflix'], 'fetched_at' => now()->toIso8601String()];
    }
    private function rec(User $a, User $b, string $type = 'movie'): Recommendation
    {
        return Recommendation::create(['sender_id' => $a->id, 'recipient_id' => $b->id, 'type' => $type, 'tmdb_id' => 1, 'content' => $this->content(), 'message' => 'À voir !']);
    }
    public function test_directory_exposes_only_consented_names_and_never_emails(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['directory_visible' => true, 'nickname' => 'PseudoVisible', 'first_name' => 'PrenomSecret', 'last_name' => 'NomSecret']);
        $c = User::factory()->create(['nickname' => 'MembreInvisible']);
        $this->actingAs($a)->get('/account/contacts')->assertOk()->assertSee('PseudoVisible')->assertDontSee('PrenomSecret')->assertDontSee('NomSecret')->assertDontSee($b->email)->assertDontSee('MembreInvisible');
        $this->get('/account/contacts?q=PrenomSecret')->assertOk()->assertDontSee('PseudoVisible');
        $b->update(['share_real_name' => true]);
        $this->get('/account/contacts?q=PrenomSecret%20NomSecret')->assertOk()->assertSee('PrenomSecret NomSecret');
        $this->get('/account/contacts?q=PrenomSecret')->assertOk()->assertSee('PrenomSecret NomSecret')->assertDontSee($b->email);
        $this->post('/account/contacts/invite', ['user_id' => $c->id])->assertRedirect();
        $this->assertSame(0, UserConnection::count());
    }
    public function test_email_requests_are_generic_idempotent_and_require_recipient_acceptance(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->actingAs($a)->post('/account/contacts/invite', ['email' => mb_strtoupper($b->email)])->assertRedirect();
        $status = session('status');
        $connection = UserConnection::firstOrFail();
        $this->post('/account/contacts/invite', ['email' => $b->email])->assertRedirect();
        $this->assertSame(1, UserConnection::count());
        $this->assertSame(1, SocialEvent::count());
        $this->post('/account/contacts/invite', ['email' => 'unknown@example.test'])->assertSessionHas('status', $status);
        $this->patch('/account/contacts/' . $connection->id, ['action' => 'accept'])->assertForbidden();
        $this->actingAs($c)->patch('/account/contacts/' . $connection->id, ['action' => 'accept'])->assertNotFound();
        $this->actingAs($b)->patch('/account/contacts/' . $connection->id, ['action' => 'accept'])->assertRedirect();
        $this->assertSame('accepted', $connection->fresh()->status);
        $this->assertSame(1, $a->socialEvents()->where('kind', 'accepted')->count());
    }
    public function test_qr_link_requires_confirmation_and_is_revocable(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->post('/account/contact-link')->assertRedirect();
        $token = $a->fresh()->contact_token;
        $this->get('/account/contact-qr')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
        $this->actingAs($b)->get('/join/' . $token)->assertOk();
        $this->assertSame(0, UserConnection::count());
        $this->actingAs($a)->post('/account/contact-link')->assertRedirect();
        $this->actingAs($b)->get('/join/' . $token)->assertNotFound();
        $this->post('/account/contacts/invite', ['token' => $a->fresh()->contact_token])->assertRedirect();
        $this->assertSame(1, UserConnection::count());
    }
    public function test_refusal_blocking_and_removal_prevent_recommendations(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $pair = $this->pair($a, $b, 'pending');
        $this->actingAs($a)->post('/account/recommendations', ['recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 1])->assertForbidden();
        $this->actingAs($b)->patch('/account/contacts/' . $pair->id, ['action' => 'decline'])->assertRedirect();
        $this->assertSame('declined', $pair->fresh()->status);
        $pair->update(['status' => 'accepted']);
        $this->patch('/account/contacts/' . $pair->id, ['action' => 'block'])->assertRedirect();
        $this->actingAs($a)->patch('/account/contacts/' . $pair->id, ['action' => 'unblock'])->assertForbidden();
        $this->patch('/account/contacts/' . $pair->id, ['action' => 'block'])->assertForbidden();
        $this->post('/account/contacts/invite', ['email' => $b->email])->assertRedirect();
        $this->assertSame('blocked', $pair->fresh()->status);
        $this->post('/account/recommendations', ['recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 1])->assertForbidden();
        $this->actingAs($b)->patch('/account/contacts/' . $pair->id, ['action' => 'unblock'])->assertRedirect();
        $this->assertSame(0, UserConnection::count());
    }
    public function test_sending_requires_relation_and_uses_server_metadata_with_duplicate_protection(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->pair($a, $b);
        $this->mock(RecommendationContent::class)->shouldReceive('get')->twice()->with('movie', 42)->andReturn($this->content());
        $data = ['recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 42, 'message' => 'Bonjour', 'content' => ['title' => 'Fake title']];
        $this->actingAs($a)->post('/account/recommendations', $data)->assertRedirect('/account/contacts');
        $this->post('/account/recommendations', $data)->assertRedirect()->assertSessionHas('status', fn ($message) => str_contains($message, 'aucun doublon'));
        $this->assertSame(1, Recommendation::count());
        $this->assertSame('Dune', Recommendation::first()->content['title']);
        $this->assertSame(1, $b->socialEvents()->count());
        $this->actingAs($b)->get('/account/recommendations')->assertOk()->assertSee('Dune')->assertSee($a->socialName())->assertDontSee($a->email);
    }
    public function test_seven_distinct_recommendations_sent_in_a_burst_all_reach_the_recipient(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->pair($sender, $recipient);
        $this->mock(RecommendationContent::class)->shouldReceive('get')
            ->andReturnUsing(fn ($type, $id) => array_replace($this->content(), ['title' => 'Contenu '.$id]));
        $delivery = $this->mock(\App\Services\SocialDeliveryService::class);
        $delivery->shouldReceive('deliver')->times(7)->andReturn(['sent' => 0, 'failed' => 0]);

        $this->actingAs($sender);
        // Ordinary device checks must not consume the recommendation sending allowance.
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/notifications/push/status', ['endpoint' => 'https://push.example.test/device'])->assertOk();
        }
        for ($id = 1; $id <= 7; $id++) {
            $this->get('/account/recommend?type=movie&id='.$id)->assertOk();
            $this->post('/account/recommendations', ['recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => $id])
                ->assertRedirect('/account/contacts')->assertSessionHas('status', 'Recommandation envoyée.');
        }
        $this->assertSame(7, Recommendation::where('recipient_id', $recipient->id)->count());
        $this->assertSame(7, $recipient->socialEvents()->where('kind', 'recommendation')->count());
        $response = $this->actingAs($recipient)->get('/account/recommendations')->assertOk();
        $this->assertSame(7, $response->viewData('recommendations')->total());
        for ($id = 1; $id <= 7; $id++) $response->assertSee('Contenu '.$id);
    }

    public function test_recommendation_limit_still_blocks_the_eleventh_send_and_is_scoped_to_the_sender(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $recipient = User::factory()->create();
        $this->pair($first, $recipient);
        $this->pair($second, $recipient);
        $this->mock(RecommendationContent::class)->shouldReceive('get')->times(11)->andReturn($this->content());
        $this->mock(\App\Services\SocialDeliveryService::class)->shouldReceive('deliver')->times(11)->andReturn(['sent' => 0, 'failed' => 0]);
        $this->actingAs($first);
        for ($id = 1; $id <= 10; $id++) {
            $this->post('/account/recommendations', ['recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => $id])->assertRedirect();
        }
        $this->post('/account/recommendations', ['recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => 11])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertSame(10, Recommendation::count());
        $this->actingAs($second)->post('/account/recommendations', ['recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => 1])->assertRedirect();
        $this->assertSame(11, Recommendation::count());
    }

    public function test_duplicate_protection_distinguishes_movies_and_series_with_the_same_identifier(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $this->pair($sender, $recipient);
        $this->mock(RecommendationContent::class)->shouldReceive('get')->twice()->andReturn($this->content());
        $this->mock(\App\Services\SocialDeliveryService::class)->shouldReceive('deliver')->twice()->andReturn(['sent' => 0, 'failed' => 0]);
        $this->actingAs($sender);
        foreach (['movie', 'tv'] as $type) {
            $this->post('/account/recommendations', ['recipient_id' => $recipient->id, 'type' => $type, 'tmdb_id' => 42])
                ->assertRedirect()->assertSessionHas('status', 'Recommandation envoyée.');
        }
        $this->assertSame(2, Recommendation::count());
    }

    public function test_recipient_only_can_view_archive_read_and_delete_recommendation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $rec = $this->rec($a, $b);
        $event = SocialEvent::create(['user_id' => $b->id, 'actor_id' => $a->id, 'kind' => 'recommendation', 'recommendation_id' => $rec->id]);
        $this->actingAs($c)->get('/account/recommendations/' . $rec->id)->assertNotFound();
        $this->patch('/account/recommendations/' . $rec->id, ['action' => 'delete'])->assertNotFound();
        $this->actingAs($b)->get('/account/recommendations/' . $rec->id)->assertOk()->assertSee('À voir !');
        $this->assertNotNull($rec->fresh()->read_at);
        $this->assertNotNull($event->fresh()->read_at);
        $this->patch('/account/recommendations/' . $rec->id, ['action' => 'archive'])->assertRedirect();
        $this->get('/account/recommendations')->assertDontSee('Une découverte');
        $this->get('/account/recommendations?state=archived')->assertSee('Une découverte');
        $this->patch('/account/recommendations/' . $rec->id, ['action' => 'delete'])->assertRedirect();
        $this->assertSame(0, SocialEvent::count());
    }
    public function test_inbox_filters_and_sorts_are_scoped_to_current_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        $this->rec($a, $b);
        $other = $this->rec($a, $c);
        $other->update(['content' => array_replace($other->content, ['title' => 'Secret privé'])]);
        $this->actingAs($b)->get('/account/recommendations?platform=netflix&type=movie&sort=title')->assertOk()->assertSee('Une découverte')->assertDontSee('Secret privé');
        $this->get('/account/recommendations?platform=disneyplus')->assertOk()->assertDontSee('Une découverte');
        $this->get('/account/recommendations?type=person')->assertOk()->assertDontSee('Une découverte');
    }
    public function test_private_preferences_are_saved_explicitly(): void
    {
        $a = User::factory()->create();
        $this->actingAs($a)->put('/account', ['first_name' => 'Jean', 'last_name' => 'Secret', 'email' => $a->email, 'directory_visible' => 1, 'share_real_name' => 1])->assertRedirect();
        $this->assertTrue($a->fresh()->share_real_name);
        $this->assertTrue($a->fresh()->directory_visible);
    }
    public function test_private_social_pages_require_login(): void
    {
        foreach (['/account/contacts', '/account/recommendations', '/account/contact-qr'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
