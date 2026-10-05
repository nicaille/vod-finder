<?php

namespace Tests\Feature;

use App\Models\{User, UserConnection, Recommendation, SocialEvent, SocialDelivery};
use App\Mail\SocialAnnouncement;
use App\Services\{BrowserPushService, SocialDeliveryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class SocialNotificationsTest extends TestCase
{
    use RefreshDatabase;
    private function event(): SocialEvent
    {
        $a = User::factory()->create(['nickname' => 'MonAmi']);
        $b = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => true, 'email_verified_at' => now()]);
        UserConnection::create(['user_low_id' => $a->id, 'user_high_id' => $b->id, 'requested_by' => $a->id, 'status' => 'accepted']);
        $rec = Recommendation::create(['sender_id' => $a->id, 'recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 1, 'content' => ['title' => 'Dune', 'image' => null, 'description' => 'À découvrir', 'providers' => []]]);
        return SocialEvent::create(['user_id' => $b->id, 'actor_id' => $a->id, 'recommendation_id' => $rec->id, 'kind' => 'recommendation']);
    }
    public function test_email_and_push_respect_preferences_and_are_delivered_once(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $event = $this->event();
        $user = $event->user;
        $endpoint = 'https://fcm.googleapis.com/fcm/send/social-test';
        $sub = $user->pushSubscriptions()->create(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => str_repeat('A', 87), 'auth_token' => str_repeat('B', 22)]);
        $this->mock(BrowserPushService::class, function ($m) use ($event) {
            $m->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $m->shouldReceive('send')->once()->withArgs(fn($s, $payload) => $payload['url'] === '/account/recommendations/' . $event->recommendation_id)->andReturn(true);
        });
        $result = app(SocialDeliveryService::class)->deliver();
        $this->assertSame(['sent' => 2, 'failed' => 0], $result);
        Mail::assertSent(SocialAnnouncement::class, fn($mail) => $mail->hasTo($user->email));
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        $this->assertSame(2, SocialDelivery::count());
    }
    public function test_unverified_or_disabled_preferences_do_not_send_email(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $event = $this->event();
        $event->user->forceFill(['email_verified_at' => null, 'notify_web' => false])->save();
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        Mail::assertNothingSent();
    }
    public function test_blocked_contact_or_historical_event_is_not_delivered(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $event = $this->event();
        $event->user->update(['notify_web' => false]);
        UserConnection::first()->update(['status' => 'blocked', 'blocked_by' => $event->user_id]);
        app(SocialDeliveryService::class)->deliver();
        Mail::assertNothingSent();
        UserConnection::first()->update(['status' => 'accepted', 'blocked_by' => null]);
        $event->update(['created_at' => now()->subDays(2)]);
        app(SocialDeliveryService::class)->deliver();
        Mail::assertNothingSent();
    }
    public function test_mail_errors_retry_without_counting_log_mailer_as_delivery(): void
    {
        config(['mail.default' => 'log']);
        $event = $this->event();
        $event->user->update(['notify_web' => false]);
        $this->assertSame(['sent' => 0, 'failed' => 1], app(SocialDeliveryService::class)->deliver());
        $delivery = SocialDelivery::firstOrFail();
        $this->assertNull($delivery->sent_at);
        $this->assertTrue($delivery->retry_at->isFuture());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
    }
    public function test_mail_contains_private_receipt_link_and_no_unshared_real_name(): void
    {
        $event = $this->event();
        $event->actor->update(['first_name' => 'PrivateFirstName', 'last_name' => 'PrivateLastName', 'share_real_name' => false]);
        $html = (new SocialAnnouncement($event->fresh(['actor', 'recommendation'])))->render();
        $this->assertStringContainsString('MonAmi', $html);
        $this->assertStringContainsString('/account/recommendations/' . $event->recommendation_id, $html);
        $this->assertStringNotContainsString('PrivateFirstName', $html);
    }
}
