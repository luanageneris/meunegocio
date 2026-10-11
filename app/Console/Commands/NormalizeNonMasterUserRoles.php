<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class NormalizeNonMasterUserRoles extends Command
{
    protected $signature = 'users:normalize-non-master-roles {--dry-run : Apenas mostra quantos usuarios seriam alterados}';

    protected $description = 'Define role owner para todos os usuarios que nao sao o admin master.';

    public function handle(): int
    {
        $query = User::query()
            ->where('role', 'root')
            ->whereRaw('LOWER(TRIM(email)) <> ?', [strtolower(User::MASTER_ADMIN_EMAIL)]);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Nenhum usuario root fora do admin master foi encontrado.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry-run: {$count} usuario(s) root seriam alterados para owner.");

            return self::SUCCESS;
        }

        $updated = $query->update(['role' => 'owner']);

        $this->info("Processo concluido: {$updated} usuario(s) alterados para owner.");

        return self::SUCCESS;
    }
}
