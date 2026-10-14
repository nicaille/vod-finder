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
    private function device(SocialEvent $event): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/private-social-device';
        $event->user->pushSubscriptions()->create(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => str_repeat('A', 87), 'auth_token' => str_repeat('B', 22)]);
    }

    public function test_recommendation_sends_after_commit_without_waiting_for_cron_or_repeating(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $event = $this->event();
        $this->device($event);
        $event->delete();
        $this->mock(\App\Services\RecommendationContent::class, fn ($mock) => $mock->shouldReceive('get')->andReturn(['title' => 'New film', 'image' => null]));
        $baseline = \Illuminate\Support\Facades\DB::connection()->transactionLevel();
        $this->mock(BrowserPushService::class, function ($mock) use ($baseline) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->withArgs(function ($device, $payload) use ($baseline) {
                $this->assertSame($baseline, \Illuminate\Support\Facades\DB::connection()->transactionLevel());
                $this->assertStringContainsString('/account/recommendations/', $payload['url']);
                return true;
            })->andReturn(true);
        });
        $data = ['recipient_id' => $event->user_id, 'type' => 'movie', 'tmdb_id' => 42];
        $this->actingAs($event->actor)->post('/account/recommendations', $data)->assertRedirect();
        $this->assertSame(2, SocialDelivery::whereNotNull('sent_at')->count());
        Mail::assertSent(SocialAnnouncement::class, 1);
        $this->post('/account/recommendations', $data)->assertRedirect();
        $this->assertDatabaseCount('social_events', 1);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        Mail::assertSent(SocialAnnouncement::class, 1);
    }

    public function test_immediate_failure_preserves_recommendation_and_cron_retries(): void
    {
        $event = $this->event();
        $event->user->update(['notify_email' => false]);
        $this->device($event);
        $event->delete();
        $this->mock(\App\Services\RecommendationContent::class, fn ($mock) => $mock->shouldReceive('get')->andReturn(['title' => 'New film']));
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Private transport details'));
            $mock->shouldReceive('send')->once()->andReturn(true);
        });
        $this->actingAs($event->actor)->post('/account/recommendations', ['recipient_id' => $event->user_id, 'type' => 'movie', 'tmdb_id' => 42])->assertRedirect();
        $this->assertDatabaseHas('recommendations', ['tmdb_id' => 42]);
        $delivery = SocialDelivery::firstOrFail();
        $this->assertNull($delivery->sent_at);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        $this->travel(16)->minutes();
        $this->assertSame(['sent' => 1, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
        $this->assertNotNull($delivery->fresh()->sent_at);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver());
    }

    public function test_targeted_delivery_does_not_send_other_pending_events(): void
    {
        $first = $this->event();
        $first->actor->update(['nickname' => 'FirstFriend']);
        $second = $this->event();
        $first->user->update(['notify_web' => false]);
        $second->user->update(['notify_web' => false]);
        config(['mail.default' => 'smtp']);
        Mail::fake();
        app(SocialDeliveryService::class)->deliver($second->id);
        Mail::assertSent(SocialAnnouncement::class, fn ($mail) => $mail->hasTo($second->user->email));
        Mail::assertSent(SocialAnnouncement::class, 1);
        $this->assertDatabaseMissing('social_deliveries', ['social_event_id' => $first->id]);
    }

    public function test_busy_destination_is_not_sent_concurrently(): void
    {
        $event = $this->event();
        $event->user->update(['notify_email' => false]);
        $this->device($event);
        $device = $event->user->pushSubscriptions()->first();
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->andReturn(true);
        });
        $lock = \Illuminate\Support\Facades\Cache::lock('social-delivery-'.$event->id.'-web-'.hash('sha256', $device->endpoint_hash), 120);
        $this->assertTrue($lock->get());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(SocialDeliveryService::class)->deliver($event->id));
        $this->assertDatabaseCount('social_deliveries', 0);
        $lock->release();
        $this->assertSame(['sent' => 1, 'failed' => 0], app(SocialDeliveryService::class)->deliver($event->id));
    }

    public function test_immediate_recommendation_preserves_opt_out(): void
    {
        Mail::fake();
        $event = $this->event();
        $event->user->update(['notify_opt_in' => false]);
        $this->device($event);
        $event->delete();
        $this->mock(\App\Services\RecommendationContent::class, fn ($mock) => $mock->shouldReceive('get')->andReturn(['title' => 'New film']));
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->actingAs($event->actor)->post('/account/recommendations', ['recipient_id' => $event->user_id, 'type' => 'movie', 'tmdb_id' => 42])->assertRedirect();
        $this->assertDatabaseHas('recommendations', ['tmdb_id' => 42]);
        $this->assertDatabaseCount('social_deliveries', 0);
        Mail::assertNothingSent();
    }

}
