<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obter uma empresa e usuário existentes
        $company = Company::firstOr(function () {
            return Company::factory()->create();
        });

        $user = User::where('company_id', $company->id)->firstOr(function () use ($company) {
            return User::factory()->for($company)->create();
        });

        // Criar logs de auditoria de exemplo
        AuditLog::factory()
                ->for($company)
                ->for($user)
                ->create()
                ->create(function () use ($company, $user) {
                    return [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'action' => 'create',
                        'model' => 'App\Models\Collaborator',
                        'model_id' => 1,
                    ];
                })
                ->create(function () use ($company, $user) {
                    return [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'action' => 'update',
                        'model' => 'App\Models\FinancialRecord',
                        'model_id' => 2,
                    ];
                })
                ->create(function () use ($company, $user) {
                    return [
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'action' => 'delete',
                        'model' => 'App\Models\Commission',
                        'model_id' => 3,
                    ];
                });
    }
}
