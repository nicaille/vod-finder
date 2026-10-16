<?php

namespace Tests\Feature;

use App\Models\{Recommendation, SocialEvent, User, UserConnection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountActivityTest extends TestCase
{
    use RefreshDatabase;

    private function connection(User $sender, User $recipient, string $state = 'accepted'): UserConnection
    {
        $ids = [$sender->id, $recipient->id]; sort($ids);
        return UserConnection::create(['user_low_id' => $ids[0], 'user_high_id' => $ids[1], 'requested_by' => $sender->id, 'status' => $state]);
    }

    private function recommendation(User $sender, User $recipient, array $attributes = []): Recommendation
    {
        return Recommendation::create($attributes + ['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => 42,
            'content' => ['title' => 'Film recommandé', 'description' => 'Synopsis', 'image' => null, 'providers' => []]]);
    }

    public function test_six_recommendations_and_one_contact_are_shown_as_separate_categories(): void
    {
        $sender = User::factory()->create(); $recipient = User::factory()->create();
        $connection = $this->connection($sender, $recipient);
        for ($i = 0; $i < 6; $i++) {
            $rec = $this->recommendation($sender, $recipient, ['tmdb_id' => 42 + $i]);
            SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'recommendation', 'recommendation_id' => $rec->id]);
        }
        SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'accepted', 'user_connection_id' => $connection->id]);
        $this->actingAs($recipient)->get('/account')->assertOk()->assertSee('6 recommandation(s) non lue(s)')
            ->assertSee('1 acceptation(s) à consulter')->assertSee('7 éléments à consulter dans Mon compte');
        $page = $this->get('/account/recommendations');
        $page->assertOk()->assertSee('6 recommandation(s) pour ces filtres.');
        $this->assertSame(6, $page->viewData('recommendations')->total());
        $this->get('/account/contacts')->assertOk()->assertSee('6 éléments à consulter dans Mon compte')->assertDontSee('1 acceptation(s) à consulter');
    }

    public function test_seven_recommendations_are_all_listed_and_links_clear_unrelated_filters(): void
    {
        $sender = User::factory()->create(); $recipient = User::factory()->create();
        $this->connection($sender, $recipient);
        for ($i = 0; $i < 7; $i++) $this->recommendation($sender, $recipient, ['tmdb_id' => $i + 1]);
        $this->actingAs($recipient)->get('/account')->assertOk()->assertSee('7 recommandation(s) non lue(s)')
            ->assertSee(route('recommendations.index', ['state' => 'unread']));
        $page = $this->get('/account/recommendations?state=unread');
        $this->assertSame(7, $page->viewData('recommendations')->total());
        $this->assertCount(7, $page->viewData('recommendations')->items());
        $this->get('/account/recommendations?type=tv')->assertOk()->assertSee('0 recommandation(s) pour ces filtres.')->assertSee('7 recommandation(s) non lue(s)');
    }

    public function test_recommendation_counts_follow_rows_not_stale_events_or_archives(): void
    {
        $sender = User::factory()->create(); $recipient = User::factory()->create();
        $this->connection($sender, $recipient);
        $current = $this->recommendation($sender, $recipient); // Counts even if its old event is missing.
        foreach ([['read_at' => now()], ['archived_at' => now()]] as $state) {
            $rec = $this->recommendation($sender, $recipient, $state);
            SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'recommendation', 'recommendation_id' => $rec->id]);
        }
        $this->recommendation($recipient, $sender);
        $this->actingAs($recipient)->get('/account')->assertOk()->assertSee('1 recommandation(s) non lue(s)');
        $page = $this->get('/account/recommendations?state=unread');
        $this->assertSame(1, $page->viewData('recommendations')->total());
        $this->get('/account/recommendations/'.$current->id)->assertOk();
        $this->get('/account')->assertOk()->assertDontSee('data-account-badge', false)->assertSee('Aucune notification non lue ni demande en attente.');
    }

    public function test_obsolete_contact_events_do_not_leave_a_phantom_badge(): void
    {
        $sender = User::factory()->create(); $recipient = User::factory()->create();
        $connection = $this->connection($sender, $recipient, 'declined');
        foreach (['request', 'accepted'] as $kind) {
            SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => $kind, 'user_connection_id' => $connection->id]);
        }
        SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'accepted']);
        $this->actingAs($recipient)->get('/account')->assertOk()->assertDontSee('data-account-badge', false);
    }

    public function test_pending_contacts_count_until_resolved_even_if_event_was_read(): void
    {
        $sender = User::factory()->create(); $recipient = User::factory()->create();
        $connection = $this->connection($sender, $recipient, 'pending');
        SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'request', 'user_connection_id' => $connection->id, 'read_at' => now()]);
        $this->actingAs($recipient)->get('/account')->assertOk()->assertSee('1 demande(s) en attente');
        $this->get('/account/contacts')->assertOk()->assertSee('1 éléments à consulter dans Mon compte');
        $this->patch('/account/contacts/'.$connection->id, ['action' => 'decline'])->assertRedirect();
        $this->get('/account')->assertOk()->assertDontSee('data-account-badge', false);
    }

    public function test_availability_badge_has_an_explicit_destination_and_clears_after_reading(): void
    {
        $user = User::factory()->create();
        $alert = $user->availabilityAlerts()->create(['type' => 'movie', 'tmdb_id' => 42, 'title' => 'Film disponible', 'revision' => 1, 'providers' => []]);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('1 alerte(s) de disponibilité non lue(s)');
        $this->patch(route('availability.read', $alert))->assertRedirect();
        $this->get('/account')->assertOk()->assertDontSee('data-account-badge', false);
    }
}
