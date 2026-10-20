<?php

try {

$app = require __DIR__.'/bootstrap.php';
if ($app->make(Illuminate\Contracts\Console\Kernel::class)->call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Migrations failed.');
if ($app->make(Illuminate\Contracts\Console\Kernel::class)->call('db:seed', ['--class' => Database\Seeders\PlatformSeeder::class, '--force' => true]) !== 0) throw new RuntimeException('Seeding failed.');
foreach (['admin', 'member'] as $name) {
    $user = App\Models\User::create(['name' => ucfirst($name).' Test', 'first_name' => ucfirst($name), 'last_name' => 'Test', 'nickname' => 'browser-'.$name, 'email' => $name.'@example.test', 'password' => 'browser-test-password', 'notify_opt_in' => false]);
    $user->forceFill(['is_admin' => $name === 'admin', 'email_verified_at' => now()])->save();
    $user->platformSubscriptions()->attach(App\Models\Platform::where('slug', 'netflix')->firstOrFail(), ['is_active' => true]);
}
// Dedicated recipient fixture: six recommendations plus one contact acceptance.
$recipient = App\Models\User::create(['name' => 'Recipient Test', 'first_name' => 'Recipient', 'last_name' => 'Test', 'nickname' => 'browser-recipient', 'email' => 'recipient@example.test', 'password' => 'browser-test-password', 'notify_opt_in' => false]);
$sender = App\Models\User::where('email', 'member@example.test')->firstOrFail();
$connection = App\Models\UserConnection::create(['user_low_id' => $sender->id, 'user_high_id' => $recipient->id, 'requested_by' => $recipient->id, 'status' => 'accepted']);
for ($i = 0; $i < 6; $i++) App\Models\Recommendation::create(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'type' => 'movie', 'tmdb_id' => 42 + $i, 'content' => ['title' => 'Recommandation '.($i + 1), 'description' => 'À découvrir', 'image' => null, 'providers' => []]]);
App\Models\SocialEvent::create(['user_id' => $recipient->id, 'actor_id' => $sender->id, 'kind' => 'accepted', 'user_connection_id' => $connection->id]);
App\Models\User::create(['name' => 'Editing Test', 'first_name' => 'Editing', 'last_name' => 'Test', 'nickname' => 'browser-edit-target', 'email' => 'edit-target@example.test', 'password' => 'browser-test-password', 'notify_opt_in' => false]);
foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
    if ($app->make(Illuminate\Contracts\Console\Kernel::class)->call($command) !== 0) throw new RuntimeException('Production cache failed: '.$command);
}
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}
