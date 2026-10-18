<?php

namespace Tests\Unit;

use App\Support\AboutContent;
use PHPUnit\Framework\TestCase;

class AboutContentTest extends TestCase
{
    public function test_pasted_sections_with_or_without_spaces_are_preserved(): void
    {
        foreach (["##Conditions d'utilisation", '## Conditions d’utilisation de l’application'] as $heading) {
            $parts = AboutContent::splitLegacySections("Présentation personnalisée\r\n\r\n$heading\r\n\r\nConditions originales\r\n\r\n##Règles de confidentialité\r\n\r\nPolitique originale");
            $this->assertSame(['body' => 'Présentation personnalisée', 'terms_body' => 'Conditions originales', 'privacy_body' => 'Politique originale'], $parts);
        }
    }

    public function test_incomplete_ambiguous_or_reversed_sections_are_left_untouched(): void
    {
        foreach ([
            AboutContent::defaults()['body'],
            "Intro\n## Conditions d’utilisation\nTexte",
            "Intro\n## Règles de confidentialité\nTexte\n## Conditions d’utilisation\nTexte",
            "Intro\n## Conditions d’utilisation\nTexte\n## Règles de confidentialité\n",
            "Intro\n## Conditions d’utilisation\nTexte\n## Conditions d’utilisation\nTexte\n## Règles de confidentialité\nTexte",
        ] as $body) $this->assertNull(AboutContent::splitLegacySections($body));
    }
}
