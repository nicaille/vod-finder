<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class CloudflareProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        parent::tearDown();
    }

    private function probe(string $peer, array $headers): array
    {
        $request = Request::create('http://app-vod.venoix.fr/account', server: ['REMOTE_ADDR' => $peer]);
        $request->headers->add($headers);
        $response = app(TrustProxies::class)->handle($request, fn ($r) => response()->json([
            'ip' => $r->ip(), 'secure' => $r->isSecure(), 'host' => $r->getHost(), 'port' => $r->getPort(), 'url' => $r->url(),
        ]));
        return $response->getData(true);
    }

    public function test_cloudflare_ipv4_restores_client_ip_and_https(): void
    {
        $actual = $this->probe('162.158.10.20', ['X-Forwarded-For' => '203.0.113.25', 'X-Forwarded-Proto' => 'https']);
        $this->assertSame('203.0.113.25', $actual['ip']);
        $this->assertTrue($actual['secure']);
        $this->assertSame('https://app-vod.venoix.fr/account', $actual['url']);
    }

    public function test_cloudflare_ipv6_is_supported(): void
    {
        $actual = $this->probe('2606:4700::1234', ['X-Forwarded-For' => '2001:db8::25', 'X-Forwarded-Proto' => 'https']);
        $this->assertSame('2001:db8::25', $actual['ip']);
        $this->assertTrue($actual['secure']);
    }

    public function test_direct_requests_cannot_spoof_their_ip_or_protocol_with_cloudflare_headers(): void
    {
        $actual = $this->probe('198.51.100.20', ['X-Forwarded-For' => '192.0.2.10', 'X-Forwarded-Proto' => 'https', 'CF-Connecting-IP' => '192.0.2.10']);
        $this->assertSame('198.51.100.20', $actual['ip']);
        $this->assertFalse($actual['secure']);
    }

    public function test_client_supplied_prefix_in_forwarded_chain_does_not_replace_actual_visitor(): void
    {
        $actual = $this->probe('104.16.10.20', ['X-Forwarded-For' => '192.0.2.10, 203.0.113.25', 'X-Forwarded-Proto' => 'https']);
        $this->assertSame('203.0.113.25', $actual['ip']);
    }

    public function test_forwarded_host_and_port_are_not_trusted_even_through_cloudflare(): void
    {
        $actual = $this->probe('162.158.10.20', ['X-Forwarded-For' => '203.0.113.25', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Port' => '4444']);
        $this->assertSame('app-vod.venoix.fr', $actual['host']);
        $this->assertSame(443, $actual['port']);
        $this->assertSame('https://app-vod.venoix.fr/account', $actual['url']);
    }
}
