<?php

$root = getenv('VOD_BROWSER_ROOT');
if (!$root || !str_starts_with(realpath($root) ?: '', sys_get_temp_dir().'/vod-browser-')) {
    throw new RuntimeException('Browser tests require a disposable application under the system temp directory.');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $root.'/test.sqlite') {
    throw new RuntimeException('Browser tests must never use a developer database.');
}
config(['services.tmdb.key' => 'fake-browser-key', 'services.streaming_availability.key' => 'fake-browser-key', 'services.streaming_availability.direct_key' => '']);
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(function ($request) {
    $path = parse_url($request->url(), PHP_URL_PATH);
    $movie = ['id' => 42, 'title' => 'Dune', 'media_type' => 'movie', 'release_date' => '2021-10-01', 'overview' => 'Synopsis du film de test.', 'popularity' => 100, 'vote_count' => 1000, 'vote_average' => 8, 'runtime' => 155, 'genre_ids' => [878]];
    $series = ['id' => 43, 'name' => 'MobLand', 'media_type' => 'tv', 'first_air_date' => '2025-01-01', 'overview' => 'Synopsis de la série de test.', 'popularity' => 80, 'vote_count' => 500, 'vote_average' => 8, 'status' => 'Returning Series', 'seasons' => [['season_number' => 2]], 'external_ids' => ['imdb_id' => 'tt1234567'], 'next_episode_to_air' => ['season_number' => 2, 'episode_number' => 1, 'air_date' => now('Europe/Paris')->addWeek()->toDateString()]];
    $person = ['id' => 500, 'name' => 'Tom Cruise', 'media_type' => 'person', 'popularity' => 100, 'known_for_department' => 'Acting', 'known_for' => [$movie]];
    if (str_starts_with($path, '/countries/')) return Illuminate\Support\Facades\Http::response(['services' => [['id' => 'netflix', 'name' => 'Netflix']]]);
    if (preg_match('~^/3/watch/providers/(movie|tv)$~', $path)) return Illuminate\Support\Facades\Http::response(['results' => [['provider_id' => 8, 'provider_name' => 'Netflix']]]);
    if (preg_match('~^/shows/(movie|tv)/(\d+)$~', $path, $matches)) return Illuminate\Support\Facades\Http::response(['tmdbId' => $matches[1].'/'.$matches[2], 'streamingOptions' => ['fr' => [['service' => ['id' => 'netflix', 'name' => 'Netflix'], 'type' => 'subscription', 'link' => 'https://www.netflix.com/title/42']]]]);
    if (str_contains($path, '/watch/providers')) return Illuminate\Support\Facades\Http::response(['results' => ['FR' => ['flatrate' => [['provider_name' => 'Netflix', 'provider_id' => 8]], 'rent' => [['provider_name' => 'Amazon Video', 'provider_id' => 10]], 'buy' => [['provider_name' => 'Apple iTunes', 'provider_id' => 2]]]]]);
    if (str_contains($path, '/recommendations')) return Illuminate\Support\Facades\Http::response(['results' => []]);
    if (str_contains($path, '/combined_credits')) return Illuminate\Support\Facades\Http::response(['cast' => [$movie + ['character' => 'Paul']], 'crew' => []]);
    if (str_contains($path, '/season/')) return Illuminate\Support\Facades\Http::response(['episodes' => [['episode_number' => 1, 'name' => 'Épisode à venir', 'air_date' => now('Europe/Paris')->addWeek()->toDateString()]]]);
    if ($path === '/3/search/multi') return Illuminate\Support\Facades\Http::response(['results' => str_contains(strtolower($request['query'] ?? ''), 'tom') ? [$person] : [$movie, $series]]);
    if ($path === '/3/search/movie' || str_starts_with($path, '/3/discover/movie')) return Illuminate\Support\Facades\Http::response(['results' => [$movie]]);
    if ($path === '/3/search/tv' || str_starts_with($path, '/3/discover/tv')) return Illuminate\Support\Facades\Http::response(['results' => [$series]]);
    if ($path === '/3/person/500') return Illuminate\Support\Facades\Http::response($person + ['birthday' => '1962-07-03', 'external_ids' => ['imdb_id' => 'nm0000129']]);
    if ($path === '/3/movie/42' || $path === '/3/tv/43') return Illuminate\Support\Facades\Http::response(($path === '/3/movie/42' ? $movie : $series) + ['credits' => ['cast' => [$person + ['character' => 'Paul']], 'crew' => []]]);
    if ($path === '/lookup/shows') return Illuminate\Support\Facades\Http::response(['id' => 900, 'externals' => ['imdb' => 'tt1234567']]);
    if ($path === '/shows/900/episodes') return Illuminate\Support\Facades\Http::response([['season' => 2, 'number' => 1, 'name' => 'Épisode à venir', 'airstamp' => now('UTC')->addWeek()->format('Y-m-d\TH:i:s\Z')]]);
    throw new RuntimeException('Unexpected external API path in browser test: '.$path);
});
return $app;
