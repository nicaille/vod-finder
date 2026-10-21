<?php

namespace Tests\Feature;

use App\Services\OriginGuard;
use App\Services\AdminLogReader;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OriginGuardTest extends TestCase
{
    private string $directory;
    private OriginGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/vod-origin-unit-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->directory.'/storage/app/private', 0700, true);
        $this->guard = new OriginGuard($this->directory);
        $this->app->instance(OriginGuard::class, $this->guard);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_init_is_idempotent_and_does_not_install_a_block_or_print_the_secret(): void
    {
        $this->artisan('origin:guard', ['action' => 'init'])->expectsOutputToContain('Aucun blocage activé')->assertSuccessful();
        $token = $this->guard->token();
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $token);
        $this->guard->initialize();
        $this->assertSame($token, $this->guard->token());
        $this->assertFileDoesNotExist($this->guard->apachePath());
        $this->assertSame(0600, fileperms($this->guard->statePath()) & 0777);
        $this->assertArrayNotHasKey('token', $this->guard->status());
    }

    public function test_observe_enforce_and_off_preserve_the_key_and_keep_it_out_of_apache(): void
    {
        $this->guard->initialize();
        $token = $this->guard->token();
        $this->artisan('origin:guard', ['action' => 'observe'])->assertSuccessful();
        $this->assertStringNotContainsString($token, file_get_contents($this->guard->apachePath()));
        $this->assertStringNotContainsString('[F,END]', file_get_contents($this->guard->apachePath()));
        $this->assertTrue($this->guard->status()['apache_consistent']);
        $this->artisan('origin:guard', ['action' => 'enforce', '--allow-acme-files' => true])->assertSuccessful();
        $this->assertStringContainsString('[F,END]', file_get_contents($this->guard->apachePath()));
        $this->assertTrue($this->guard->status()['allow_acme_files']);
        $this->artisan('origin:guard', ['action' => 'off'])->assertSuccessful();
        $this->assertFileDoesNotExist($this->guard->apachePath());
        $this->assertSame($token, $this->guard->token());
    }

    public function test_unmanaged_apache_configuration_is_never_overwritten(): void
    {
        file_put_contents($this->guard->apachePath(), '# User configuration');
        $this->artisan('origin:guard', ['action' => 'init'])->assertFailed();
        $this->assertSame('# User configuration', file_get_contents($this->guard->apachePath()));
        $this->assertFileDoesNotExist($this->guard->statePath());
    }

    public function test_manual_edits_or_symbolic_links_cannot_redirect_writes(): void
    {
        $this->guard->initialize();
        $this->guard->setMode('observe');
        file_put_contents($this->guard->apachePath(), '# Changed manually');
        $this->artisan('origin:guard', ['action' => 'enforce'])->assertFailed();
        $this->assertSame('# Changed manually', file_get_contents($this->guard->apachePath()));
        unlink($this->guard->apachePath());
        $outside = $this->directory.'/outside.txt';
        file_put_contents($outside, 'Keep');
        symlink($outside, $this->guard->apachePath());
        $this->artisan('origin:guard', ['action' => 'off'])->assertFailed();
        $this->assertSame('Keep', file_get_contents($outside));
    }

    public function test_direct_enforcement_is_rejected_until_observation_is_installed(): void
    {
        $this->guard->initialize();
        $this->artisan('origin:guard', ['action' => 'enforce'])->assertFailed();
        $this->assertFileDoesNotExist($this->guard->apachePath());
        $this->artisan('origin:guard', ['action' => 'unknown'])->assertExitCode(2);
    }
    public function test_off_recovers_after_the_emergency_ssh_rollback(): void
    {
        $this->guard->initialize();
        $this->guard->setMode('observe');
        $this->guard->setMode('enforce');
        rename($this->guard->apachePath(), $this->guard->apachePath().'.origin-disabled-test');
        $this->assertFalse($this->guard->status()['apache_consistent']);
        $this->artisan('origin:guard', ['action' => 'off'])->assertSuccessful();
        $this->assertSame('off', $this->guard->status()['mode']);
        $this->assertTrue($this->guard->status()['apache_consistent']);
    }

    public function test_admin_logs_redact_the_private_key_and_header_values(): void
    {
        $this->guard->initialize();
        $path = storage_path('logs/origin-guard-test.log');
        try {
            file_put_contents($path, '[2026-10-22 00:00:00] testing.ERROR: '.$this->guard->token()."\n"
                .'X-VOD-Origin-Key: header-secret'."\n"
                .'{"X-VOD-Origin-Key":"json-secret","VOD_ORIGIN_TOKEN":"worker-secret"}');
            $text = app(AdminLogReader::class)->read(basename($path));
            foreach ([$this->guard->token(), 'header-secret', 'json-secret', 'worker-secret'] as $secret) {
                $this->assertStringNotContainsString($secret, $text);
            }
        } finally { if (is_file($path)) unlink($path); }
    }

}
