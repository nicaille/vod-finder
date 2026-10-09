<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Http, Notification};
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    public function test_declared_ai_and_search_crawlers_are_denied_before_outbound_calls(): void
    {
        Http::preventStrayRequests();
        foreach (['GPTBot/1.0', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Googlebot', 'bingbot', 'CCBot', 'Bytespider', 'Meta-ExternalAgent'] as $agent) {
            $this->get('/search?q=Dune', ['User-Agent' => $agent])->assertForbidden()->assertHeader('X-Robots-Tag');
        }
        Http::assertNothingSent();
        $this->get('/login', ['User-Agent' => 'Mozilla/5.0 Firefox/157.0'])->assertOk();
        $this->get('/about', ['User-Agent' => 'curl/8.17.0'])->assertOk();
        $this->assertStringContainsString("User-agent: *\nDisallow: /", file_get_contents(public_path('robots.txt')));
    }

    public function test_admin_pages_require_a_password_even_for_an_authenticated_administrator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/logs')->assertRedirect('/confirm-password');
        $this->getJson('/admin/user-data')->assertStatus(423)->assertJsonPath('confirmation_url', route('password.confirm'));
        $this->put('/admin/about', ['title' => 'Unsafe', 'body' => 'Unsafe'])->assertRedirect('/confirm-password');
        $this->assertDatabaseMissing('site_pages', ['title' => 'Unsafe']);
        $this->post('/confirm-password', ['password' => 'password'])->assertSessionHasNoErrors()->assertRedirect('/admin');
        $this->get('/admin/logs')->assertOk();
    }

    public function test_normal_login_confirms_admin_access_but_confirmation_expires_after_thirty_minutes(): void
    {
        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/admin')->assertOk();
        $this->withSession(['auth.password_confirmed_at' => time() - 1800])->get('/admin')->assertRedirect('/confirm-password');
    }

    public function test_confirmation_cannot_be_reused_by_another_admin_or_after_password_changes(): void
    {
        $admin = $this->admin(); $other = $this->admin();
        $this->actingAsConfirmedAdmin($admin)->get('/admin')->assertOk();
        $this->actingAs($other)->get('/admin')->assertRedirect('/confirm-password');
        $this->actingAsConfirmedAdmin($admin);
        $admin->forceFill(['password' => 'a-different-secret'])->save();
        // auth.session independently rejects old sessions; the admin confirmation also binds the hash.
        $this->withSession(['password_hash_web' => $admin->getAuthPassword()])->get('/admin')->assertRedirect('/confirm-password');
    }

    public function test_revoked_admin_cannot_use_a_cached_user_or_confirmed_session(): void
    {
        $admin = $this->admin();
        $this->actingAsConfirmedAdmin($admin)->get('/admin')->assertOk();
        User::whereKey($admin->id)->update(['is_admin' => false]);
        $this->get('/admin/user-data')->assertForbidden();
        $this->put('/admin/about', ['title' => 'Unsafe', 'body' => 'Unsafe'])->assertForbidden();
    }

    public function test_optional_admin_ip_allowlist_uses_real_peer_ip_not_untrusted_forwarded_headers(): void
    {
        config(['security.admin_allowed_ips' => ['192.0.2.0/24']]);
        $this->actingAsConfirmedAdmin($this->admin());
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->get('/admin', ['X-Forwarded-For' => '192.0.2.10'])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/admin')->assertOk();
    }

    public function test_admin_and_error_responses_have_private_cache_and_strict_security_headers(): void
    {
        $response = $this->actingAsConfirmedAdmin($this->admin())->get('/admin');
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringContainsString("script-src 'self';", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $response->headers->get('Content-Security-Policy'));
        $this->get('/admin/user-data/99999')->assertNotFound()->assertHeader('X-Robots-Tag')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_production_rejects_host_spoofing_and_sets_hsts_only_for_https(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://app-vod.venoix.fr']);
        try {
            $this->get('https://app-vod.venoix.fr/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
            $this->get('http://app-vod.venoix.fr/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
            // Laravel intentionally converts untrusted-host errors into a generic 404.
            $this->get('https://evil.example/login')->assertNotFound();
        } finally {
            \Symfony\Component\HttpFoundation\Request::setTrustedHosts([]);
        }
    }

    public function test_search_rate_limit_is_shared_with_popups_and_cannot_be_bypassed_by_query_rotation(): void
    {
        Http::preventStrayRequests();
        Cache::flush();
        for ($i = 0; $i < 20; $i++) $this->getJson('/search?q=')->assertOk();
        $this->getJson('/search?q=another-title')->assertStatus(429)->assertHeader('Retry-After');
        $this->get('/title/movie/1', ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(429);
        Http::assertNothingSent();
    }

    public function test_login_ip_budget_prevents_email_rotation_and_confirmation_attempts_are_limited(): void
    {
        Cache::flush();
        for ($i = 0; $i < 20; $i++) $this->post('/login', ['email' => "absent-$i@example.test", 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'different@example.test', 'password' => 'incorrect'])->assertStatus(429);
        Cache::flush();
        $this->actingAs($this->admin());
        for ($i = 0; $i < 5; $i++) $this->post('/confirm-password', ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->post('/confirm-password', ['password' => 'password'])->assertStatus(429);
        $this->get('/admin')->assertRedirect('/confirm-password');
    }

    public function test_malformed_or_unbounded_queries_are_rejected_without_calling_any_api(): void
    {
        Http::preventStrayRequests();
        foreach (['q[]=Dune', 'q='.str_repeat('a', 201), 'person_id=1|2|3|4|5|6', 'person_ids[]=1&person_ids[]=2&person_ids[]=3&person_ids[]=4&person_ids[]=5&person_ids[]=6', 'country[]=FR'] as $query) {
            $this->getJson('/search?'.$query)->assertUnprocessable();
        }
        $this->getJson('/autocomplete?q[]=Dune')->assertUnprocessable();
        $this->postJson('/login', ['email' => ['invalid'], 'password' => 'invalid'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_email_changes_require_the_current_password_on_both_profile_endpoints(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => 'Member', 'email' => 'attacker@example.test'])->assertSessionHasErrors('current_password');
        $data = ['first_name' => 'Member', 'last_name' => 'Example', 'email' => 'attacker@example.test'];
        $this->put('/account', $data)->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->put('/account', $data + ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('attacker@example.test', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_reset_link_response_does_not_reveal_whether_a_valid_email_is_registered(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHasNoErrors()->assertSessionHas('status', $message);
    }

    public function test_crlf_email_addresses_are_rejected_and_the_last_admin_cannot_delete_their_account(): void
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => "victim@example.test\r\nBcc: attacker@example.test"])->assertSessionHasErrors('email');
        Notification::assertNothingSent();
        $admin = $this->admin();
        $this->actingAs($admin)->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($admin->fresh());
        $target = User::factory()->unverified()->create();
        $this->actingAsConfirmedAdmin($admin)->post('/admin/users', ['email' => $target->email])->assertSessionHasErrors('email');
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_a_changed_password_invalidates_an_existing_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk();
        User::whereKey($user->id)->update(['password' => \Illuminate\Support\Facades\Hash::make('new-secret-password')]);
        \Illuminate\Support\Facades\Auth::setUser($user->fresh());
        $this->get('/account')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_confirmed_admin_writes_still_require_a_csrf_token(): void
    {
        $this->app->instance('env', 'local'); // Enable CSRF checks normally skipped by Laravel's test environment.
        $this->actingAsConfirmedAdmin($this->admin());
        $data = ['title' => 'CSRF attempt', 'body' => 'A cross-site write'];
        $this->put('/admin/about', $data)->assertStatus(419)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseMissing('site_pages', ['title' => 'CSRF attempt']);
        $token = session()->token();
        $this->put('/admin/about', $data + ['_token' => $token])->assertRedirect('/admin')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_pages', ['title' => 'CSRF attempt']);
    }

    public function test_admin_audit_identifies_the_actor_and_target_without_recording_private_input(): void
    {
        $admin = $this->admin(); $target = User::factory()->create();
        $logger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        \Illuminate\Support\Facades\Log::shouldReceive('channel')->with('security')->andReturn($logger);
        $logger->shouldReceive('info')->once()->with('admin.request', \Mockery::on(function ($context) use ($admin, $target) {
            return $context === ['actor_id' => $admin->id, 'route' => 'admin.user-data.show', 'method' => 'GET', 'status' => 200, 'target_user_id' => $target->id];
        }));
        $this->actingAsConfirmedAdmin($admin)->get('/admin/user-data/'.$target->id.'?private_input=never-record-this')->assertOk();
    }

    public function test_signup_and_password_recovery_have_limits_before_creating_accounts_or_sending_mail(): void
    {
        Cache::flush(); Notification::fake();
        for ($i = 0; $i < 3; $i++) $this->postJson('/register', [])->assertUnprocessable();
        $this->postJson('/register', [])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) $this->postJson('/forgot-password', [])->assertUnprocessable();
        $this->postJson('/forgot-password', [])->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }
}
