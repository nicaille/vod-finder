<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\NotificationMailSetting;
use App\Services\NotificationEmailVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace(['first_name' => 'Test', 'last_name' => 'User', 'email' => 'new@example.test',
            'password' => 'password', 'password_confirmation' => 'password'], $overrides);
    }

    public function test_duplicate_nickname_is_rejected_even_with_different_case(): void
    {
        User::factory()->create(['nickname' => 'Maverick']);
        $this->post('/register', $this->payload(['nickname' => 'maverick']))->assertSessionHasErrors('nickname');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    public function test_account_can_keep_its_nickname_but_cannot_take_another_members_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'Maverick']);
        User::factory()->create(['nickname' => 'Goose']);
        $data = ['first_name' => 'Test', 'last_name' => 'User', 'email' => $user->email, 'nickname' => 'Maverick'];
        $this->actingAs($user)->put('/account', $data)->assertSessionHasNoErrors();
        $this->put('/account', array_replace($data, ['nickname' => 'goose']))->assertSessionHasErrors('nickname');
        $this->assertSame('Maverick', $user->fresh()->nickname);
    }

    public function test_web_choice_is_saved_but_requires_device_permission(): void
    {
        Notification::fake();
        $this->post('/register', $this->payload(['notify_opt_in' => 1, 'notify_web' => 1]))->assertRedirect('/account#notifications');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue($user->notify_opt_in);
        $this->assertTrue($user->notify_web);
        $this->assertFalse($user->notify_email);
        $this->assertSame(0, $user->pushSubscriptions()->count());
        Notification::assertNothingSent();
    }

    public function test_global_opt_out_prevents_sending_even_if_email_channel_is_selected(): void
    {
        $this->mock(NotificationEmailVerification::class, fn ($mock) => $mock->shouldNotReceive('send'));
        $this->post('/register', $this->payload(['notify_opt_in' => 0, 'notify_email' => 1]))->assertRedirect(RouteServiceProvider::HOME);
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'notify_opt_in' => false, 'notify_email' => true]);
    }

    public function test_email_choice_sends_confirmation_through_brevo_without_verifying_automatically(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'test'], 201)]);
        $settings = new NotificationMailSetting(['brevo_enabled' => true, 'brevo_api_key' => 'private-test-key', 'sender_email' => 'sender@example.test', 'sender_name' => 'VOD Finder']);
        $settings->id = 1; $settings->save();
        $this->post('/register', $this->payload(['notify_opt_in' => 1, 'notify_email' => 1]))->assertRedirect('/account#notifications');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue($user->notify_email);
        $this->assertFalse($user->hasVerifiedEmail());
        Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/smtp/email' && $request['to'][0]['email'] === $user->email && str_contains($request['htmlContent'], 'signature='));
        Http::assertSentCount(1);
    }

    public function test_confirmation_failure_does_not_prevent_account_creation(): void
    {
        $this->mock(NotificationEmailVerification::class, fn ($mock) => $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Unavailable')));
        $this->post('/register', $this->payload(['notify_opt_in' => 1, 'notify_email' => 1]))->assertRedirect('/account#notifications')->assertSessionHas('status', fn ($value) => str_contains($value, 'n’a pas pu être envoyé'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.test', 'email_verified_at' => null]);
    }

    public function test_global_opt_out_prevents_confirmation_and_channels_default_to_off(): void
    {
        Notification::fake();
        $this->post('/register', $this->payload(['notify_opt_in' => 0]))->assertRedirect(RouteServiceProvider::HOME);
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertFalse($user->notify_opt_in);
        $this->assertFalse($user->notify_email);
        $this->assertFalse($user->notify_web);
        Notification::assertNothingSent();
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
