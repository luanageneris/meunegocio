<?php

namespace Database\Seeders;

use App\Models\Commission;
use App\Models\Company;
use App\Models\FinancialRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CommissionsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Please seed companies first.');
            return;
        }

        // Get all financial records with service types (entries that generate commissions)
        $records = FinancialRecord::where('company_id', $company->id)
            ->whereNotNull('service_type_id')
            ->whereNotNull('collaborator_id')
            ->with('serviceType', 'collaborator')
            ->get();

        if ($records->isEmpty()) {
            $this->command->warn('No financial records with service types found.');
            return;
        }

        $created = 0;
        foreach ($records as $record) {
            // Skip if commission already exists
            if (Commission::where('financial_record_id', $record->id)->exists()) {
                continue;
            }

            // Calculate commission: amount * (percentage / 100)
            $commissionValue = $record->amount * ($record->serviceType->commission_percentage / 100);

            Commission::create([
                'company_id' => $company->id,
                'collaborator_id' => $record->collaborator_id,
                'financial_record_id' => $record->id,
                'date' => $record->date,
                'amount' => $commissionValue,
                'paid_at' => null, // Unpaid by default
                'notes' => "Comissão de {$record->serviceType->name}",
            ]);

            $created++;
        }

        $this->command->info("Created {$created} commissions.");
    }
}
