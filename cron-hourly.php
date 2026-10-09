<?php

// OVH scheduled tasks execute this file directly, without arguments.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(__DIR__);
if (is_file(__DIR__.'/storage/framework/down')) {
    exit(0);
}

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$input = new Symfony\Component\Console\Input\ArrayInput(['command' => 'cron:hourly']);
$status = $kernel->handle($input, new Symfony\Component\Console\Output\ConsoleOutput());
$kernel->terminate($input, $status);
exit($status);
