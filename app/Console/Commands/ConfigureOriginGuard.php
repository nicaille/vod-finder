<?php

namespace App\Console\Commands;

use App\Services\OriginGuard;
use Illuminate\Console\Command;
use Throwable;

class ConfigureOriginGuard extends Command
{
    protected $signature = 'origin:guard {action : init, token, status, observe, enforce ou off} {--allow-acme-files : Autoriser GET/HEAD des fichiers HTTP-01 existants uniquement}';
    protected $description = 'Préparer et gérer la protection privée Apache de l’origine, sans modifier public/.htaccess';

    public function handle(OriginGuard $guard): int
    {
        try {
            $action = $this->argument('action');
            if ($action === 'init') {
                $guard->initialize();
                $this->info('Clé privée créée ou conservée. Aucun blocage activé.');
            } elseif ($action === 'token') {
                $this->line($guard->token()); // Explicit local retrieval only; never used by deployment.
            } elseif (in_array($action, ['observe', 'enforce', 'off'], true)) {
                $guard->setMode($action, (bool) $this->option('allow-acme-files'));
                $this->info('Mode '.$action.' installé.');
            } elseif ($action !== 'status') {
                $this->error('Action inconnue.'); return self::INVALID;
            }
            if ($action !== 'token') {
                $status = $guard->status();
                $this->line('Mode : '.$status['mode'].' ; fichier Apache : '.($status['apache_consistent'] ? 'cohérent' : 'à vérifier'));
                $this->line('Exception fichiers ACME : '.($status['allow_acme_files'] ? 'oui' : 'non'));
            }
            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage()); return self::FAILURE;
        }
    }
}
