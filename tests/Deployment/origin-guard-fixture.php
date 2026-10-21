<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = $argv[1] ?? '';
if (!str_starts_with(realpath($root) ?: '', sys_get_temp_dir().'/vod-origin-apache-')) throw new RuntimeException('Disposable fixture required.');
$guard = new App\Services\OriginGuard($root);
$guard->initialize();
$guard->setMode($argv[2], ($argv[3] ?? '') === 'acme');
