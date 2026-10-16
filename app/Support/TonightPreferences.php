<?php

namespace App\Support;

class TonightPreferences
{
    public const DURATIONS = [30, 60, 90, 120, 180];
    public const GENRES = [
        'any' => 'Tous les genres', 'comedy' => 'Comédie', 'drama' => 'Drame',
        'adventure' => 'Action / aventure', 'mystery' => 'Mystère',
        'fantasy' => 'Science-fiction / fantastique', 'documentary' => 'Documentaire', 'animation' => 'Animation',
    ];
    public const MOODS = [
        'any' => 'Peu importe', 'light' => 'Rire et décompresser', 'escape' => 'M’évader',
        'suspense' => 'Du suspense', 'learn' => 'Découvrir et apprendre',
    ];

    public static function genres(string $genre, string $type): array
    {
        return match ($genre) {
            'comedy' => [35], 'drama' => [18], 'adventure' => $type === 'movie' ? [28, 12] : [10759],
            'mystery' => [9648], 'fantasy' => $type === 'movie' ? [878, 14] : [10765],
            'documentary' => [99], 'animation' => [16], default => [],
        };
    }

    public static function moodGenres(string $mood, string $type): array
    {
        return match ($mood) {
            'light' => [35], 'escape' => $type === 'movie' ? [12, 14, 878] : [10759, 10765],
            'suspense' => $type === 'movie' ? [53, 9648] : [9648], 'learn' => [99], default => [],
        };
    }
}
