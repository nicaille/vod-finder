<?php

namespace Tests\Feature;

use App\Models\{NotificationMailSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialPasswordMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $setting = new NotificationMailSetting(['brevo_enabled' => true, 'brevo_api_key' => 'fake-key', 'sender_email' => 'vod@example.test', 'sender_name' => 'VOD Finder']);
        $setting->id = 1; $setting->save();
        Http::preventStrayRequests();
    }

    public function test_password_setup_uses_brevo_even_without_notification_opt_in(): void
    {
        $user = User::factory()->create(['notify_opt_in' => false, 'notify_email' => false]);
        $user->forceFill(['has_local_password' => false])->save();
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'fake-message'], 201)]);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['to'][0]['email'] === $user->email
            && str_contains($request['htmlContent'], '/reset-password/') && str_contains($request['textContent'], '/reset-password/'));
        $this->assertFalse($user->fresh()->has_local_password);
    }

    public function test_transport_error_and_unknown_address_keep_same_generic_response(): void
    {
        $user = User::factory()->create();
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response([], 503)]);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHasNoErrors();
        $message = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('status', $message);
        Http::assertSentCount(1);
    }
}
