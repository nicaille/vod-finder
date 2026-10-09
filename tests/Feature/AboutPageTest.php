<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_about_page_has_initial_content_and_provider_credits(): void
    {
        $this->get('/about')->assertOk()->assertSee('À propos de VOD Finder')
            ->assertSee('Moins de temps à chercher')->assertSee('Movie of the Night')
            ->assertSee('https://www.movieofthenight.com/about/api')->assertSee('TMDb');
        $this->get('/')->assertOk()->assertSee(route('about.show'))->assertDontSee('Administration');
    }

    public function test_admin_editor_requires_explicit_privileges(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->put('/admin/about', ['title' => 'Changed', 'body' => 'Changed'])->assertForbidden();
        $this->assertSame('À propos de VOD Finder', SitePage::first()->title);
    }

    public function test_admin_can_publish_content_and_credits_cannot_be_removed_by_editing_body(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->get('/admin')->assertOk()->assertSee('Enregistrer et publier');
        $this->put('/admin/about', ['title' => 'Notre projet', 'body' => "## Bienvenue\n\n**Du cinéma** pour tous."])
            ->assertSessionHasNoErrors()->assertRedirect('/admin');
        $this->get('/about')->assertOk()->assertSee('Notre projet')->assertSee('<strong>Du cinéma</strong>', false)
            ->assertSee('Movie of the Night')->assertSee('Administration');
    }

    public function test_markdown_cannot_publish_scripts_or_unsafe_links(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->put('/admin/about', [
            'title' => '<img src=x onerror=alert(1)>',
            'body' => "<script>alert(1)</script>\n\n[bad](javascript:alert(1))\n\n## Texte visible",
        ])->assertSessionHasNoErrors();
        $this->get('/about')->assertOk()->assertSee('Texte visible')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)->assertDontSee('<img src=x onerror=', false);
    }

    public function test_empty_content_is_rejected_without_overwriting_the_page(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->put('/admin/about', ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
        $this->assertSame('À propos de VOD Finder', SitePage::first()->title);
    }

    public function test_admin_role_is_managed_explicitly_and_not_through_mass_assignment(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true])->save();
        $this->assertFalse($user->fresh()->is_admin);
        $this->artisan('app:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
        $this->artisan('app:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);
        $this->artisan('app:admin', ['email' => 'unknown@example.test'])->assertFailed();
    }
}
