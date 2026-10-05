<?php

namespace App\Support;

class ProviderIdentity
{
    /** Stable TMDb identities; channel subscriptions keep their own route. */
    public static function tmdb(int $id, string $name): ?array
    {
        $ids = [
            350 => ['appletv', null],
            531 => ['paramountplus', null],
            582 => ['paramountplus', 'prime'],
            633 => ['paramountplus', 'roku'],
            1825 => ['hbomax', 'prime'],
            1853 => ['paramountplus', 'appletv'],
            1899 => ['hbomax', null],
            2284 => ['hbomax', 'unext'],
            2303 => ['paramountplus', null],
            2304 => ['paramountplus', null],
            2616 => ['paramountplus', null],
        ];
        $names = [
            'hbo' => ['hbomax', null],
            'hbo go' => ['hbomax', null],
            'hbo now' => ['hbomax', null],
            'hbo max' => ['hbomax', null],
            'max' => ['hbomax', null],
            'hbo max amazon channel' => ['hbomax', 'prime'],
            'max amazon channel' => ['hbomax', 'prime'],
            'hbo max on u-next' => ['hbomax', 'unext'],
            'paramount plus' => ['paramountplus', null],
            'paramount+' => ['paramountplus', null],
            'paramount plus premium' => ['paramountplus', null],
            'paramount plus basic with ads' => ['paramountplus', null],
            'paramount plus essential' => ['paramountplus', null],
            'paramount+ amazon channel' => ['paramountplus', 'prime'],
            'paramount plus amazon channel' => ['paramountplus', 'prime'],
            'paramount+ roku premium channel' => ['paramountplus', 'roku'],
            'paramount plus apple tv channel' => ['paramountplus', 'appletv'],
        ];

        return $ids[$id] ?? $names[strtolower(trim(preg_replace('/\s+/', ' ', $name)))] ?? null;
    }

    public static function viaLabel(?string $via): ?string
    {
        return [
            'prime' => 'via Prime Video',
            'canalplus' => 'via Canal+',
            'appletv' => 'via Apple TV',
            'roku' => 'via Roku',
            'unext' => 'via U-Next',
        ][$via ?? ''] ?? null;
    }
}
