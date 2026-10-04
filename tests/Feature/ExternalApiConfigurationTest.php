<?php

namespace Tests\Feature;

use App\Support\ExternalApiClient;
use Composer\CaBundle\CaBundle;
use DateTimeImmutable;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class ExternalApiConfigurationTest extends TestCase
{
    private function certificateFailure(): RequestException
    {
        $url = 'https://api.themoviedb.org/3/search/multi?api_key=secret-test-key';
        return new RequestException('cURL error 60 for '.$url, new Request('GET', $url), null, null, ['errno' => 60]);
    }

    public function test_external_client_uses_a_readable_trust_store_and_keeps_tls_verification(): void
    {
        $verify = ExternalApiClient::make()->getOptions()['verify'];
        $this->assertIsString($verify);
        $this->assertTrue(is_readable($verify));
        $this->assertTrue(is_dir($verify) || CaBundle::validateCaFile($verify));
    }

    public function test_fallback_bundle_is_available_and_contains_valid_certificates(): void
    {
        $bundle = CaBundle::getBundledCaBundlePath();
        $this->assertFileExists($bundle);
        $this->assertTrue(CaBundle::validateCaFile($bundle));
    }

    public function test_http_failure_context_preserves_diagnosis_without_api_keys(): void
    {
        $exception = new ConnectionException('Connection failed with secret-test-key', 0, $this->certificateFailure());
        $context = ExternalApiClient::failureContext($exception);
        $this->assertSame(60, $context['curl_errno']);
        $this->assertSame(ConnectionException::class, $context['exception_class']);
        $this->assertStringNotContainsString('secret-test-key', json_encode($context));
        $this->assertStringNotContainsString('api_key', json_encode($context));
    }

    public function test_autocomplete_logs_certificate_failure_without_request_secrets(): void
    {
        config(['services.tmdb.key' => 'secret-test-key']);
        Http::preventStrayRequests();
        Http::fake(function () {throw $this->certificateFailure();});
        Log::shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return $message === 'Autocomplete error'
                && $context['curl_errno'] === 60
                && !str_contains(json_encode($context), 'secret-test-key');
        });
        $this->getJson('/autocomplete?q=Matrix')->assertStatus(500)->assertJsonPath('error', true);
    }

    public function test_file_loggers_use_paris_time_and_database_timezone_stays_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        foreach (['stack', 'single', 'daily'] as $channel) {
            $this->assertContains(\App\Logging\UseParisTimezone::class, config('logging.channels.'.$channel.'.tap'));
        }

        $path = tempnam(sys_get_temp_dir(), 'vod-log-test-');
        config(['logging.channels.single.path' => $path]);
        Log::forgetChannel('single');
        $logger = Log::channel('single');
        try {
            $handler = new TestHandler();
            $logger->getLogger()->pushHandler($handler);
            $logger->info('Timezone test');
            $record = $handler->getRecords()[0];
            $this->assertSame('Europe/Paris', $record->datetime->getTimezone()->getName());
            $zone = $logger->getLogger()->getTimezone();
            $this->assertSame(3600, $zone->getOffset(new DateTimeImmutable('2026-01-01T12:00:00Z')));
            $this->assertSame(7200, $zone->getOffset(new DateTimeImmutable('2026-07-01T12:00:00Z')));
        } finally {
            $logger->getLogger()->close();
            unlink($path);
        }
    }
}
