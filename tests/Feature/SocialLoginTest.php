<?php

namespace Tests\Feature;

use App\Models\{SocialAccount, User};
use App\Services\SocialLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Password, Notification};
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (SocialLogin::PROVIDERS as $provider => $name) config(['services.'.$provider.'.enabled' => true,
            'services.'.$provider.'.client_id' => 'test-id', 'services.'.$provider.'.client_secret' => 'test-secret',
            'services.'.$provider.'.redirect' => 'http://localhost/auth/'.$provider.'/callback']);
        Http::preventStrayRequests();
        Notification::fake();
    }

    private function identity(string $provider = 'google', string $id = '123'): array
    {
        return ['provider' => $provider, 'provider_id' => $id, 'email' => 'new@example.test', 'email_verified' => $provider === 'google',
            'first_name' => 'Nouveau', 'last_name' => 'Membre', 'expires_at' => now()->addMinutes(10)->timestamp];
    }

    private function flow(string $provider = 'google', ?int $userId = null): array
    {
        return ['state' => 'test-state', 'oauth.flow' => ['provider' => $provider, 'user_id' => $userId, 'expires_at' => now()->addMinutes(10)->timestamp]];
    }

    private function finishCallback(array $identity, ?int $userId = null)
    {
        $this->mock(SocialLogin::class)->shouldReceive('identity')->once()->with($identity['provider'])->andReturn($identity);
        return $this->withSession($this->flow($identity['provider'], $userId))->get('/auth/'.$identity['provider'].'/callback?state=test-state&code=fake-code');
    }

    public function test_only_fully_configured_enabled_providers_are_visible(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continuer avec Google')->assertSee('Continuer avec Microsoft')->assertSee('Continuer avec Facebook');
        config(['services.google.enabled' => false, 'services.microsoft.client_secret' => null, 'services.facebook.enabled' => false]);
        $this->get('/register')->assertOk()->assertDontSee('data-oauth-provider', false);
        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/apple/redirect')->assertNotFound();
    }

    public function test_real_drivers_generate_state_and_pkce_on_supported_providers(): void
    {
        foreach (['google' => 'accounts.google.com', 'microsoft' => 'login.microsoftonline.com', 'facebook' => 'www.facebook.com'] as $provider => $host) {
            $response = $this->get('/auth/'.$provider.'/redirect')->assertRedirect()->assertHeader('Cache-Control', 'no-store, private');
            $url = $response->headers->get('Location');
            $this->assertSame($host, parse_url($url, PHP_URL_HOST));
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertNotEmpty($query['state']);
            $this->assertSame(session('state'), $query['state']);
            if ($provider !== 'facebook') $this->assertSame('S256', $query['code_challenge_method']);
        }
        Http::assertNothingSent();
    }

    public function test_invalid_expired_or_mixed_provider_states_never_contact_the_provider(): void
    {
        $this->mock(SocialLogin::class)->shouldNotReceive('identity');
        $this->get('/auth/google/callback?state=forged&code=secret')->assertRedirect('/login')->assertSessionHasErrors('oauth');
        $this->withSession($this->flow())->get('/auth/google/callback?state=forged&code=secret')->assertRedirect('/login');
        $expired = $this->flow(); $expired['oauth.flow']['expires_at'] = now()->subSecond()->timestamp;
        $this->withSession($expired)->get('/auth/google/callback?state=test-state&code=secret')->assertRedirect('/login');
        $this->withSession($this->flow('facebook'))->get('/auth/google/callback?state=test-state&code=secret')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_cancelled_consent_and_provider_errors_leave_no_partial_account(): void
    {
        $this->withSession($this->flow())->get('/auth/google/callback?state=test-state&error=access_denied')->assertRedirect('/login')->assertSessionHasErrors('oauth');
        $this->mock(SocialLogin::class)->shouldReceive('identity')->once()->andThrow(new \RuntimeException('private-token-secret'));
        $this->withSession($this->flow())->get('/auth/google/callback?state=test-state&code=secret')->assertRedirect('/login');
        $this->get('/login')->assertDontSee('private-token-secret');
        $this->assertSame(0, User::count()); $this->assertSame(0, SocialAccount::count());
    }

    public function test_verified_google_signup_requires_confirmation_and_does_not_opt_in_automatically(): void
    {
        $identity = $this->identity();
        $this->finishCallback($identity)->assertRedirect('/oauth/signup');
        $this->assertSame(0, User::count());
        $this->get('/oauth/signup')->assertOk()->assertSee('Nouveau')->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/oauth/signup', ['first_name' => 'Nouveau', 'last_name' => 'Membre', 'nickname' => 'Nouveau', 'email' => $identity['email'], 'is_admin' => 1])
            ->assertRedirect('/account');
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user); $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->has_local_password); $this->assertFalse($user->is_admin);
        $this->assertFalse($user->notify_opt_in); $this->assertFalse($user->notify_email); $this->assertFalse($user->notify_web);
        $this->assertFalse($user->directory_visible); $this->assertFalse($user->share_real_name);
        $this->assertSame('123', $user->socialAccounts->first()->provider_id);
        $this->assertNull(session('oauth.pending'));
        Notification::assertNothingSent();
    }

    public function test_signup_cannot_reassign_google_verified_email_or_use_an_existing_nickname(): void
    {
        User::factory()->create(['nickname' => 'DejaPris']);
        $this->withSession(['oauth.pending' => $this->identity()])->post('/oauth/signup', [
            'first_name' => 'Nouveau', 'last_name' => 'Membre', 'nickname' => 'dejapris', 'email' => 'someone@example.test',
        ])->assertSessionHasErrors(['email', 'nickname']);
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_provider_without_email_can_complete_with_unverified_address(): void
    {
        $identity = $this->identity('facebook'); $identity['email'] = '';
        $this->finishCallback($identity)->assertRedirect('/oauth/signup');
        $this->post('/oauth/signup', ['first_name' => 'Nouveau', 'last_name' => 'Membre', 'email' => 'chosen@example.test'])->assertRedirect('/account');
        $this->assertFalse(User::firstOrFail()->hasVerifiedEmail());
    }

    public function test_matching_email_never_auto_links_or_logs_into_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'new@example.test']);
        $this->finishCallback($this->identity())->assertRedirect('/login')->assertSessionHasErrors('oauth');
        $this->assertGuest(); $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertNull(session('oauth.pending'));
    }

    public function test_existing_social_identity_logs_into_original_account_even_after_email_changes(): void
    {
        $user = User::factory()->create(['email' => 'changed@example.test']);
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => '123']);
        $this->finishCallback($this->identity())->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('changed@example.test', $user->fresh()->email);
        $this->get('/auth/google/callback?state=test-state&code=fake-code')->assertRedirect('/account'); // single use
    }

    public function test_social_admin_login_still_requires_local_password_confirmation(): void
    {
        $user = User::factory()->create(); $user->forceFill(['is_admin' => true])->save();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => '123']);
        $this->finishCallback($this->identity())->assertRedirect('/');
        $this->get('/admin')->assertRedirect('/confirm-password');
    }

    public function test_linking_requires_local_confirmation_and_never_steals_another_identity(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create();
        $other->socialAccounts()->create(['provider' => 'google', 'provider_id' => '123']);
        $this->actingAs($user)->post('/account/connections/google')->assertRedirect('/confirm-password');
        $this->actingAsConfirmedAdmin($user);
        $this->finishCallback($this->identity(), $user->id)->assertRedirect('/account')->assertSessionHasErrors('oauth');
        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertSame(1, $other->socialAccounts()->count());
    }

    public function test_linking_to_authenticated_user_preserves_email_and_preferences(): void
    {
        $user = User::factory()->create(['email' => 'local@example.test', 'notify_email' => true]);
        $this->actingAsConfirmedAdmin($user);
        $this->finishCallback($this->identity(), $user->id)->assertRedirect('/account')->assertSessionHasNoErrors();
        $this->assertSame(1, $user->socialAccounts()->count());
        $this->assertSame('local@example.test', $user->fresh()->email); $this->assertTrue($user->fresh()->notify_email);
    }

    public function test_user_switch_during_flow_does_not_link_identity(): void
    {
        $first = User::factory()->create(); $second = User::factory()->create();
        $this->mock(SocialLogin::class)->shouldNotReceive('identity');
        $this->actingAs($second)->withSession($this->flow('google', $first->id))->get('/auth/google/callback?state=test-state&code=fake-code')->assertRedirect('/account');
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_cannot_unlink_last_social_login_without_a_local_password(): void
    {
        $user = User::factory()->create(); $user->forceFill(['has_local_password' => false])->save();
        $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => '123']);
        $this->actingAsConfirmedAdmin($user)->delete('/account/connections/google')->assertRedirect('/account');
        $this->assertSame(1, $user->socialAccounts()->count());
        $user->forceFill(['has_local_password' => true])->save();
        $this->delete('/account/connections/google')->assertRedirect('/account');
        $this->assertSame(0, $user->socialAccounts()->count());
    }

    public function test_password_reset_enables_local_authentication_for_social_only_account(): void
    {
        $user = User::factory()->create(); $user->forceFill(['has_local_password' => false])->save();
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'new-secret-password', 'password_confirmation' => 'new-secret-password'])->assertRedirect('/login');
        $this->assertTrue($user->fresh()->has_local_password);
    }

    public function test_expired_registration_proof_cannot_create_account(): void
    {
        $identity = $this->identity(); $identity['expires_at'] = now()->subSecond()->timestamp;
        $this->withSession(['oauth.pending' => $identity])->post('/oauth/signup', ['first_name' => 'Forged'])->assertRedirect('/login');
        $this->assertSame(0, User::count());
    }
}
