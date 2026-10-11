<?php

try {

// Run twice against disposable copies: seed with Laravel 10, verify with Laravel 13.
$root = getenv('VOD_UPGRADE_ROOT');
if (!$root || !str_starts_with(realpath($root) ?: '', sys_get_temp_dir().'/vod-upgrade-')) {
    throw new RuntimeException('An isolated upgrade application is required.');
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'vod_finder_upgrade' || config('database.connections.mysql.host') !== '127.0.0.1' || config('database.connections.mysql.username') !== 'vod_test') {
    throw new RuntimeException('Only the disposable local upgrade database is permitted.');
}
Illuminate\Support\Facades\Http::preventStrayRequests();
$tables = ['users', 'platforms', 'user_platform_subscriptions', 'watchlist_items', 'lists', 'list_items', 'favorites', 'tracked_series', 'series_follows', 'series_episodes', 'episode_alerts', 'notification_mail_settings', 'user_connections', 'recommendations'];
$snapshot = function () use ($tables, $root) {
    $rows = [];
    foreach ($tables as $table) $rows[$table] = Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toJson();
    return ['rows' => $rows, 'env' => hash_file('sha256', $root.'/.env'), 'push' => hash_file('sha256', $root.'/storage/app/private/webpush-keys.json')];
};
if (($argv[1] ?? '') === 'seed') {
    if (!str_starts_with(Illuminate\Foundation\Application::VERSION, '10.')) throw new RuntimeException('Baseline must be Laravel 10.');
    foreach (['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) @mkdir($root.'/'.$dir, 0755, true);
    if ($kernel->call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Baseline migrations failed.');
    if ($kernel->call('db:seed', ['--class' => Database\Seeders\PlatformSeeder::class, '--force' => true]) !== 0) throw new RuntimeException('Baseline seeding failed.');
    $a = App\Models\User::create(['name' => 'Upgrade A', 'first_name' => 'Upgrade', 'last_name' => 'A', 'nickname' => 'upgrade-a', 'email' => 'a@example.test', 'password' => 'upgrade-password', 'notify_opt_in' => true, 'notify_email' => true]);
    $a->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
    $b = App\Models\User::create(['name' => 'Upgrade B', 'email' => 'b@example.test', 'password' => 'upgrade-password']);
    $a->platformSubscriptions()->attach(App\Models\Platform::where('slug', 'netflix')->firstOrFail(), ['is_active' => true, 'notify_opt_in' => true]);
    $a->watchlist()->create(['tmdb_id' => 42, 'type' => 'movie', 'title' => 'Dune', 'year' => '2021']);
    $list = $a->lists()->create(['name' => 'Upgrade collection', 'is_public' => false]);
    $list->items()->create(['tmdb_id' => 42, 'type' => 'movie', 'added_by' => $a->id]);
    $a->favorites()->create(['tmdb_id' => 42, 'type' => 'movie']);
    $series = App\Models\TrackedSeries::create(['tmdb_id' => 43, 'name' => 'MobLand']);
    $a->seriesFollows()->create(['tracked_series_id' => $series->id, 'alerts_enabled' => true]);
    $episode = $series->episodes()->create(['season_number' => 2, 'episode_number' => 1, 'name' => 'Upgrade episode', 'air_date' => '2026-10-25', 'airs_at' => '2026-10-25T01:30:00Z']);
    $a->episodeAlerts()->create(['series_episode_id' => $episode->id]);
    $settings = new App\Models\NotificationMailSetting(['brevo_enabled' => true, 'brevo_api_key' => 'fake-upgrade-brevo-key', 'sender_email' => 'notifications@example.test', 'sender_name' => 'VOD Finder']);
    $settings->id = 1; $settings->save();
    $connection = App\Models\UserConnection::create(['user_low_id' => $a->id, 'user_high_id' => $b->id, 'requested_by' => $a->id, 'status' => 'accepted']);
    App\Models\Recommendation::create(['sender_id' => $a->id, 'recipient_id' => $b->id, 'type' => 'movie', 'tmdb_id' => 42, 'content' => ['overview' => 'Upgrade recommendation']]);
    file_put_contents($root.'/storage/app/private/webpush-keys.json', json_encode((new App\Services\VapidKeyGenerator)->generate()));
    Illuminate\Support\Facades\Cache::put('upgrade-payload', ['title' => 'Dune', 'providers' => ['netflix']], 3600);
    $snapshotData = $snapshot();
    file_put_contents($root.'/expected.json', json_encode($snapshotData, JSON_THROW_ON_ERROR));
    foreach (['config:cache', 'route:cache', 'view:cache'] as $command) if ($kernel->call($command) !== 0) throw new RuntimeException('Baseline cache command failed: '.$command);
    echo "Laravel 10 fixture created: users, catalog, subscriptions, lists, alerts, contacts, recommendations, encrypted Brevo settings and VAPID keys.\n";
} elseif (($argv[1] ?? '') === 'verify') {
    if (!str_starts_with(Illuminate\Foundation\Application::VERSION, '13.')) throw new RuntimeException('Expected Laravel 13.');
    if ($kernel->call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Upgrade migrations failed.');
    $expected = json_decode(file_get_contents($root.'/expected.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($snapshot() !== $expected) throw new RuntimeException('Existing data, .env or VAPID keys changed during upgrade.');
    if (App\Models\NotificationMailSetting::findOrFail(1)->brevo_api_key !== 'fake-upgrade-brevo-key') throw new RuntimeException('Brevo key decryption failed.');
    if (!Illuminate\Support\Facades\Hash::check('upgrade-password', App\Models\User::where('email', 'a@example.test')->firstOrFail()->password)) throw new RuntimeException('Password hash changed.');
    if (Illuminate\Support\Facades\Cache::get('upgrade-payload') !== ['title' => 'Dune', 'providers' => ['netflix']]) throw new RuntimeException('Legacy cache payload was lost.');
    foreach (['config:cache', 'route:cache', 'view:cache', 'about'] as $command) if ($kernel->call($command) !== 0) throw new RuntimeException('Production cache command failed: '.$command);
    echo "Laravel 13 upgrade passed: 14 tables unchanged, APP_KEY/.env preserved, keys readable, legacy cache readable, production caches built.\n";
} else throw new RuntimeException('Use seed or verify.');
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}
