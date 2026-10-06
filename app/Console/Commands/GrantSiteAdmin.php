<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantSiteAdmin extends Command
{
    protected $signature = 'app:admin {email : Adresse du compte existant} {--revoke : Retirer les droits}';
    protected $description = 'Attribuer ou retirer les droits d’administration à un compte existant';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('Compte introuvable. Créez d’abord le compte dans l’application.');
            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => !$this->option('revoke')])->save();
        $this->info($this->option('revoke') ? 'Droits retirés.' : 'Droits d’administration attribués.');
        return self::SUCCESS;
    }
}
