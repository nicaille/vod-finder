<?php

namespace App\Support;

final class PersonSearchRelevance
{
    public static function creditImportance(array $credit, bool $cast): int
    {
        if (!$cast) {
            return in_array($credit['job'] ?? '', ['Director', ''], true) ? 4 : 3;
        }
        $character = SearchRelevance::normalize((string) ($credit['character'] ?? ''));
        if (str_contains($character, 'archive')) return 0;
        if (in_array(99, $credit['genre_ids'] ?? [], true)
            || preg_match('/\b(self|himself|herself|lui meme|elle meme)\b/', $character)) return 1;
        if (str_contains($character, 'uncredited') || str_contains($character, 'cameo')) return 2;

        return isset($credit['order']) && (int) $credit['order'] > 5 ? 3 : 4;
    }

    public static function rank(array $items, string $sort = 'relevance'): array
    {
        usort($items, function ($a, $b) use ($sort) {
            $dateA = (string) ($a['release_date'] ?? $a['first_air_date'] ?? '');
            $dateB = (string) ($b['release_date'] ?? $b['first_air_date'] ?? '');
            $titleA = SearchRelevance::normalize((string) ($a['title'] ?? $a['name'] ?? ''));
            $titleB = SearchRelevance::normalize((string) ($b['title'] ?? $b['name'] ?? ''));
            $score = match ($sort) {
                'year_asc' => strcmp($dateA ?: '9999', $dateB ?: '9999'),
                'year_desc' => strcmp($dateB, $dateA),
                'title_az' => strcmp($titleA, $titleB),
                'title_za' => strcmp($titleB, $titleA),
                default => (($b['_person_importance'] ?? 0) <=> ($a['_person_importance'] ?? 0))
                    ?: (($b['vote_count'] ?? 0) <=> ($a['vote_count'] ?? 0))
                    ?: (($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0))
                    ?: strcmp($dateB, $dateA),
            };
            return $score ?: strcmp(($a['media_type'] ?? '').':'.$a['id'], ($b['media_type'] ?? '').':'.$b['id']);
        });
        return $items;
    }

    public static function creditLabel(array $credit, bool $cast): string
    {
        if ($cast) {
            return match (self::creditImportance($credit, true)) {
                0 => 'Images d’archives',
                1 => 'Apparition documentaire / dans son propre rôle',
                2 => 'Apparition brève ou non créditée',
                default => 'Interprétation',
            };
        }
        return match ($credit['job'] ?? '') {
            'Director' => 'Réalisation',
            'Producer', 'Executive Producer' => 'Production',
            'Writer', 'Screenplay', 'Story' => 'Écriture',
            default => 'Équipe technique',
        };
    }
}
