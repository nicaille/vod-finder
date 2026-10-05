<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Minishlink\WebPush\VAPID;

class InitializeWebPush extends Command
{
    protected $signature = 'notifications:setup-push';
    protected $description = 'Crée les clés privées de notifications push sans modifier .env';

    public function handle(): int
    {
        $path = config('webpush.key_file');
        if (is_file($path)) {
            $this->info('Les clés push existent déjà et sont conservées.');
            return self::SUCCESS;
        }
        $keys = VAPID::createVapidKeys();
        File::ensureDirectoryExists(dirname($path), 0700);
        $handle = fopen($path, 'x');
        if (!$handle) return self::FAILURE;
        chmod($path, 0600);
        fwrite($handle, json_encode($keys, JSON_THROW_ON_ERROR));
        fclose($handle);
        $this->info('Clés push créées dans le stockage privé. Conserver ce fichier lors des déploiements.');
        return self::SUCCESS;
    }
}
