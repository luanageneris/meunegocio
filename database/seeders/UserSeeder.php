<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Services\CompanyDefaultsService;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function __construct(private CompanyDefaultsService $companyDefaultsService)
    {
    }

    public function run(): void
    {
        // Criar empresa Xerografia & Cia
        $company = Company::firstOrCreate(
            ['slug' => 'xerografia-cia'],
            [
                'name' => 'Xerografia & Cia',
                'email' => 'contato@xerografia.com.br',
                'phone' => '(11) 9999-9999',
                'document' => '12.345.678/0001-99',
                'address' => 'Rua das Copiadoras, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'active' => true,
            ]
        );

        // Usuário proprietário da empresa
        User::updateOrCreate(
            ['email' => 'luanacarvalhomesquita@gmail.com'],
            [
                'company_id' => $company->id,
                'name' => 'Dona Genilda',
                'password' => bcrypt('password'),
                'google_id' => null,
                'role' => 'root',
                'email_verified_at' => now(),
            ]
        );

        // Usuário master para administração geral do sistema
        User::updateOrCreate(
            ['email' => 'admin@meunegocio.cloud'],
            [
                'company_id' => $company->id,
                'name' => 'Administrador Master',
                'password' => bcrypt('password'),
                'google_id' => null,
                'role' => 'root',
                'email_verified_at' => now(),
            ]
        );

        $this->companyDefaultsService->ensureForCompany((int) $company->id);
    }
}
