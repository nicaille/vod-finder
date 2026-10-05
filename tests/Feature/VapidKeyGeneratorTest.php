<?php

namespace Tests\Feature;

use App\Services\VapidKeyGenerator;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class VapidKeyGeneratorTest extends TestCase
{
    public function test_missing_default_configuration_falls_back_to_project_config_and_produces_a_valid_signature(): void
    {
        $generator = new class extends VapidKeyGenerator {
            public array $configs = [];

            protected function createKey(?string $config)
            {
                $this->configs[] = $config;
                return $config === null ? false : parent::createKey($config);
            }
        };
        $keys = $generator->generate();
        $this->assertSame([null, resource_path('openssl.cnf')], $generator->configs);
        $validated = VAPID::validate(['subject' => 'mailto:test@example.test', ...$keys]);
        $this->assertSame(65, strlen($validated['publicKey']));
        $this->assertSame(32, strlen($validated['privateKey']));

        $headers = VAPID::getVapidHeaders('https://fcm.googleapis.com', $validated['subject'], $validated['publicKey'], $validated['privateKey'], 'aes128gcm');
        preg_match('/t=([^,]+)/', $headers['Authorization'], $matches);
        $jwt = (new CompactSerializer())->unserialize($matches[1]);
        $encode = fn ($bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        $publicKey = new JWK(['kty' => 'EC', 'crv' => 'P-256', 'x' => $encode(substr($validated['publicKey'], 1, 32)), 'y' => $encode(substr($validated['publicKey'], 33, 32))]);
        $verifier = new JWSVerifier(new AlgorithmManager([new ES256()]));
        $this->assertTrue($verifier->verifyWithKey($jwt, $publicKey, 0));
    }

    public function test_generation_failure_returns_an_actionable_error_without_creating_a_file(): void
    {
        $generator = new class extends VapidKeyGenerator {
            protected function createKey(?string $config) { return false; }
        };
        $this->app->instance(VapidKeyGenerator::class, $generator);
        $path = sys_get_temp_dir().'/vod-push-failure-'.bin2hex(random_bytes(8)).'.json';
        config(['webpush.key_file' => $path]);
        $this->artisan('notifications:setup-push')->expectsOutputToContain('OpenSSL ne peut pas créer une clé P-256')->assertFailed();
        $this->assertFileDoesNotExist($path);
    }
}
