<?php

namespace Tests\Feature;

use App\Models\{TaskRun, User, UserConnection, Recommendation, SocialEvent, SocialDelivery};
use App\Services\NotificationDeliveryBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache, Http, Mail};
use Tests\TestCase;

class CloudflareCronTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private array $trustedHosts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trustedHosts = \Illuminate\Http\Request::getTrustedHosts();
        Cache::flush();
        $this->token = str_repeat('a', 64);
        config(['cloudflare-cron.enabled' => true, 'cloudflare-cron.token' => $this->token]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // The production HTTPS test activates Symfony's process-wide host checks.
        \Illuminate\Http\Request::setTrustedHosts($this->trustedHosts);
        parent::tearDown();
    }

    private function headers(): array { return ['Authorization' => 'Bearer '.$this->token]; }

    public function test_endpoint_is_disabled_by_default_and_requires_the_full_secret(): void
    {
        Artisan::shouldReceive('call')->never();
        config(['cloudflare-cron.enabled' => false]);
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertNotFound();
        config(['cloudflare-cron.enabled' => true]);
        $this->postJson('/api/internal/notifications/tick')->assertUnauthorized();
        $this->postJson('/api/internal/notifications/tick', [], ['Authorization' => 'Bearer '.str_repeat('b', 64)])->assertUnauthorized();
        $this->postJson('/api/internal/notifications/tick?token='.$this->token)->assertUnauthorized();
        config(['cloudflare-cron.token' => 'weak']);
        $this->postJson('/api/internal/notifications/tick', [], ['Authorization' => 'Bearer weak'])->assertNotFound();
        $this->assertDatabaseCount('task_runs', 0);
    }

    public function test_status_does_not_send_notifications_and_tick_cannot_be_called_with_get(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->getJson('/api/internal/notifications/status', $this->headers())->assertOk()->assertJson(['ok' => true, 'version' => 1])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/internal/notifications/tick', $this->headers())->assertStatus(405);
        $this->assertDatabaseCount('task_runs', 0);
    }

    public function test_only_fixed_notification_batch_runs_once_per_minute_and_is_observed(): void
    {
        $this->travelTo(now()->startOfMinute());
        Artisan::shouldReceive('call')->twice()->with('notifications:deliver', ['--batch' => 8])->andReturn(0);
        $this->postJson('/api/internal/notifications/tick', ['command' => 'migrate:fresh'], $this->headers())->assertOk()->assertJson(['status' => 'processed']);
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk()->assertJson(['status' => 'already-triggered']);
        $this->travel(1)->minutes();
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk();
        $this->assertDatabaseHas('task_runs', ['name' => 'cron:cloudflare', 'status' => 'success']);
        $this->assertDatabaseMissing('task_runs', ['name' => 'series:sync']);
        $this->assertDatabaseMissing('task_runs', ['name' => 'availability:sync']);
    }

    public function test_delivery_failure_is_visible_to_cloudflare_and_admin(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(1);
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertStatus(503)->assertJson(['ok' => false]);
        $this->assertDatabaseHas('task_runs', ['name' => 'cron:cloudflare', 'status' => 'failed']);
    }

    public function test_production_requires_https_and_does_not_trust_a_spoofed_forwarded_header(): void
    {
        $this->app['env'] = 'production';
        Artisan::shouldReceive('call')->never();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21'])->postJson('/api/internal/notifications/tick', [], $this->headers() + ['X-Forwarded-Proto' => 'https'])->assertForbidden();
        $this->getJson('https://localhost/api/internal/notifications/status', $this->headers())->assertOk();
    }

    public function test_scheduled_batches_resume_without_duplicate_delivery_and_share_the_ovh_lock(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $sender = User::factory()->create();
        $recipient = User::factory()->create(['notify_opt_in' => true, 'notify_email' => true, 'notify_web' => true]);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/cron-test';
        $recipient->pushSubscriptions()->create(['endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => str_repeat('A', 87), 'auth_token' => str_repeat('B', 22)]);
        $this->mock(\App\Services\BrowserPushService::class, function ($mock) {
            $mock->shouldReceive('keys')->andReturn(['publicKey' => 'fake']);
            $mock->shouldReceive('send')->times(9)->andReturn(true);
        });
        UserConnection::create(['user_low_id' => $sender->id, 'user_high_id' => $recipient->id, 'requested_by' => $sender->id, 'status' => 'accepted']);
        for ($id = 1; $id <= 9; $id++) {
            $rec = Recommendation::create(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => $id, 'content' => ['title' => 'Titre '.$id]]);
            SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'recommendation_id' => $rec->id, 'kind' => 'recommendation']);
        }
        $lock = Cache::lock('episode-notification-delivery', 3600);
        $lock->get();
        try {
            $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk();
            Mail::assertNothingSent();
        } finally { $lock->release(); }
        $this->travel(1)->minutes();
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk();
        $this->assertSame(8, SocialDelivery::whereNotNull('sent_at')->count());
        $this->travel(1)->minutes();
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk();
        $this->assertSame(16, SocialDelivery::whereNotNull('sent_at')->count());
        $this->travel(1)->minutes();
        $this->postJson('/api/internal/notifications/tick', [], $this->headers())->assertOk();
        $this->assertSame(18, SocialDelivery::whereNotNull('sent_at')->count());
        $this->artisan('notifications:deliver')->assertSuccessful();
        Mail::assertSent(\App\Mail\SocialAnnouncement::class, 9);
        $this->assertSame(18, (int) SocialDelivery::sum('attempts'));
        $this->assertDatabaseHas('task_runs', ['name' => 'notifications:deliver', 'status' => 'success']);
        Http::assertNothingSent();
    }

    public function test_budget_and_invalid_command_options_prevent_unbounded_http_batches(): void
    {
        $budget = new NotificationDeliveryBudget(1);
        $this->assertTrue($budget->acquire());
        $this->assertFalse($budget->acquire());
        $this->assertFalse((new NotificationDeliveryBudget(8, 0))->available());
        $this->artisan('notifications:deliver', ['--batch' => 21])->assertExitCode(2);
        $this->assertDatabaseCount('task_runs', 0);
    }
}
