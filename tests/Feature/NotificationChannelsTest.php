<?php

namespace Tests\Feature;

use App\Mail\EpisodeAnnouncement;
use App\Models\AlertDelivery;
use App\Models\EpisodeAlert;
use App\Models\PushSubscription;
use App\Models\TrackedSeries;
use App\Models\User;
use App\Services\AlertDeliveryService;
use App\Services\BrowserPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(User $user, string $suffix = 'device'): PushSubscription
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$suffix;
        return $user->pushSubscriptions()->create(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => str_repeat('A', 87), 'auth_token' => str_repeat('B', 22)]);
    }

    private function alert(User $user): EpisodeAlert
    {
        $series = TrackedSeries::create(['tmdb_id' => 247718, 'name' => 'MobLand']);
        $user->seriesFollows()->create(['tracked_series_id' => $series->id, 'alerts_enabled' => true]);
        $episode = $series->episodes()->create(['season_number' => 2, 'episode_number' => 4, 'name' => 'Blank Curtain', 'air_date' => now('Europe/Paris')->toDateString()]);
        return $user->episodeAlerts()->create(['series_episode_id' => $episode->id]);
    }

    public function test_account_saves_both_channel_choices_and_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $data = ['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => $user->email, 'notify_opt_in' => 1];
        $this->actingAs($user)->put('/account', $data + ['notify_email' => 1, 'notify_web' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->notify_email);
        $this->assertTrue($user->fresh()->notify_web);
        $this->put('/account', $data + ['notify_email' => 0, 'notify_web' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->notify_email);
        $this->assertFalse($user->fresh()->notify_web);
        $this->putJson('/account', $data + ['notify_web' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('notify_web');
    }

    public function test_push_registration_is_private_and_does_not_expose_private_keys(): void
    {
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldReceive('keys')->andReturn(['publicKey' => 'public-test', 'privateKey' => 'secret-test']));
        $this->getJson('/notifications/push/key')->assertUnauthorized();
        $user = User::factory()->create();
        $data = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/device', 'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('B', 22)]];
        $this->actingAs($user)->getJson('/notifications/push/key')->assertOk()->assertExactJson(['publicKey' => 'public-test']);
        $this->postJson('/notifications/push', $data)->assertOk();
        $this->postJson('/notifications/push', $data)->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->postJson('/notifications/push/status', ['endpoint' => $data['endpoint']])->assertOk()->assertJson(['registered' => true]);
        $this->actingAs(User::factory()->create())->postJson('/notifications/push', $data)->assertConflict();
        $this->postJson('/notifications/push/status', ['endpoint' => $data['endpoint']])->assertOk()->assertJson(['registered' => false]);
        $this->deleteJson('/notifications/push', ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->actingAs($user)->deleteJson('/notifications/push', ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_push_endpoints_cannot_target_local_or_arbitrary_servers(): void
    {
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test', 'privateKey' => 'test']));
        $this->actingAs(User::factory()->create());
        foreach (['https://127.0.0.1/private', 'http://fcm.googleapis.com/send', 'https://example.com/send', 'https://fcm.googleapis.com:8443/send', 'https://evil@fcm.googleapis.com/send'] as $endpoint) {
            $this->postJson('/notifications/push', ['endpoint' => $endpoint, 'keys' => ['p256dh' => str_repeat('A',87), 'auth' => str_repeat('B',22)]])->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        }
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_email_and_two_devices_deliver_once_on_repeated_runs(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => true]);
        $alert = $this->alert($user);
        $this->subscription($user, 'one');
        $this->subscription($user, 'two');
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test', 'privateKey' => 'test']);
            $mock->shouldReceive('send')->twice()->andReturn(true);
        });
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->artisan('notifications:deliver')->assertSuccessful();
        Mail::assertSent(EpisodeAnnouncement::class, fn ($mail) => $mail->hasTo($user->email) && $mail->alert->id === $alert->id);
        Mail::assertSentCount(1);
        $this->assertSame(3, AlertDelivery::whereNotNull('sent_at')->count());
    }

    public function test_disabled_channels_global_preferences_and_unverified_email_do_not_send(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create(['notify_opt_in' => false, 'notify_email' => true, 'notify_web' => true, 'email_verified_at' => null]);
        $this->alert($user);
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldReceive('keys')->andReturnNull());
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $user->update(['notify_opt_in' => true]);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $user->update(['email_verified_at' => now(), 'notify_email' => false, 'notify_web' => false]);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        Mail::assertNothingSent();
    }

    public function test_expired_push_subscription_is_removed(): void
    {
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_web' => true]);
        $this->alert($user);
        $this->subscription($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test', 'privateKey' => 'test']);
            $mock->shouldReceive('send')->once()->andReturn(false);
        });
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_failed_delivery_retries_after_delay_without_duplicate_success(): void
    {
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_web' => true]);
        $this->alert($user);
        $this->subscription($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test', 'privateKey' => 'test']);
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('test'));
        });
        $this->artisan('notifications:deliver')->assertFailed();
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->assertNull(AlertDelivery::firstOrFail()->sent_at);
        $this->travel(16)->minutes();
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test', 'privateKey' => 'test']);
            $mock->shouldReceive('send')->once()->andReturn(true);
        });
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->assertNotNull(AlertDelivery::firstOrFail()->sent_at);
        $this->assertSame(2, AlertDelivery::firstOrFail()->attempts);
    }

    public function test_unfollowed_postponed_or_historical_alerts_do_not_deliver(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true]);
        $alert = $this->alert($user);
        $user->seriesFollows()->update(['alerts_enabled' => false]);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $user->seriesFollows()->update(['alerts_enabled' => true]);
        $alert->episode->update(['air_date' => now('Europe/Paris')->addDay()->toDateString()]);
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        $alert->episode->update(['air_date' => now('Europe/Paris')->toDateString()]);
        $alert->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->assertSame(['sent' => 0, 'failed' => 0], app(AlertDeliveryService::class)->deliver());
        Mail::assertNothingSent();
    }

    public function test_logout_revokes_only_the_current_device(): void
    {
        $user = User::factory()->create();
        $device = $this->subscription($user, 'one');
        $this->subscription($user, 'two');
        $this->actingAs($user)->post('/logout', ['push_endpoint' => $device->endpoint])->assertRedirect('/');
        $this->assertSame(1, $user->pushSubscriptions()->count());
        $this->assertGuest();
    }

    public function test_push_setup_preserves_keys_and_keeps_them_outside_public_storage(): void
    {
        $path = sys_get_temp_dir().'/vod-push-test-'.bin2hex(random_bytes(8)).'.json';
        config(['webpush.key_file' => $path]);
        try {
            $this->artisan('notifications:setup-push')->assertSuccessful();
            $initial = file_get_contents($path);
            $this->artisan('notifications:setup-push')->assertSuccessful();
            $this->assertSame($initial, file_get_contents($path));
            $keys = app(BrowserPushService::class)->keys();
            $this->assertSame(87, strlen($keys['publicKey']));
            $this->assertNotEmpty($keys['privateKey']);
        } finally { if (is_file($path)) unlink($path); }
    }
}
