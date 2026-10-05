<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private function platform(string $slug, int $position = 1): Platform
    {
        return Platform::create(['slug' => $slug, 'name' => ucfirst($slug), 'position' => $position]);
    }

    private function accountData(User $user): array
    {
        return ['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => $user->email];
    }

    private function fields(string $html, string $query): \DOMNodeList
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        return (new \DOMXPath($dom))->query($query);
    }

    public function test_forms_share_canonical_fields_and_order_platforms(): void
    {
        $last = $this->platform('zulu', 2);
        $beta = $this->platform('beta');
        $alpha = $this->platform('alpha');
        $user = User::factory()->create();
        foreach ([$this->get('/register'), $this->actingAs($user)->get('/account')] as $response) {
            $response->assertOk()->assertSee('Tout cocher')->assertSee('Tout décocher');
            $ids = [];
            foreach ($this->fields($response->getContent(), '//input[@name="platforms[]"]') as $node) {
                $ids[] = (int) $node->getAttribute('value');
            }
            $this->assertSame([$alpha->id, $beta->id, $last->id,
                Platform::where('slug', 'paramountplus')->value('id'),
                Platform::where('slug', 'hbomax')->value('id'),
            ], $ids);
            $this->assertSame(0, $this->fields($response->getContent(), '//select[@name="via['.$alpha->id.']"]/option[@value="'.$alpha->id.'"]')->length);
        }
    }

    public function test_registration_saves_canonical_pivot_and_notification_choices(): void
    {
        $apple = $this->platform('appletv');
        $canal = $this->platform('canalplus');
        $this->post('/register', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'nickname' => 'jean',
            'email' => 'jean@example.test', 'password' => 'password', 'password_confirmation' => 'password',
            'notify_opt_in' => '0', 'platforms' => [$apple->id],
            'via' => [$apple->id => $canal->id], 'notify' => [$apple->id => '0'],
        ])->assertSessionHasNoErrors()->assertRedirect('/');
        $user = User::where('email', 'jean@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->notify_opt_in);
        $this->assertDatabaseHas('user_platform_subscriptions', [
            'user_id' => $user->id, 'platform_id' => $apple->id,
            'subscribed_via_platform_id' => $canal->id, 'is_active' => 1, 'notify_opt_in' => 0,
        ]);
        $this->assertSame(1, $user->platformSubscriptions()->count());
        $this->assertSame($user->id, $apple->users()->firstOrFail()->id);
    }

    public function test_registration_restores_platform_details_after_validation_error(): void
    {
        $apple = $this->platform('appletv');
        $canal = $this->platform('canalplus');
        $this->from('/register')->post('/register', [
            'first_name' => 'Jean', 'email' => 'invalid', 'platforms' => [$apple->id],
            'via' => [$apple->id => $canal->id], 'notify' => [$apple->id => '0'],
        ])->assertSessionHasErrors(['email', 'last_name']);
        $html = $this->get('/register')->assertOk()->getContent();
        $this->assertSame(1, $this->fields($html, '//input[@name="platforms[]" and @value="'.$apple->id.'" and @checked]')->length);
        $this->assertSame(1, $this->fields($html, '//select[@name="via['.$apple->id.']"]/option[@value="'.$canal->id.'" and @selected]')->length);
        $this->assertSame(0, $this->fields($html, '//input[@type="checkbox" and @name="notify['.$apple->id.']" and @checked]')->length);
    }

    public function test_account_preserves_existing_values_and_applies_updates(): void
    {
        $apple = $this->platform('appletv');
        $canal = $this->platform('canalplus');
        $user = User::factory()->create(['nickname' => 'jean']);
        $user->platformSubscriptions()->attach($apple, ['is_active' => true, 'subscribed_via_platform_id' => $canal->id, 'notify_opt_in' => false]);
        $html = $this->actingAs($user)->get('/account')->assertOk()->getContent();
        $this->assertSame(1, $this->fields($html, '//input[@name="platforms[]" and @value="'.$apple->id.'" and @checked]')->length);
        $this->assertSame(1, $this->fields($html, '//select[@name="via['.$apple->id.']"]/option[@value="'.$canal->id.'" and @selected]')->length);
        $this->put('/account', $this->accountData($user) + [
            'nickname' => 'jean', 'platforms' => [$apple->id],
            'via' => [$apple->id => $apple->id], 'notify' => [$apple->id => '1'],
        ])->assertSessionHasNoErrors()->assertRedirect('/account');
        $this->assertDatabaseHas('user_platform_subscriptions', [
            'user_id' => $user->id, 'platform_id' => $apple->id,
            'subscribed_via_platform_id' => null, 'is_active' => 1, 'notify_opt_in' => 1,
        ]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unchecking_all_stays_unchecked_after_error_and_can_be_saved(): void
    {
        $netflix = $this->platform('netflix');
        $user = User::factory()->create();
        $user->platformSubscriptions()->attach($netflix, ['is_active' => true, 'notify_opt_in' => true]);
        $this->actingAs($user)->from('/account')->put('/account', ['first_name' => 'Jean', 'email' => 'invalid'])
            ->assertSessionHasErrors();
        $html = $this->get('/account')->assertOk()->getContent();
        $this->assertSame(0, $this->fields($html, '//input[@name="platforms[]" and @checked]')->length);
        $this->put('/account', $this->accountData($user))->assertSessionHasNoErrors();
        $this->assertSame(0, $user->platformSubscriptions()->count());
        $this->assertNull($user->fresh()->nickname);
    }

    public function test_invalid_platform_and_duplicate_nickname_leave_account_unchanged(): void
    {
        $user = User::factory()->create(['first_name' => 'Original']);
        User::factory()->create(['nickname' => 'taken']);
        $this->actingAs($user)->put('/account', $this->accountData($user) + [
            'nickname' => 'taken', 'platforms' => [99999],
        ])->assertSessionHasErrors(['nickname', 'platforms.0']);
        $this->assertSame('Original', $user->fresh()->first_name);
    }

    public function test_changed_email_requires_verification_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/account', [
            'first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'new@example.test',
        ])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_search_injects_only_active_subscriptions_and_guest_can_access_it(): void
    {
        $netflix = $this->platform('netflix');
        $disney = $this->platform('disneyplus');
        $user = User::factory()->create();
        $user->platformSubscriptions()->attach($netflix, ['is_active' => true, 'notify_opt_in' => false]);
        $user->platformSubscriptions()->attach($disney, ['is_active' => false, 'notify_opt_in' => true]);
        $this->get('/')->assertOk()->assertViewIs('search')->assertViewHas('defaultProviderSlugs', []);
        $this->actingAs($user)->get('/')->assertOk()->assertViewHas('defaultProviderSlugs', ['netflix']);
    }
}
