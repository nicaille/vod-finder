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

    public function test_zero_default_bits_are_overridden_and_the_key_remains_p256(): void
    {
        $path = sys_get_temp_dir().'/vod-openssl-zero-'.bin2hex(random_bytes(8)).'.cnf';
        file_put_contents($path, "[req]\ndefault_bits = 0\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n");
        try {
            $generator = new class($path) extends VapidKeyGenerator {
                public array $details = [];

                public function __construct(private string $path) {}

                protected function createKey(?string $config)
                {
                    $key = parent::createKey($this->path);
                    if ($key !== false) $this->details = openssl_pkey_get_details($key);
                    return $key;
                }
            };
            $keys = $generator->generate();
            $this->assertSame(256, $generator->details['bits']);
            $this->assertSame('prime256v1', $generator->details['ec']['curve_name']);
            $this->assertSame(OPENSSL_KEYTYPE_EC, $generator->details['type']);
            $this->assertSame(87, strlen($keys['publicKey']));
        } finally { unlink($path); }
    }

    public function test_native_failure_returns_false_and_restores_the_existing_error_handler(): void
    {
        $generator = new class extends VapidKeyGenerator {
            public function attempt(string $config) { return parent::createKey($config); }
        };
        $handler = static function () { throw new \ErrorException('The previous error handler should not run during key generation.'); };
        set_error_handler($handler, E_WARNING);
        try {
            $result = $generator->attempt(sys_get_temp_dir().'/vod-missing-'.bin2hex(random_bytes(8)).'.cnf');
            $this->assertFalse($result);
            $restored = set_error_handler($handler, E_WARNING);
            restore_error_handler();
            $this->assertSame($handler, $restored);
        } finally { restore_error_handler(); }
    }
}
