<?php

namespace Tests\Feature;

use App\Models\ApiHealth;
use App\Services\{AdminLogReader, ApiMonitor, ApiStatistics, StreamingAvailabilityClient, StreamingAvailabilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Http, Log};
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class StreamingAvailabilityConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.streaming_availability.key' => 'rapid-private-test',
            'services.streaming_availability.direct_key' => 'motn-key-private-test',
            'services.streaming_availability.base_url' => 'https://streaming-availability.p.rapidapi.com',
            'cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_quota_switches_to_direct_with_isolated_headers_and_separate_statistics(): void
    {
        Http::fake([
            'streaming-availability.p.rapidapi.com/*' => Http::response([], 429, ['Retry-After' => '3600']),
            'api.movieofthenight.com/v4/*' => Http::response(['streamingOptions' => []], 200, ['X-RateLimit-Requests-Remaining' => '999']),
        ]);
        $client = app(StreamingAvailabilityClient::class);
        $this->assertTrue($client->get('/shows/movie/42', ['source' => 'tmdb', 'country' => 'fr'])->successful());
        $this->assertTrue($client->get('/shows/tv/7', ['country' => 'fr'])->successful());
        Http::assertSentCount(3); // The exhausted account is not probed again.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'rapidapi.com/') && $r->hasHeader('X-RapidAPI-Key', 'rapid-private-test') && !$r->hasHeader('X-API-Key'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v4/shows/movie%2F42') && $r->hasHeader('X-API-Key', 'motn-key-private-test') && !$r->hasHeader('X-RapidAPI-Key') && !$r->hasHeader('X-RapidAPI-Host') && !isset($r['source']));
        $this->assertSame(1, ApiHealth::where('service', 'Streaming Availability')->firstOrFail()->requests);
        $this->assertSame(2, ApiHealth::where('service', StreamingAvailabilityClient::DIRECT_SERVICE)->firstOrFail()->requests);
        $this->assertSame(3, app(ApiMonitor::class)->calls('Streaming Availability'));
        $this->assertSame(2, app(ApiMonitor::class)->calls(StreamingAvailabilityClient::DIRECT_SERVICE));
        $this->assertSame(1, app(ApiStatistics::class)->streamingMonthlyBudget()['used']);
        $this->assertSame(2, app(ApiStatistics::class)->streamingMonthlyBudget(StreamingAvailabilityClient::DIRECT_SERVICE)['used']);
    }

    public function test_last_allowed_call_is_kept_then_zero_remaining_switches_accounts(): void
    {
        Http::fake([
            'streaming-availability.p.rapidapi.com/*' => Http::response(['id' => '42'], 200, ['X-RateLimit-Requests-Remaining' => '0', 'Retry-After' => '86400']),
            'api.movieofthenight.com/v4/*' => Http::response(['id' => '43']),
        ]);
        $client = app(StreamingAvailabilityClient::class);
        $this->assertSame('42', $client->get('/shows/movie/42')->json('id'));
        $this->assertSame('43', $client->get('/shows/movie/43')->json('id'));
        Http::assertSentCount(2);
    }

    public static function failures(): array
    {
        return [[401], [403], [429], [500], [503]];
    }

    #[DataProvider('failures')]
    public function test_retriable_status_uses_the_other_account(int $status): void
    {
        Http::fake([
            'streaming-availability.p.rapidapi.com/*' => Http::response([], $status),
            'api.movieofthenight.com/v4/*' => Http::response(['id' => '42']),
        ]);
        $this->assertTrue(app(StreamingAvailabilityClient::class)->get('/shows/movie/42')->successful());
        Http::assertSentCount(2);
    }

    public function test_changing_accounts_does_not_discard_shared_cached_results(): void
    {
        Http::fake(['streaming-availability.p.rapidapi.com/*' => Http::response(['id' => '42', 'streamingOptions' => []])]);
        $service = app(StreamingAvailabilityService::class);
        $this->assertSame('42', $service->getShowWithSeasonsFromTmdbId(42)['id']);
        config(['services.streaming_availability.key' => '']);
        $this->assertSame('42', $service->getShowWithSeasonsFromTmdbId(42)['id']);
        Http::assertSentCount(1);
    }

    public function test_success_does_not_spend_the_second_account_and_404_does_not_trigger_failover(): void
    {
        Http::fake(['streaming-availability.p.rapidapi.com/*' => Http::sequence()->push(['id' => 42])->push([], 404)]);
        $client = app(StreamingAvailabilityClient::class);
        $this->assertTrue($client->get('/shows/movie/42')->successful());
        $this->assertSame(404, $client->get('/shows/movie/99')->status());
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'movieofthenight.com'));
    }

    public function test_direct_only_uses_v4_and_the_shared_availability_cache(): void
    {
        config(['services.streaming_availability.key' => '']);
        Http::fake([
            'api.movieofthenight.com/v4/countries/fr' => Http::response(['services' => []]),
            'api.movieofthenight.com/v4/shows/*' => Http::response(['id' => '7', 'streamingOptions' => ['fr' => []], 'seasons' => []]),
        ]);
        $service = app(StreamingAvailabilityService::class);
        $this->assertTrue($service->isEnabled());
        $this->assertNotNull($service->getShowWithSeasonsFromTmdbId(7, 'tv'));
        $this->assertNotNull($service->getShowWithSeasonsFromTmdbId(7, 'tv'));
        $this->assertNotNull($service->getAvailabilityBundle(7, 'tv', 'FR'));
        Http::assertSentCount(2); // Same show reused, only the catalogue is loaded separately.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v4/shows/tv%2F7') && $r['country'] === 'fr');
    }

    public function test_connection_failure_switches_accounts_without_leaking_credentials(): void
    {
        Log::spy();
        Http::fake([
            'streaming-availability.p.rapidapi.com/*' => Http::failedConnection('private-error-url'),
            'api.movieofthenight.com/v4/*' => Http::response(['id' => '42']),
        ]);
        $this->assertTrue(app(StreamingAvailabilityClient::class)->get('/shows/movie/42')->successful());
        Log::shouldHaveReceived('warning')->with('Streaming Availability connection failed', \Mockery::on(fn ($context) =>
            $context['connection'] === 'rapidapi' && !str_contains(json_encode($context), 'private')
        ))->once();
    }

    public function test_both_failures_back_off_and_retry_after_expiry(): void
    {
        $this->freezeTime();
        Http::fake([
            'streaming-availability.p.rapidapi.com/*' => Http::response([], 429, ['Retry-After' => now()->addMinutes(2)->utc()->toRfc7231String()]),
            'api.movieofthenight.com/v4/*' => Http::sequence()->push([], 503)->push(['id' => '42']),
        ]);
        $client = app(StreamingAvailabilityClient::class);
        $this->assertSame(503, $client->get('/shows/movie/42')->status());
        $this->assertNull($client->get('/shows/movie/43'));
        Http::assertSentCount(2);
        $this->travel(31)->seconds();
        $this->assertTrue($client->get('/shows/movie/43')->successful());
        Http::assertSentCount(3);
    }

    public function test_rotating_a_configured_key_does_not_reuse_old_cooldown_or_active_account(): void
    {
        Http::fake(fn ($r) => str_contains($r->url(), 'rapidapi.com') && $r->hasHeader('X-RapidAPI-Key', 'rapid-private-test') ? Http::response([], 429) : Http::response(['id' => '42']));
        $client = app(StreamingAvailabilityClient::class);
        $this->assertTrue($client->get('/shows/movie/42')->successful());
        config(['services.streaming_availability.key' => 'replacement-private-test']);
        $this->assertTrue($client->get('/shows/movie/43')->successful());
        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => $r->hasHeader('X-RapidAPI-Key', 'replacement-private-test'));
    }

    public function test_redirects_and_arbitrary_hosts_cannot_receive_credentials(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);
            return Http::response('', 302, ['Location' => 'https://untrusted.example/private']);
        });
        $this->assertSame(302, app(StreamingAvailabilityClient::class)->get('/shows/movie/42')->status());
        Http::assertSentCount(1);
        config(['services.streaming_availability.base_url' => 'https://untrusted.example']);
        $this->expectException(\InvalidArgumentException::class);
        app(StreamingAvailabilityClient::class)->get('/shows/movie/42');
    }

    public function test_missing_keys_disable_external_requests_and_admin_logs_mask_the_direct_key(): void
    {
        $file = 'zz-direct-test-'.bin2hex(random_bytes(8)).'.log';
        $path = storage_path('logs/'.$file);
        file_put_contents($path, 'Direct credential: motn-key-private-test');
        try {
            $this->assertStringNotContainsString('motn-key-private-test', app(AdminLogReader::class)->read($file));
        } finally { unlink($path); }
        config(['services.streaming_availability.key' => '', 'services.streaming_availability.direct_key' => '']);
        $this->assertFalse(app(StreamingAvailabilityService::class)->isEnabled());
        $this->assertNull(app(StreamingAvailabilityClient::class)->get('/shows/movie/42'));
        Http::assertNothingSent();
    }
}
