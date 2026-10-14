<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Services\BrowserPushService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class BrowserPushTransportTest extends TestCase
{
    public function test_real_transport_sends_high_urgency_and_logs_only_safe_result_metadata(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(201), new Response(410), new Response(503),
            new \GuzzleHttp\Exception\ConnectException('private-network-detail', new \GuzzleHttp\Psr7\Request('POST', 'https://fcm.googleapis.com/private-endpoint')),
        ]));
        $handler->push(Middleware::history($history));
        $keys = VAPID::createVapidKeys();
        $service = new class($handler, $keys) extends BrowserPushService {
            public function __construct(private $handler, private array $testKeys) {}
            public function keys(): ?array { return $this->testKeys; }
            protected function transport(array $keys): WebPush
            {
                // Keep the actual production options; replace only outbound HTTP.
                $options = parent::transport($keys)->getDefaultOptions();
                return new WebPush(['VAPID' => ['subject' => 'mailto:tests@example.test'] + $keys], $options, 10, ['handler' => $this->handler]);
            }
        };
        $device = new PushSubscription(['endpoint' => 'https://fcm.googleapis.com/fcm/send/private-device-token', 'public_key' => $keys['publicKey'], 'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]);
        $device->id = 123;
        $payload = ['title' => 'Private title', 'body' => 'Private recommendation', 'tag' => 'social-42'];
        Log::spy();
        $this->assertTrue($service->send($device, $payload));
        $this->assertFalse($service->send($device, $payload));
        try {
            $service->send($device, $payload);
            $this->fail('A rejected push must trigger retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Push delivery failed.', $e->getMessage());
        }
        try {
            $service->send($device, $payload);
            $this->fail('A connection failure must trigger retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Push delivery failed.', $e->getMessage());
        }
        try {
            $service->send($device, ['body' => "\xB1", 'tag' => 'social-42']);
            $this->fail('Invalid JSON must fail without leaking the payload.');
        } catch (\JsonException $e) {
            $this->assertSame(JSON_ERROR_UTF8, $e->getCode());
        }
        $this->assertCount(4, $history);
        foreach ($history as $entry) {
            $this->assertSame('high', $entry['request']->getHeaderLine('Urgency'));
            $this->assertSame('86400', $entry['request']->getHeaderLine('TTL'));
        }
        foreach (['accepted' => 201, 'expired' => 410, 'rejected' => 503, 'transport_failed' => null] as $result => $status) {
            Log::shouldHaveReceived('info')->with('Web push result', \Mockery::on(function ($context) use ($result, $status) {
                $this->assertArrayNotHasKey('endpoint', $context);
                $this->assertArrayNotHasKey('payload', $context);
                $this->assertStringNotContainsString('private', json_encode($context));
                return $context['result'] === $result && $context['http_status'] === $status
                    && $context['tag'] === 'social-42' && isset($context['attempted_at'], $context['completed_at']);
            }))->once();
        }
        Log::shouldHaveReceived('warning')->with('Web push transport failed', \Mockery::on(fn ($context) =>
            $context['exception_class'] === \JsonException::class
                && !str_contains(json_encode($context), 'private')
        ))->once();
    }
}
