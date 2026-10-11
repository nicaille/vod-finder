<?php

namespace Tests\Feature;

use App\Models\{NotificationMailSetting, User};
use Illuminate\Cache\FileStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\{MessageBag, ViewErrorBag};
use Illuminate\Support\Facades\{DB, Hash, Http};
use Tests\TestCase;

class FrameworkUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rehashes_password_and_keeps_admin_confirmation_valid(): void
    {
        $user = User::factory()->create();
        $oldHash = password_hash('secret-password', PASSWORD_BCRYPT, ['cost' => 4]);
        $user->forceFill(['password' => $oldHash, 'is_admin' => true])->save();
        config(['hashing.bcrypt.rounds' => 5]);
        $this->app['hash']->forgetDrivers();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])->assertRedirect('/');
        $this->assertNotSame($oldHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('secret-password', $user->fresh()->password));
        $this->get('/admin')->assertOk();
        $this->get('/account')->assertOk();
    }

    public function test_sanctum_tokens_work_and_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('upgrade-check', ['*'], now()->addHour());
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token->plainTextToken])
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonMissingPath('password');
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();
        // Each real HTTP request gets a fresh guard; emulate that boundary.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
    }

    public function test_repeat_migrations_preserve_users_encrypted_settings_and_subscriptions(): void
    {
        $user = User::factory()->create();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $user->platformSubscriptions()->attach(\App\Models\Platform::first(), ['is_active' => true]);
        $settings = new NotificationMailSetting(['brevo_api_key' => 'fake-upgrade-key', 'brevo_enabled' => false]);
        $settings->id = 1; $settings->save();
        $before = [DB::table('users')->get()->toJson(), DB::table('notification_mail_settings')->get()->toJson(), DB::table('user_platform_subscriptions')->get()->toJson()];
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($before, [DB::table('users')->get()->toJson(), DB::table('notification_mail_settings')->get()->toJson(), DB::table('user_platform_subscriptions')->get()->toJson()]);
        $this->assertSame('fake-upgrade-key', $settings->fresh()->brevo_api_key);
    }

    public function test_legacy_sessions_keep_login_data_and_validation_messages(): void
    {
        $this->assertSame('php', config('session.serialization'));
        $handler = new ArraySessionHandler(120);
        $id = str_repeat('a', 40);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => 'Adresse invalide']));
        $handler->write($id, serialize(['login_web_test' => 42, '_token' => 'legacy-token', 'errors' => $errors]));
        $session = new Store('upgrade-session', $handler, $id, config('session.serialization'));
        $session->start();
        $this->assertSame(42, $session->get('login_web_test'));
        $this->assertSame('legacy-token', $session->token());
        $this->assertSame('Adresse invalide', $session->get('errors')->first('email'));
        $session->save();
        $next = new Store('upgrade-session', $handler, $id, config('session.serialization'));
        $next->start();
        $this->assertSame(42, $next->get('login_web_test'));
    }

    public function test_old_file_cache_payloads_remain_readable_without_restoring_objects(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));
        $dir = sys_get_temp_dir().'/vod-upgrade-cache-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        try {
            $store = new FileStore($files, $dir, null, config('cache.serializable_classes'));
            $payload = ['title' => 'Dune', 'providers' => [['slug' => 'netflix']], 'ttl' => 86400];
            // FileStore's expiration + serialized-array format also used by Laravel 10.
            $path = $store->path('legacy-api');
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, (time() + 60).serialize($payload));
            $this->assertSame($payload, $store->get('legacy-api'));
            $store->put('object', (object) ['unsafe' => true], 60);
            $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $store->get('object'));
        } finally { $files->deleteDirectory($dir); }
    }

    public function test_new_forgery_middleware_retains_token_checks_for_cross_site_writes(): void
    {
        $this->app->instance('env', 'local');
        Http::preventStrayRequests();
        $admin = User::factory()->create(); $admin->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($admin);
        $this->put('/admin/about', ['title' => 'Forged', 'body' => 'forged'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
        $this->assertDatabaseMissing('site_pages', ['title' => 'Forged']);
        $this->put('/admin/about', ['title' => 'Legitimate', 'body' => 'valid', '_token' => session()->token()], ['Sec-Fetch-Site' => 'cross-site'])->assertRedirect('/admin');
        $this->assertDatabaseHas('site_pages', ['title' => 'Legitimate']);
        Http::assertNothingSent();
    }
}
