<?php

namespace Database\Seeders;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinancialRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            return;
        }

        // Garantir colaboradores básicos
        $collaborators = Collaborator::where('company_id', $company->id)->get();
        if ($collaborators->count() < 2) {
            $extras = 2 - $collaborators->count();
            $collaborators = $collaborators->concat(
                Collaborator::factory()->count($extras)->create(['company_id' => $company->id])
            );
        }

        $entryType = EntryType::where('company_id', $company->id)->first();
        $exitMaterial = ExitType::where('company_id', $company->id)->where('name', 'Material')->first();
        $exitComissao = ExitType::where('company_id', $company->id)->where('name', 'Comissão')->first();
        $serviceType = ServiceType::where('company_id', $company->id)->first();

        $today = Carbon::today();

        $records = [
            [
                'type' => 'entry',
                'amount' => 120.00,
                'description' => 'Impressões e cópias',
                'date' => $today->copy()->subDays(1)->toDateString(),
                'entry_type_id' => $entryType?->id,
                'service_type_id' => $serviceType?->id,
                'collaborator_id' => $collaborators->first()?->id,
                'quantity' => 60,
                'discount' => 0,
            ],
            [
                'type' => 'entry',
                'amount' => 250.00,
                'description' => 'Design gráfico',
                'date' => $today->copy()->subDays(2)->toDateString(),
                'entry_type_id' => $entryType?->id,
                'service_type_id' => $serviceType?->id,
                'collaborator_id' => $collaborators->get(1)?->id,
                'quantity' => 5,
                'discount' => 20.00,
            ],
            [
                'type' => 'entry',
                'amount' => 90.00,
                'description' => 'Encadernação de apostilas',
                'date' => $today->copy()->subDays(3)->toDateString(),
                'entry_type_id' => $entryType?->id,
                'service_type_id' => $serviceType?->id,
                'collaborator_id' => $collaborators->first()?->id,
                'quantity' => 6,
                'discount' => 0,
            ],
            [
                'type' => 'exit',
                'amount' => 80.00,
                'description' => 'Compra de papel A4',
                'date' => $today->copy()->subDays(1)->toDateString(),
                'exit_type_id' => $exitMaterial?->id,
                'collaborator_id' => $collaborators->first()?->id,
                'quantity' => 10,
                'discount' => 0,
            ],
            [
                'type' => 'exit',
                'amount' => 150.00,
                'description' => 'Pagamento de comissão diária',
                'date' => $today->copy()->subDays(2)->toDateString(),
                'exit_type_id' => $exitComissao?->id,
                'collaborator_id' => $collaborators->get(1)?->id,
                'quantity' => 1,
                'discount' => 0,
            ],
            [
                'type' => 'exit',
                'amount' => 60.00,
                'description' => 'Tintas para impressora',
                'date' => $today->copy()->subDays(3)->toDateString(),
                'exit_type_id' => $exitMaterial?->id,
                'collaborator_id' => $collaborators->first()?->id,
                'quantity' => 3,
                'discount' => 0,
            ],
        ];

        // Add a small batch covering hoje, últimos 7 dias e mensal
        for ($i = 0; $i <= 6; $i++) {
            $date = $today->copy()->subDays($i)->toDateString();
            $records[] = [
                'type' => 'entry',
                'amount' => 50 + ($i * 5),
                'description' => 'Entrada diária mock ' . $date,
                'date' => $date,
                'entry_type_id' => $entryType?->id,
                'service_type_id' => $serviceType?->id,
                'collaborator_id' => $collaborators->first()?->id,
                'quantity' => 1 + $i,
                'discount' => 0,
            ];
            $records[] = [
                'type' => 'exit',
                'amount' => 20 + ($i * 3),
                'description' => 'Saída diária mock ' . $date,
                'date' => $date,
                'exit_type_id' => $exitMaterial?->id ?? $exitComissao?->id,
                'collaborator_id' => $collaborators->get(1)?->id ?? $collaborators->first()?->id,
                'quantity' => 1,
                'discount' => 0,
            ];
        }

        foreach ($records as $record) {
            FinancialRecord::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'description' => $record['description'],
                    'date' => $record['date'],
                ],
                array_merge($record, [
                    'company_id' => $company->id,
                ])
            );
        }
    }
}
