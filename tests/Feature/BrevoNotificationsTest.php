<?php

namespace Tests\Feature;

use App\Models\{AlertDelivery, EpisodeAlert, NotificationMailSetting, Recommendation, SocialDelivery, SocialEvent, TrackedSeries, User, UserConnection};
use App\Services\{AlertDeliveryService, SocialDeliveryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function settings(bool $enabled = true): NotificationMailSetting
    {
        $settings = new NotificationMailSetting(['brevo_enabled' => $enabled, 'brevo_api_key' => 'test-brevo-key',
            'sender_email' => 'notifications@example.test', 'sender_name' => 'VOD Finder']);
        $settings->id = 1;
        $settings->save();
        return $settings;
    }

    private function alert(User $user): EpisodeAlert
    {
        $series = TrackedSeries::create(['tmdb_id' => 97546, 'name' => 'Ted Lasso']);
        $user->seriesFollows()->create(['tracked_series_id' => $series->id, 'alerts_enabled' => true]);
        $episode = $series->episodes()->create(['season_number' => 4, 'episode_number' => 10, 'name' => 'Final', 'air_date' => now('Europe/Paris')->toDateString()]);
        return EpisodeAlert::create(['user_id' => $user->id, 'series_episode_id' => $episode->id]);
    }

    public function test_only_admins_can_configure_brevo_or_send_tests(): void
    {
        $this->get('/admin/notifications/email')->assertRedirect('/login');
        $this->actingAs(User::factory()->create());
        $this->get('/admin/notifications/email')->assertForbidden();
        $this->put('/admin/notifications/email', ['brevo_enabled' => 1])->assertForbidden();
        $this->post('/admin/notifications/email/test')->assertForbidden();
        $this->assertDatabaseCount('notification_mail_settings', 0);
        Http::assertNothingSent();
    }

    public function test_configuration_encrypts_the_key_and_never_displays_or_serializes_it(): void
    {
        $this->actingAs($this->admin())->put('/admin/notifications/email', [
            'brevo_enabled' => 1, 'brevo_api_key' => 'test-brevo-key', 'sender_email' => 'sender@example.test', 'sender_name' => 'VOD Finder',
        ])->assertRedirect('/admin/notifications/email')->assertSessionHasNoErrors();
        $settings = NotificationMailSetting::findOrFail(1);
        $this->assertTrue($settings->brevo_enabled);
        $this->assertSame('test-brevo-key', $settings->brevo_api_key);
        $this->assertNotSame('test-brevo-key', $settings->getRawOriginal('brevo_api_key'));
        $this->assertArrayNotHasKey('brevo_api_key', $settings->toArray());
        $this->get('/admin/notifications/email')->assertOk()->assertSee('Une clé est enregistrée')
            ->assertDontSee('test-brevo-key')->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    public function test_blank_key_preserves_the_saved_secret_when_disabling_or_reenabling(): void
    {
        $this->settings();
        $this->actingAs($this->admin())->put('/admin/notifications/email', [
            'brevo_enabled' => 0, 'brevo_api_key' => '', 'sender_email' => 'sender@example.test', 'sender_name' => 'New name',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(NotificationMailSetting::findOrFail(1)->brevo_enabled);
        $this->assertSame('test-brevo-key', NotificationMailSetting::findOrFail(1)->brevo_api_key);
        $this->put('/admin/notifications/email', ['brevo_enabled' => 1, 'sender_email' => 'sender@example.test', 'sender_name' => 'New name'])->assertSessionHasNoErrors();
        $this->assertTrue(NotificationMailSetting::findOrFail(1)->brevo_enabled);
    }

    public function test_invalid_settings_cannot_enable_brevo_and_secret_is_not_flashed(): void
    {
        $this->actingAs($this->admin())->from('/admin/notifications/email')->put('/admin/notifications/email', [
            'brevo_enabled' => 1, 'sender_email' => 'sender@example.test', 'sender_name' => 'VOD Finder',
        ])->assertSessionHasErrors('brevo_api_key');
        $this->put('/admin/notifications/email', [
            'brevo_enabled' => 1, 'brevo_api_key' => 'test-secret-never-flash', 'sender_email' => 'invalid', 'sender_name' => 'VOD Finder',
        ])->assertSessionHasErrors('sender_email');
        $this->assertNull(session()->getOldInput('brevo_api_key'));
        $this->assertDatabaseCount('notification_mail_settings', 0);
    }

    public function test_explicit_test_uses_the_saved_configuration_and_only_the_admins_own_address(): void
    {
        $this->settings(false);
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'test-message'], 201)]);
        $admin = $this->admin();
        $this->actingAs($admin)->from('/admin/notifications/email')->post('/admin/notifications/email/test', ['recipient' => 'someone-else@example.test'])
            ->assertRedirect('/admin/notifications/email')->assertSessionHasNoErrors()->assertSessionHas('status');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->hasHeader('api-key', 'test-brevo-key')
            && $request['to'] === [['email' => $admin->email]]
            && $request['sender']['email'] === 'notifications@example.test'
            && str_contains($request['subject'], 'Test') && str_contains($request['htmlContent'], 'VOD Finder')
            && str_contains($request['textContent'], 'envoyé à ta demande')
            && str_contains($request['textContent'], route('admin.notification-mail.edit')));
        Http::assertSentCount(1);
        $this->assertFalse(NotificationMailSetting::findOrFail(1)->brevo_enabled);
    }

    public function test_brevo_rejection_shows_a_safe_error_instead_of_reporting_a_success(): void
    {
        $this->settings();
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'test-brevo-key is invalid'], 401)]);
        $this->actingAs($this->admin())->from('/admin/notifications/email')->post('/admin/notifications/email/test')
            ->assertRedirect('/admin/notifications/email')->assertSessionHasErrors('brevo_test');
        $this->get('/admin/notifications/email')->assertDontSee('test-brevo-key')->assertSee('Brevo n’a pas accepté le test');
    }

    public function test_episode_notifications_use_brevo_with_existing_preferences_and_duplicate_protection(): void
    {
        $this->settings();
        config(['mail.default' => 'log']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'episode-message'], 201)]);
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => false]);
        $alert = $this->alert($user);
        $this->assertSame(['sent' => 1, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        Http::assertSent(fn ($request) => $request['to'] === [['email' => $user->email]] && str_contains($request['subject'], 'Ted Lasso') && str_contains($request['htmlContent'], 'Final')
            && str_contains($request['textContent'], 'Ted Lasso') && str_contains($request['textContent'], 'Final')
            && str_contains($request['textContent'], route('account.edit').'#notifications')
            && str_contains($request['htmlContent'], route('account.edit').'#notifications')
            && str_contains($request['textContent'], 'tu suis cette série'));
        Http::assertSentCount(1);
        $this->assertNotNull(AlertDelivery::where('episode_alert_id', $alert->id)->firstOrFail()->sent_at);
    }

    public function test_social_notifications_also_use_brevo_once(): void
    {
        $this->settings();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'social-message'], 201)]);
        $actor = User::factory()->create();
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => false]);
        $pair = [$actor->id, $user->id];sort($pair);
        UserConnection::create(['user_low_id' => $pair[0], 'user_high_id' => $pair[1], 'requested_by' => $actor->id, 'status' => 'accepted']);
        $recommendation = Recommendation::create(['sender_id' => $actor->id, 'recipient_id' => $user->id, 'type' => 'movie', 'tmdb_id' => 1,
            'content' => ['title' => 'Dune', 'image' => null, 'description' => 'À découvrir', 'providers' => []]]);
        SocialEvent::create(['user_id' => $user->id, 'actor_id' => $actor->id, 'recommendation_id' => $recommendation->id, 'kind' => 'recommendation']);
        $this->assertSame(['sent' => 1, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        Http::assertSent(fn ($request) => str_contains($request['htmlContent'], 'Dune') && $request['to'][0]['email'] === $user->email
            && str_contains($request['textContent'], 'Dune') && str_contains($request['textContent'], 'À découvrir')
            && str_contains($request['textContent'], route('account.edit').'#notifications')
            && str_contains($request['textContent'], 'contacts et recommandations'));
        Http::assertSentCount(1);
        $this->assertSame(1, SocialDelivery::whereNotNull('sent_at')->count());
    }

    public function test_delivery_failure_is_retried_later_without_an_immediate_duplicate(): void
    {
        $this->settings();
        Http::fake(['api.brevo.com/*' => Http::response([], 503)]);
        $this->alert(User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => false]));
        $this->assertSame(['sent' => 0, 'failed' => 1], app(AlertDeliveryService::class)->deliver());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $delivery = AlertDelivery::firstOrFail();
        $this->assertNull($delivery->sent_at);
        $this->assertTrue($delivery->retry_at->isFuture());
        $this->assertSame(1, $delivery->attempts);
        Http::assertSentCount(1);
    }

    public function test_unverified_or_opted_out_users_do_not_receive_brevo_emails(): void
    {
        $this->settings();
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => false, 'email_verified_at' => null]);
        $this->alert($user);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $user->update(['email_verified_at' => now(), 'notify_email' => false]);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        Http::assertNothingSent();
    }

    public function test_unverified_user_can_confirm_their_address_via_brevo_without_smtp(): void
    {
        $this->settings();
        config(['mail.default' => 'log']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'verification-message'], 201)]);
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user)->from('/account')->post('/email/verification-notification')
            ->assertRedirect('/account')->assertSessionHas('status', 'verification-link-sent');
        $request = Http::recorded()->first()[0];
        $this->assertSame([['email' => $user->email]], $request['to']);
        preg_match('/href="([^"]+)"/', $request['htmlContent'], $matches);
        $url = html_entity_decode($matches[1]);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString($url, $request['textContent']);
        $this->assertStringNotContainsString('&amp;', $request['textContent']);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_failed_verification_send_does_not_report_success_or_verify_the_address(): void
    {
        $this->settings();
        Http::fake(['api.brevo.com/*' => Http::response([], 401)]);
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user)->from('/account')->post('/email/verification-notification')
            ->assertRedirect('/account')->assertSessionHasErrors('email_verification')->assertSessionMissing('status');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
