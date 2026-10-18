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
    public function test_legal_sections_are_public_and_have_stable_links(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('id="conditions-utilisation"', false)
            ->assertSee('id="confidentialite"', false)
            ->assertSee('Conditions d’utilisation de l’application')
            ->assertSee('Règles de confidentialité de l’application')
            ->assertSee('vod-finder@venoix.fr')->assertSee('CNIL')
            ->assertSee('href="/profile"', false)->assertSee('Google, Microsoft ou Facebook');
    }

    public function test_admin_can_edit_legal_sections_without_publishing_unsafe_html(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->get('/admin')->assertOk()
            ->assertSee('name="terms_body"', false)->assertSee('name="privacy_body"', false);
        $this->put('/admin/about', [
            'title' => 'À propos', 'body' => 'Présentation conservée',
            'terms_body' => "### Conditions révisées\n\n<script>alert(42)</script>",
            'privacy_body' => 'Contact confidentiel [piège](javascript:alert(42))',
        ])->assertRedirect('/admin')->assertSessionHasNoErrors();
        $this->get('/about')->assertOk()->assertSee('Conditions révisées')
            ->assertSee('Présentation conservée')->assertSee('Contact confidentiel')
            ->assertDontSee('<script>alert(42)</script>', false)
            ->assertDontSee('href="javascript:', false);
        $this->put('/admin/about', [
            'title' => 'À propos', 'body' => 'Présentation',
            'terms_body' => '', 'privacy_body' => str_repeat('a', 20001),
        ])->assertSessionHasErrors(['terms_body', 'privacy_body']);
        $this->assertStringContainsString('Conditions révisées', SitePage::first()->terms_body);
    }

    public function test_legal_migration_preserves_existing_editorial_content_and_accounts(): void
    {
        $migration = require database_path('migrations/2026_10_18_010000_add_about_legal_sections.php');
        $migration->down();
        SitePage::where('slug', 'about')->update(['title' => 'Titre personnalisé', 'body' => 'Notre texte existant']);
        $user = User::factory()->create();
        $migration->up();
        $page = SitePage::where('slug', 'about')->firstOrFail();
        $this->assertSame('Titre personnalisé', $page->title);
        $this->assertSame('Notre texte existant', $page->body);
        $this->assertSame(\App\Support\LegalContent::terms(), $page->terms_body);
        $this->assertSame(\App\Support\LegalContent::privacy(), $page->privacy_body);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_combined_legacy_content_is_separated_without_overwriting_custom_sections(): void
    {
        $migration = require database_path('migrations/2026_10_18_020000_separate_legacy_about_legal_sections.php');
        $body = "Notre présentation\n\n##Conditions d'utilisation\n\nNos conditions\n\n##Règles de confidentialité\n\nNotre confidentialité";
        SitePage::where('slug', 'about')->update(['body' => $body]);
        $migration->up();
        $page = SitePage::firstOrFail();
        $this->assertSame('Notre présentation', $page->body);
        $this->assertSame('Nos conditions', $page->terms_body);
        $this->assertSame('Notre confidentialité', $page->privacy_body);
        $migration->up();
        $this->assertSame($page->getAttributes(), $page->fresh()->getAttributes());
        $this->get('/about')->assertOk()->assertSee('Nos conditions')->assertSee('Notre confidentialité');

        $page->update(['body' => $body, 'terms_body' => 'Conditions modifiées dans le nouvel éditeur']);
        $migration->up();
        $this->assertSame($body, $page->fresh()->body);
        $this->assertSame('Conditions modifiées dans le nouvel éditeur', $page->fresh()->terms_body);
    }

}
