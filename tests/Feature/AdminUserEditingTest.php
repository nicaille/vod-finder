<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Log, Mail};
use Tests\TestCase;

class AdminUserEditingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    private function data(User $user, array $overrides = []): array
    {
        return array_replace(['first_name' => 'Camille', 'last_name' => 'Martin', 'nickname' => 'camille-support', 'email' => $user->email], $overrides);
    }

    public function test_edit_and_update_require_recently_confirmed_admin_privileges(): void
    {
        $target = User::factory()->create();
        $url = '/admin/user-data/'.$target->id;
        $this->get($url.'/edit')->assertRedirect('/login');
        $this->patch($url, $this->data($target))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url.'/edit')->assertForbidden();
        $this->patchJson($url, $this->data($target))->assertForbidden();
        $this->actingAs($this->admin())->patchJson($url, $this->data($target))->assertStatus(423);
        $this->assertSame($target->name, $target->fresh()->name);
    }

    public function test_editor_links_and_device_information_do_not_expose_secrets(): void
    {
        $target = User::factory()->create();
        $target->forceFill(['contact_token' => str_repeat('x', 48)])->save();
        $url = '/admin/user-data/'.$target->id;
        $this->actingAsConfirmedAdmin($this->admin())->get($url)->assertOk()->assertSee($url.'/edit');
        $this->get($url.'/edit')->assertOk()->assertSee('Modifier le compte')->assertSee('0 appareil(s)')
            ->assertSee('L’autorisation du navigateur reste à accorder sur chaque appareil.')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee($target->password)->assertDontSee($target->contact_token);
    }

    public function test_profile_update_preserves_preferences_credentials_roles_and_user_contents(): void
    {
        $target = User::factory()->create(['notify_opt_in' => true, 'notify_web' => true, 'directory_visible' => true]);
        $target->watchlist()->create(['type' => 'movie', 'tmdb_id' => 42, 'title' => 'Titre conservé']);
        $before = $target->fresh()->getAttributes();
        $this->actingAsConfirmedAdmin($this->admin())->patch('/admin/user-data/'.$target->id, $this->data($target, ['first_name' => '  Camille  ', 'email' => strtoupper($target->email)]))
            ->assertRedirect('/admin/user-data/'.$target->id)->assertSessionHasNoErrors();
        $fresh = $target->fresh();
        $this->assertSame('Camille Martin', $fresh->name);
        $this->assertSame('Camille', $fresh->first_name);
        $this->assertSame('camille-support', $fresh->nickname);
        foreach (['password', 'remember_token', 'is_admin', 'email_verified_at', 'notify_opt_in', 'notify_web', 'directory_visible'] as $field) {
            $this->assertSame($before[$field], $fresh->getAttributes()[$field]);
        }
        $this->assertSame(1, $fresh->watchlist()->count());
    }

    public function test_email_change_revokes_verification_and_reset_links_without_sending_mail(): void
    {
        Http::preventStrayRequests(); Mail::fake();
        $target = $this->admin();
        $target->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'existing-google-id']);
        $password = $target->password;
        $remember = $target->remember_token;
        DB::table('password_resets')->insert(['email' => $target->email, 'token' => 'old-reset-link', 'created_at' => now()]);
        $this->actingAsConfirmedAdmin($this->admin())->patch('/admin/user-data/'.$target->id, $this->data($target, ['email' => 'NEW-ADDRESS@example.test']))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', fn ($message) => str_contains($message, 'doit être confirmée'));
        $fresh = $target->fresh();
        $this->assertSame('new-address@example.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertNotSame($remember, $fresh->remember_token);
        $this->assertSame($password, $fresh->password);
        $this->assertTrue($fresh->is_admin);
        $this->assertSame('existing-google-id', $fresh->socialAccounts()->first()->provider_id);
        $this->assertDatabaseMissing('password_resets', ['email' => $target->email]);
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_duplicate_names_emails_invalid_flags_and_sensitive_fields_are_rejected(): void
    {
        $target = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.test', 'nickname' => 'AlreadyUsed']);
        $before = $target->fresh()->getAttributes();
        $this->actingAsConfirmedAdmin($this->admin());
        foreach ([
            [['email' => ' TAKEN@example.test '], 'email'],
            [['nickname' => 'alreadyused'], 'nickname'],
            [['first_name' => '   '], 'first_name'],
            [['notify_web' => 2], 'notify_web'],
            [['is_admin' => true], 'is_admin'],
            [['password' => 'new-password'], 'password'],
            [['email_verified_at' => now()->toIso8601String()], 'email_verified_at'],
        ] as [$values, $field]) {
            $this->patchJson('/admin/user-data/'.$target->id, $this->data($target, $values))->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertSame($before, $target->fresh()->getAttributes());
        }
    }

    public function test_admin_preferences_do_not_grant_device_permissions_or_send_messages(): void
    {
        Http::preventStrayRequests(); Mail::fake();
        $target = User::factory()->create(['notify_opt_in' => false, 'notify_email' => false, 'notify_web' => false]);
        $flags = array_fill_keys(['notify_opt_in', 'notify_email', 'notify_web', 'notify_platform_updates', 'directory_visible', 'share_real_name'], 1);
        $this->actingAsConfirmedAdmin($this->admin())->patch('/admin/user-data/'.$target->id, $this->data($target, $flags))->assertRedirect()->assertSessionHasNoErrors();
        foreach ($flags as $field => $_) $this->assertTrue($target->fresh()->{$field});
        $this->assertSame(0, $target->pushSubscriptions()->count());
        $this->patch('/admin/user-data/'.$target->id, $this->data($target, array_fill_keys(array_keys($flags), 0)))->assertRedirect();
        foreach ($flags as $field => $_) $this->assertFalse($target->fresh()->{$field});
        Mail::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_update_audit_contains_field_names_and_actor_without_private_values(): void
    {
        $admin = $this->admin(); $target = User::factory()->create();
        $logger = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        Log::shouldReceive('channel')->with('security')->andReturn($logger);
        $logger->shouldReceive('info')->once()->with('admin.user.updated', \Mockery::on(function ($context) use ($admin, $target) {
            $this->assertSame($admin->id, $context['actor_id']);
            $this->assertSame($target->id, $context['target_user_id']);
            $this->assertContains('first_name', $context['fields']);
            $this->assertStringNotContainsString('Camille', json_encode($context));
            $this->assertStringNotContainsString($target->email, json_encode($context));
            return array_keys($context) === ['actor_id', 'target_user_id', 'fields'];
        }));
        $logger->shouldReceive('info')->once()->with('admin.request', \Mockery::type('array'));
        $this->actingAsConfirmedAdmin($admin)->patch('/admin/user-data/'.$target->id, $this->data($target))->assertRedirect();
    }

    public function test_revoked_admin_cannot_edit_users(): void
    {
        $target = User::factory()->create(); $admin = $this->admin();
        $this->actingAsConfirmedAdmin($admin);
        User::whereKey($admin->id)->update(['is_admin' => false]);
        $this->patchJson('/admin/user-data/'.$target->id, $this->data($target))->assertForbidden();
        $this->assertSame($target->name, $target->fresh()->name);
    }
}
