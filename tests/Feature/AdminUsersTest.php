<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        return $user;
    }

    public function test_regular_users_cannot_view_or_assign_admin_rights(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', ['email' => $target->email])->assertForbidden();
        $this->delete('/admin/users/'.$target->id)->assertForbidden();
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_admin_can_grant_and_revoke_access_to_an_existing_account(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $this->actingAs($admin)->from('/admin/users')->post('/admin/users', ['email' => $target->email])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/users');
        $this->assertTrue($target->fresh()->is_admin);
        $this->actingAs($target->fresh())->get('/admin')->assertOk();
        $this->actingAs($admin)->delete('/admin/users/'.$target->id)->assertRedirect('/admin/users');
        $this->assertFalse($target->fresh()->is_admin);
        $this->actingAs($target->fresh())->get('/admin')->assertForbidden();
    }

    public function test_last_admin_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHasErrors('administrator');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_step_down_after_appointing_another_admin(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertRedirect('/about');
        $this->assertFalse($admin->fresh()->is_admin);
        $this->assertTrue($other->fresh()->is_admin);
    }

    public function test_missing_account_does_not_create_a_privileged_user(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/users', ['email' => 'unknown@example.test'])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.test']);
    }
}
