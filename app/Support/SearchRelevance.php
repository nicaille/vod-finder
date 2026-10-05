<?php

namespace App\Support;

use Illuminate\Support\Str;

final class SearchRelevance
{
    public static function normalize(string $value): string
    {
        // Separate apostrophes: « d'une » must not become the word « Dune ».
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($value))) ?? '');
    }

    public static function rank(array $items, string $query): array
    {
        $query = self::normalize($query);
        $ranked = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) continue;
            $title = self::normalize((string) ($item['title'] ?? $item['name'] ?? ''));
            $original = self::normalize((string) ($item['original_title'] ?? $item['original_name'] ?? ''));
            $ranked[] = [
                'item' => $item,
                'score' => max(self::score($title, $query), self::score($original, $query) * 0.5),
                'popularity' => (float) ($item['popularity'] ?? $item['pop'] ?? 0),
                'index' => $index,
            ];
        }
        usort($ranked, fn ($a, $b) => ($b['score'] <=> $a['score'])
            ?: ($b['popularity'] <=> $a['popularity']) ?: ($a['index'] <=> $b['index']));

        return array_column($ranked, 'item');
    }

    private static function score(string $title, string $query): float
    {
        if ($query === '' || $title === '') return 0;
        if ($title === $query) return 5;
        if (str_starts_with($title, $query.' ')) return 4;
        if (str_contains(' '.$title.' ', ' '.$query.' ')) return 3;
        $words = array_unique(explode(' ', $query));
        $matched = count(array_intersect($words, explode(' ', $title)));
        if ($matched) return 1 + $matched / count($words);
        // Still useful while a user is typing an incomplete word.
        if (str_starts_with($title, $query)) return 1;

        return 0;
    }
}
