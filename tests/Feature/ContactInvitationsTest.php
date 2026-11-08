<?php

namespace Tests\Feature;

use App\Mail\ContactInvitationMail;
use App\Models\{ContactInvitation, SocialEvent, User, UserConnection};
use App\Services\{ContactInvitations, NotificationEmailSender, NotificationEmailVerification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class ContactInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function link(User $sender): array { return app(ContactInvitations::class)->create($sender); }
    private function token(string $url): string { return basename(parse_url($url, PHP_URL_PATH)); }

    public function test_link_creation_is_authenticated_private_expiring_and_stores_only_a_digest(): void
    {
        $this->postJson(route('contact-invitations.store'), ['channel' => 'link'])->assertUnauthorized();
        $sender = User::factory()->create();
        $response = $this->actingAs($sender)->postJson(route('contact-invitations.store'), ['channel' => 'link'])->assertCreated();
        $token = $this->token($response->json('url'));
        $row = ContactInvitation::sole();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $this->assertSame(hash('sha256', $token), $row->token_hash);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $row->expires_at->timestamp, 2);
        $this->assertStringNotContainsString($token, json_encode((array) DB::table('contact_invitations')->first()));
        $this->assertSame(0, UserConnection::count());
        $this->assertSame(0, SocialEvent::count());
    }

    public function test_get_does_not_accept_and_guest_returns_to_invitation_after_login(): void
    {
        $sender = User::factory()->create(['nickname' => 'CineAmi', 'directory_visible' => false, 'share_real_name' => false]);
        $recipient = User::factory()->create(['password' => bcrypt('invite-test-password')]);
        [$invitation, $url] = $this->link($sender);
        $this->get($url)->assertOk()->assertSee('CineAmi')->assertDontSee($sender->email)
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertSame(0, UserConnection::count());
        $this->post('/login', ['email' => $recipient->email, 'password' => 'invite-test-password'])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Accepter l’invitation');
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->post(route('contact-invitations.accept', $this->token($url)))->assertRedirect(route('contacts.index'));
        $this->assertSame('accepted', UserConnection::sole()->status);
        $this->assertSame($sender->id, UserConnection::sole()->requested_by);
        $this->assertSame($recipient->id, $invitation->fresh()->accepted_by);
        $this->assertSame(1, $sender->socialEvents()->where('kind', 'accepted')->count());
        $this->post(route('contact-invitations.accept', $this->token($url)))->assertGone();
        $this->assertSame(1, SocialEvent::count());
    }

    public function test_new_account_returns_to_invitation_without_creating_a_relation_automatically(): void
    {
        [, $url] = $this->link(User::factory()->create());
        $this->get($url)->assertOk();
        $this->post('/register', ['first_name' => 'Nouvel', 'last_name' => 'Ami', 'email' => 'newfriend@example.test',
            'password' => 'a-safe-test-password', 'password_confirmation' => 'a-safe-test-password'])->assertRedirect($url);
        $this->assertSame(0, UserConnection::count());
        $this->get($url)->assertSee('Accepter l’invitation');
    }

    public function test_notification_onboarding_is_preserved_and_invitation_remains_available_after_signup(): void
    {
        $this->mock(NotificationEmailVerification::class)->shouldReceive('send')->once();
        [, $url] = $this->link(User::factory()->create());
        $this->get($url);
        $this->post('/register', ['first_name' => 'Nouvel', 'last_name' => 'Ami', 'email' => 'alertsfriend@example.test',
            'password' => 'a-safe-test-password', 'password_confirmation' => 'a-safe-test-password',
            'notify_opt_in' => 1, 'notify_email' => 1])->assertRedirect(route('account.edit').'#notifications')->assertSessionHas('offer_push_permission', true);
        $this->get('/account')->assertOk()->assertSee($url)->assertSee('reprendre la mise en relation');
        $this->assertSame(0, UserConnection::count());
    }

    public function test_expired_revoked_and_self_invitations_cannot_create_relations(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create();
        [$row, $url] = $this->link($a);
        $this->actingAs($a)->post(route('contact-invitations.accept', $this->token($url)))->assertUnprocessable();
        $this->actingAs($b)->delete(route('contact-invitations.revoke', $row))->assertNotFound();
        $this->actingAs($a)->delete(route('contact-invitations.revoke', $row))->assertRedirect();
        $this->actingAs($b)->get($url)->assertGone()->assertDontSee($a->email);
        $this->post(route('contact-invitations.accept', $this->token($url)))->assertGone();
        [$expired, $expiredUrl] = $this->link($a);
        $expired->update(['expires_at' => now()->subSecond()]);
        $this->get($expiredUrl)->assertGone();
        $this->post(route('contact-invitations.accept', $this->token($expiredUrl)))->assertGone();
        $this->assertSame(0, UserConnection::count());
    }

    public function test_invitations_never_override_blocked_or_declined_relations_and_are_single_use(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create(); $c = User::factory()->create();
        $ids = [$a->id, $b->id]; sort($ids);
        $pair = UserConnection::create(['user_low_id' => $ids[0], 'user_high_id' => $ids[1], 'requested_by' => $a->id, 'status' => 'blocked', 'blocked_by' => $b->id]);
        [$row, $url] = $this->link($a);
        $this->actingAs($b)->post(route('contact-invitations.accept', $this->token($url)))->assertForbidden();
        $this->assertNull($row->fresh()->accepted_at);
        $pair->update(['status' => 'declined', 'blocked_by' => null]);
        $this->post(route('contact-invitations.accept', $this->token($url)))->assertForbidden();
        $pair->update(['status' => 'pending']);
        $this->post(route('contact-invitations.accept', $this->token($url)))->assertRedirect();
        $this->actingAs($c)->post(route('contact-invitations.accept', $this->token($url)))->assertGone();
        $this->assertSame(1, UserConnection::count());
        $this->assertSame(1, SocialEvent::count());
    }

    public function test_email_invites_work_for_existing_or_new_users_and_do_not_import_phone_data(): void
    {
        $a = User::factory()->create(['email_verified_at' => now(), 'nickname' => 'CineAmi']);
        $existing = User::factory()->create();
        $this->mock(NotificationEmailSender::class)->shouldReceive('send')->twice()->withArgs(function ($recipient, $mail) {
            return is_string($recipient) && $mail instanceof ContactInvitationMail && $mail->senderName === 'CineAmi';
        });
        $this->actingAs($a);
        foreach ([$existing->email, 'notregistered@example.test'] as $address) {
            $this->postJson(route('contact-invitations.store'), ['channel' => 'email', 'email' => $address])->assertOk()
                ->assertJsonMissingPath('url')->assertJsonPath('message', 'Demande d’invitation prise en compte. À réception, ton proche pourra créer son compte ou se connecter, puis accepter.');
        }
        $this->assertSame(2, ContactInvitation::whereNotNull('email_sent_at')->count());
        $this->assertSame(0, UserConnection::count());
        $this->assertSame(0, SocialEvent::count());
        $this->assertStringNotContainsString('notregistered@example.test', json_encode(DB::table('contact_invitations')->get()));
    }

    public function test_email_requires_verified_sender_and_failures_revoke_the_link_without_logging_secrets(): void
    {
        $a = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($a)->postJson(route('contact-invitations.store'), ['channel' => 'email', 'email' => 'friend@example.test'])->assertUnprocessable();
        $this->assertSame(0, ContactInvitation::count());
        $a->forceFill(['email_verified_at' => now()])->save();
        $this->mock(NotificationEmailSender::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('private transport details'));
        $this->postJson(route('contact-invitations.store'), ['channel' => 'email', 'email' => 'friend@example.test'])->assertStatus(503)->assertDontSee('private transport details');
        $this->assertNotNull(ContactInvitation::sole()->revoked_at);
    }

    public function test_brevo_receives_an_explicit_invitation_with_html_and_text_without_auto_enabling_notifications(): void
    {
        $settings = new \App\Models\NotificationMailSetting(['brevo_enabled' => true, 'brevo_api_key' => 'fake-key', 'sender_email' => 'vod@example.test', 'sender_name' => 'VOD Finder']);
        $settings->id = 1; $settings->save();
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'isolated-test'], 201)]);
        $sender = User::factory()->create(['email_verified_at' => now(), 'nickname' => 'CineAmi', 'notify_opt_in' => false]);
        $this->actingAs($sender)->postJson(route('contact-invitations.store'), ['channel' => 'email', 'email' => 'friend@example.test'])->assertOk();
        Http::assertSent(fn ($request) => $request['to'][0]['email'] === 'friend@example.test'
            && str_contains($request['htmlContent'], '/invite/') && str_contains($request['textContent'], '/invite/')
            && str_contains($request['htmlContent'], 'CineAmi'));
        $this->assertFalse($sender->fresh()->notify_opt_in);
    }

    public function test_generation_is_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 5; $i++) $this->postJson(route('contact-invitations.store'), ['channel' => 'link'])->assertCreated();
        $this->postJson(route('contact-invitations.store'), ['channel' => 'link'])->assertStatus(429);
        $this->assertSame(5, ContactInvitation::count());
    }
    public function test_expired_invitation_cleanup_does_not_remove_recent_links_or_relationships(): void
    {
        $a = User::factory()->create();
        [$old] = $this->link($a); $old->update(['expires_at' => now()->subDays(31)]);
        [$recent] = $this->link($a); $recent->update(['expires_at' => now()->subDay()]);
        [$active] = $this->link($a);
        $this->assertSame(1, app(ContactInvitations::class)->prune());
        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertModelExists($active);
    }

    public function test_deleted_recipient_does_not_make_a_used_link_reusable_and_hidden_names_are_respected(): void
    {
        $a = User::factory()->create(['first_name' => 'PrivateFirst', 'last_name' => 'PrivateLast', 'nickname' => 'PublicPseudo', 'share_real_name' => false]);
        $b = User::factory()->create();
        [$row, $url] = $this->link($a);
        $this->get($url)->assertSee('PublicPseudo')->assertDontSee('PrivateFirst')->assertDontSee('PrivateLast');
        $this->actingAs($b)->post(route('contact-invitations.accept', $this->token($url)))->assertRedirect();
        $b->delete();
        $this->assertNull($row->fresh()->accepted_by);
        $this->assertFalse($row->fresh()->usable());
    }

    public function test_invitation_tokens_are_redacted_from_admin_logs(): void
    {
        [, $url] = $this->link(User::factory()->create());
        $file = storage_path('logs/contact-invitation-test.log');
        try {
            file_put_contents($file, '[2026-11-08 12:00:00] testing.ERROR: '.$url);
            $this->assertStringNotContainsString($this->token($url), app(\App\Services\AdminLogReader::class)->read(basename($file)));
        } finally { if (is_file($file)) unlink($file); }
    }

    public function test_continuation_never_redirects_to_an_arbitrary_url(): void
    {
        $user = User::factory()->create(['password' => bcrypt('invite-test-password')]);
        $this->withSession(['contact_invitation' => 'https://outside.example/private'])->post('/login', ['email' => $user->email, 'password' => 'invite-test-password'])->assertRedirect(route('search.index'));
    }

    public function test_email_invites_respect_a_block_without_disclosing_the_recipient_account(): void
    {
        $a = User::factory()->create(['email_verified_at' => now()]); $b = User::factory()->create();
        $ids = [$a->id, $b->id]; sort($ids);
        UserConnection::create(['user_low_id' => $ids[0], 'user_high_id' => $ids[1], 'requested_by' => $a->id, 'status' => 'blocked', 'blocked_by' => $b->id]);
        $this->mock(NotificationEmailSender::class)->shouldNotReceive('send');
        $this->actingAs($a)->postJson(route('contact-invitations.store'), ['channel' => 'email', 'email' => $b->email])->assertOk()
            ->assertJsonPath('message', 'Demande d’invitation prise en compte. À réception, ton proche pourra créer son compte ou se connecter, puis accepter.')
            ->assertJsonMissingPath('recipient');
        $row = ContactInvitation::sole();
        $this->assertNull($row->revoked_at);
        $this->assertNull($row->email_sent_at);
        $this->assertSame('blocked', UserConnection::sole()->status);
    }

    public function test_existing_oauth_account_returns_to_the_pending_invitation(): void
    {
        config(['services.google.enabled' => true, 'services.google.client_id' => 'fake-client', 'services.google.client_secret' => 'fake-secret']);
        $recipient = User::factory()->create();
        $recipient->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'known-provider-user']);
        [, $url] = $this->link(User::factory()->create());
        $this->get($url);
        $this->mock(\App\Services\SocialLogin::class)->shouldReceive('identity')->once()->andReturn([
            'provider' => 'google', 'provider_id' => 'known-provider-user', 'email' => $recipient->email,
            'email_verified' => true, 'first_name' => 'Test', 'last_name' => 'Ami', 'expires_at' => now()->addMinutes(10)->timestamp]);
        $this->withSession(['state' => 'invite-state', 'oauth.flow' => ['provider' => 'google', 'user_id' => null, 'expires_at' => now()->addMinutes(10)->timestamp]])
            ->get('/auth/google/callback?state=invite-state&code=fake-code')->assertRedirect($url);
        $this->assertAuthenticatedAs($recipient);
        $this->assertSame(0, UserConnection::count());
    }

    public function test_pending_invitation_can_be_dismissed_without_creating_or_revoking_a_relation(): void
    {
        [$row, $url] = $this->link(User::factory()->create());
        $this->get($url);
        $this->actingAs(User::factory()->create())->post(route('contact-invitations.dismiss'))->assertRedirect(route('contacts.index'));
        $this->assertNull(session('contact_invitation'));
        $this->assertTrue($row->fresh()->usable());
        $this->assertSame(0, UserConnection::count());
    }

}
