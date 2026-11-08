<?php

$root = getenv('VOD_BROWSER_ROOT');
if (!$root || !str_starts_with(realpath($root) ?: '', sys_get_temp_dir().'/vod-browser-')) {
    http_response_code(500); exit('Isolated test root required.');
}
// This server exists only for the guarded disposable fixture, never in the release.
// Separate simulated visitors without disabling the application's rate limits.
$testIp = $_SERVER['HTTP_X_VOD_BROWSER_IP'] ?? '';
if (preg_match('/\A198\.(?:18|19)\.\d{1,3}\.\d{1,3}\z/', $testIp) && filter_var($testIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $_SERVER['REMOTE_ADDR'] = $testIp;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicFile = realpath($root.'/public'.$path);
if ($path !== '/' && $publicFile && str_starts_with($publicFile, $root.'/public/') && is_file($publicFile)) return false;
$app = require __DIR__.'/bootstrap.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
