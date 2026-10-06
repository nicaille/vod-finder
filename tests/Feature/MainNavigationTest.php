<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function navigation(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    public function test_all_main_pages_keep_the_same_links_and_mark_the_current_page(): void
    {
        $this->actingAs(User::factory()->create());
        $links = [
            'search.index' => 'On regarde quoi ?',
            'watchlist.index' => 'Playlist',
            'account.edit' => 'Mon compte',
            'lists.index' => 'Mes listes',
            'series.index' => 'Séries suivies',
        ];

        foreach (array_keys($links) as $current) {
            $xpath = $this->navigation($this->get(route($current))->assertOk()->getContent());
            $nav = '//nav[@aria-label="Navigation principale"]';
            $this->assertSame(1, $xpath->query($nav)->length);
            $this->assertSame(5, $xpath->query($nav.'//a')->length);
            foreach ($links as $name => $label) {
                $nodes = $xpath->query($nav.'//a[@href="'.route($name).'"]');
                $this->assertSame(1, $nodes->length);
                $this->assertSame($label, trim($nodes->item(0)->textContent));
            }
            $active = $xpath->query($nav.'//a[@aria-current="page"]');
            $this->assertSame(1, $active->length);
            $this->assertSame(route($current), $active->item(0)->getAttribute('href'));
            $this->assertSame(1, $xpath->query($nav.'//form[@action="'.route('logout').'" and @method="POST"]//input[@name="_token"]')->length);
        }
    }

    public function test_guests_keep_search_and_login_links_without_private_links(): void
    {
        $xpath = $this->navigation($this->get('/')->assertOk()->getContent());
        $nav = '//nav[@aria-label="Navigation principale"]';
        $this->assertSame(2, $xpath->query($nav.'//a')->length);
        $this->assertSame(1, $xpath->query($nav.'//a[@href="'.route('login').'"]')->length);
        $this->assertSame(1, $xpath->query($nav.'//a[@href="'.route('search.index').'" and @aria-current="page"]')->length);
        $this->assertSame(0, $xpath->query($nav.'//form')->length);
    }
}
