<?php

namespace Tests\Feature;

use App\Models\{PushSubscription, User};
use App\Services\BrowserPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function device(User $user, string $name = 'device'): PushSubscription
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$name;
        return $user->pushSubscriptions()->create([
            'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => str_repeat('A', 87), 'auth_token' => str_repeat('B', 22),
        ]);
    }

    public function test_only_the_authenticated_users_registered_device_can_be_tested(): void
    {
        $user = $this->admin();
        $other = $this->device(User::factory()->create());
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->postJson('/notifications/push/test', ['endpoint' => $other->endpoint])->assertUnauthorized();
        $this->actingAsConfirmedAdmin($user)->postJson('/notifications/push/test', ['endpoint' => $other->endpoint])->assertNotFound();
        $this->postJson('/notifications/push/test', ['endpoint' => 'https://example.test/private'])->assertNotFound();
        $this->postJson('/notifications/push/test', [])->assertUnprocessable();
    }

    public function test_test_is_immediate_and_targets_one_device_without_changing_preferences(): void
    {
        $user = $this->admin(['notify_opt_in' => false, 'notify_web' => false]);
        $device = $this->device($user, 'current');
        $this->device($user, 'another');
        $this->mock(BrowserPushService::class, function ($mock) use ($device) {
            $mock->shouldReceive('keys')->once()->andReturn(['publicKey' => 'test', 'privateKey' => 'secret']);
            $mock->shouldReceive('send')->once()->withArgs(fn ($target, $payload) =>
                $target->id === $device->id && $payload['url'] === '/account'
                && str_starts_with($payload['tag'], 'push-test-')
                && $payload['title'] === 'VOD Finder · Test de notification'
            )->andReturn(true);
        });
        $this->actingAsConfirmedAdmin($user)->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])
            ->assertOk()->assertJsonPath('message', fn ($message) => str_contains($message, 'ne confirme pas encore'))
            ->assertDontSee('secret');
        $this->assertFalse($user->fresh()->notify_opt_in);
        $this->assertFalse($user->fresh()->notify_web);
        $this->assertSame(2, PushSubscription::count());
    }

    public function test_expired_subscription_is_removed_and_reported(): void
    {
        $user = $this->admin();
        $device = $this->device($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->andReturn(false);
        });
        $this->actingAsConfirmedAdmin($user)->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertStatus(410);
        $this->assertModelMissing($device);
    }

    public function test_transient_failure_preserves_subscription_and_hides_details(): void
    {
        $user = $this->admin();
        $device = $this->device($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('private-endpoint-secret'));
        });
        $this->actingAsConfirmedAdmin($user)->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])
            ->assertStatus(502)->assertDontSee('private-endpoint-secret');
        $this->assertModelExists($device);
    }

    public function test_missing_keys_prevent_delivery(): void
    {
        $user = $this->admin();
        $device = $this->device($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->once()->andReturn(null);
            $mock->shouldNotReceive('send');
        });
        $this->actingAsConfirmedAdmin($user)->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertStatus(503);
    }

    public function test_push_tests_are_rate_limited(): void
    {
        $user = $this->admin();
        $device = $this->device($user);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->times(3)->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->times(3)->andReturn(true);
        });
        $this->actingAsConfirmedAdmin($user);
        for ($i = 0; $i < 3; $i++) $this->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertOk();
        $this->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertTooManyRequests();
    }

    public function test_status_requests_do_not_consume_the_push_test_allowance(): void
    {
        $admin = $this->admin(); $device = $this->device($admin);
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->once()->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->once()->andReturn(true);
        });
        $this->actingAsConfirmedAdmin($admin);
        for ($i = 0; $i < 4; $i++) $this->postJson('/notifications/push/status', ['endpoint' => $device->endpoint])->assertOk();
        $this->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertOk();
    }

    public function test_push_test_limit_is_private_to_each_administrator(): void
    {
        $first = $this->admin(); $firstDevice = $this->device($first, 'first');
        $second = $this->admin(); $secondDevice = $this->device($second, 'second');
        $this->mock(BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->times(4)->andReturn(['publicKey' => 'test']);
            $mock->shouldReceive('send')->times(4)->andReturn(true);
        });
        $this->actingAsConfirmedAdmin($first);
        for ($i = 0; $i < 3; $i++) $this->postJson('/notifications/push/test', ['endpoint' => $firstDevice->endpoint])->assertOk();
        $this->postJson('/notifications/push/test', ['endpoint' => $firstDevice->endpoint])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->actingAsConfirmedAdmin($second)->postJson('/notifications/push/test', ['endpoint' => $secondDevice->endpoint])->assertOk();
    }

    public function test_members_cannot_see_the_button_or_send_tests_even_to_their_own_device(): void
    {
        $member = User::factory()->create();
        $device = $this->device($member);
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->actingAs($member)->get('/account')->assertOk()->assertDontSee('data-test-push', false);
        $this->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertForbidden();
        $this->actingAsConfirmedAdmin($this->admin())->get('/account')->assertOk()->assertSee('data-test-push disabled', false);
    }

    public function test_unconfirmed_or_revoked_admin_cannot_send_tests(): void
    {
        $admin = $this->admin();
        $device = $this->device($admin);
        $this->mock(BrowserPushService::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->actingAs($admin)->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertStatus(423);
        $this->actingAsConfirmedAdmin($admin);
        User::whereKey($admin->id)->update(['is_admin' => false]);
        $this->postJson('/notifications/push/test', ['endpoint' => $device->endpoint])->assertForbidden();
    }
}
