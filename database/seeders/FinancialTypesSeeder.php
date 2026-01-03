<?php

namespace Database\Seeders;

use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ServiceType;
use App\Models\Company;
use Illuminate\Database\Seeder;

class FinancialTypesSeeder extends Seeder
{
    public function run(): void
    {
        // Obter a primeira empresa (Xerografia & Cia)
        $company = Company::first();
        
        if (!$company) {
            return; // Skipped if no company exists
        }

        // Tipos de Entrada
        $entryTypes = [
            ['name' => 'Serviço', 'requires_collaborator' => true],
            ['name' => 'Doação', 'requires_collaborator' => false],
            ['name' => 'Investimento', 'requires_collaborator' => false],
            ['name' => 'Devolução', 'requires_collaborator' => false],
        ];

        foreach ($entryTypes as $type) {
            EntryType::firstOrCreate(
                ['name' => $type['name'], 'company_id' => $company->id],
                array_merge($type, ['company_id' => $company->id])
            );
        }

        // Tipos de Saída
        $exitTypes = [
            ['name' => 'Comissão'],
            ['name' => 'Material'],
            ['name' => 'Energia'],
            ['name' => 'Água'],
            ['name' => 'Internet'],
            ['name' => 'Aluguel'],
            ['name' => 'Telefone'],
            ['name' => 'Outras'],
        ];

        foreach ($exitTypes as $type) {
            ExitType::firstOrCreate(
                ['name' => $type['name'], 'company_id' => $company->id],
                array_merge($type, ['company_id' => $company->id])
            );
        }

        // Tipos de Serviço
        $serviceTypes = [
            ['name' => 'Xerox Preto e Branco', 'commission_percentage' => 15.00],
            ['name' => 'Xerox Colorido', 'commission_percentage' => 20.00],
            ['name' => 'Impressão Preto e Branco', 'commission_percentage' => 10.00],
            ['name' => 'Impressão Colorida', 'commission_percentage' => 18.00],
            ['name' => 'Design Gráfico', 'commission_percentage' => 25.00],
            ['name' => 'Encadernação', 'commission_percentage' => 12.00],
        ];

        foreach ($serviceTypes as $type) {
            ServiceType::firstOrCreate(
                ['name' => $type['name'], 'company_id' => $company->id],
                array_merge($type, ['company_id' => $company->id])
            );
        }
    }
}
