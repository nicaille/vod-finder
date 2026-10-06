<?php

namespace App\Support;

final class TmdbImage
{
    public static function posterSrcset(?string $url): ?string
    {
        if (!$url || !preg_match('~^https://image\.tmdb\.org/t/p/(?:w[0-9]+|original)(/[^?#\s]+)$~', $url, $matches)) return null;
        return implode(', ', array_map(fn ($size) => 'https://image.tmdb.org/t/p/w'.$size.$matches[1].' '.$size.'w', [185, 342, 500, 780]));
    }

    public static function detail(array $details, bool $person = false): ?array
    {
        $kind = $person ? 'profile' : (!empty($details['backdrop_path']) ? 'backdrop' : 'poster');
        $path = $details[$kind.'_path'] ?? null;
        if (!is_string($path) || !str_starts_with($path, '/')) return null;
        $metadata = collect($details['images'][$kind.'s'] ?? [])->firstWhere('file_path', $path) ?? [];
        $width = max(0, (int) ($metadata['width'] ?? 0));
        $height = max(0, (int) ($metadata['height'] ?? 0));
        $sizes = match ($kind) {
            'backdrop' => [300, 780, 1280],
            'profile' => [45, 185],
            default => [92, 154, 185, 342, 500, 780],
        };
        $sources = [];
        foreach ($sizes as $size) {
            if (!$width || $size < $width) $sources[] = ['width' => $size, 'url' => 'https://image.tmdb.org/t/p/w'.$size.$path];
        }
        if ($kind === 'profile' && $width && $height > 632) {
            $sources[] = ['width' => (int) round(632 * $width / $height), 'url' => 'https://image.tmdb.org/t/p/h632'.$path];
        }
        usort($sources, fn ($a, $b) => $a['width'] <=> $b['width']);
        $original = 'https://image.tmdb.org/t/p/original'.$path;
        $sources[] = ['width' => $width, 'url' => $original];

        return compact('kind', 'width', 'height', 'sources', 'original');
    }
}
