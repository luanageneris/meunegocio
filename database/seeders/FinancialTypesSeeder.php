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
            ['name' => 'Serviço'],
            ['name' => 'Doação'],
            ['name' => 'Investimento'],
            ['name' => 'Devolução'],
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
            [
                'name' => 'Xerox Preto e Branco',
                'unit' => 'Folha',
                'service_value' => 0.50,
                'commission_percentage' => 15.00,
                'commission_value' => 0.08,
                'resource_value' => 0.10,
            ],
            [
                'name' => 'Xerox Colorido',
                'unit' => 'Folha',
                'service_value' => 2.00,
                'commission_percentage' => 20.00,
                'commission_value' => 0.40,
                'resource_value' => 0.50,
            ],
            [
                'name' => 'Impressão Preto e Branco',
                'unit' => 'Folha',
                'service_value' => 1.00,
                'commission_percentage' => 10.00,
                'commission_value' => 0.10,
                'resource_value' => 0.20,
            ],
            [
                'name' => 'Impressão Colorida',
                'unit' => 'Folha',
                'service_value' => 3.50,
                'commission_percentage' => 18.00,
                'commission_value' => 0.63,
                'resource_value' => 0.80,
            ],
            [
                'name' => 'Design Gráfico',
                'unit' => 'Hora',
                'service_value' => 80.00,
                'commission_percentage' => 25.00,
                'commission_value' => 20.00,
                'resource_value' => 5.00,
            ],
            [
                'name' => 'Encadernação',
                'unit' => 'Unidade',
                'service_value' => 15.00,
                'commission_percentage' => 12.00,
                'commission_value' => 1.80,
                'resource_value' => 3.00,
            ],
        ];

        foreach ($serviceTypes as $type) {
            ServiceType::firstOrCreate(
                ['name' => $type['name'], 'company_id' => $company->id],
                array_merge($type, ['company_id' => $company->id])
            );
        }
    }
}
