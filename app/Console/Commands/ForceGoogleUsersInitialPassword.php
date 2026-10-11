<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ForceGoogleUsersInitialPassword extends Command
{
    protected $signature = 'users:force-google-password';

    protected $description = 'Define uma senha inicial para usuários antigos do Google sem senha definida.';

    public function handle(): int
    {
        $updated = User::applyInitialPasswordForGoogleUsers();

        if ($updated === 0) {
            $this->info('Nenhum usuário Google sem senha foi encontrado.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Senha inicial aplicada a %d usuário(s) Google sem senha definida. Senha: %s',
            $updated,
            User::INITIAL_PASSWORD
        ));

        return self::SUCCESS;
    }
}
