<?php

namespace Tests\Feature;

use App\Services\SocialLogin;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Tests\TestCase;

class SocialProviderTest extends TestCase
{
    private function provider(string $class, array $responses, array &$history)
    {
        $request = Request::create('http://localhost/auth/google/callback?state=known-state&code=known-code');
        $session = $this->app['session']->driver();
        $session->put(['state' => 'known-state', 'code_verifier' => 'known-verifier']);
        $request->setLaravelSession($session);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        return (new $class($request, 'client', 'secret', 'http://localhost/callback'))->setHttpClient(new Client(['handler' => $stack]));
    }

    public function test_google_exchanges_code_with_pkce_and_extracts_only_verified_identity_fields(): void
    {
        $history = [];
        $driver = $this->provider(GoogleProvider::class, [
            new Response(200, [], json_encode(['access_token' => 'never-persist-access-token', 'refresh_token' => 'never-persist-refresh-token', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['sub' => 'google-id', 'email' => 'MAIL@example.test', 'email_verified' => true, 'given_name' => 'Given', 'family_name' => 'Family', 'name' => 'Given Family'])),
        ], $history)->enablePKCE();
        $service = \Mockery::mock(SocialLogin::class)->makePartial();
        $service->shouldReceive('driver')->once()->with('google')->andReturn($driver);
        $identity = $service->identity('google');
        $this->assertSame('google-id', $identity['provider_id']); $this->assertSame('mail@example.test', $identity['email']);
        $this->assertTrue($identity['email_verified']);
        $this->assertStringNotContainsString('never-persist', json_encode($identity));
        $this->assertCount(2, $history);
        parse_str((string) $history[0]['request']->getBody(), $body);
        $this->assertSame('known-code', $body['code']); $this->assertSame('known-verifier', $body['code_verifier']);
        $this->assertNull($this->app['session']->get('state'));
    }

    public function test_facebook_email_is_not_treated_as_verified_merely_because_profile_is_verified(): void
    {
        $history = [];
        $driver = $this->provider(FacebookProvider::class, [
            new Response(200, [], json_encode(['access_token' => 'never-persist-access-token', 'expires_in' => 3600])),
            new Response(200, [], json_encode(['id' => 'facebook-id', 'email' => 'mail@example.test', 'verified' => true, 'name' => 'Given Family'])),
        ], $history);
        $service = \Mockery::mock(SocialLogin::class)->makePartial();
        $service->shouldReceive('driver')->once()->andReturn($driver);
        $this->assertFalse($service->identity('facebook')['email_verified']);
    }

    public function test_socialite_itself_rejects_invalid_state_before_token_exchange(): void
    {
        $history = [];
        $driver = $this->provider(GoogleProvider::class, [], $history);
        $this->app['session']->put('state', 'different-state');
        try { $driver->user(); $this->fail('Invalid state was accepted.'); }
        catch (InvalidStateException) { $this->assertSame([], $history); }
    }
}
