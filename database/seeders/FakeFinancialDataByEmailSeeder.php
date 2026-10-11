<?php

namespace Database\Seeders;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\CompanyDefaultsService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FakeFinancialDataByEmailSeeder extends Seeder
{
    public function __construct(private CompanyDefaultsService $companyDefaultsService)
    {
    }

    public function run(): void
    {
        $targetEmail = trim((string) env('SEED_USER_EMAIL', 'luanacarvalhomesquita@gmail.com'));

        if ($targetEmail === '') {
            $this->command?->error('Defina a variável SEED_USER_EMAIL antes de executar esta seed.');

            return;
        }

        $user = User::query()
            ->with('company')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($targetEmail)])
            ->first();

        if (!$user || !$user->company_id || !$user->company) {
            $this->command?->error("Usuário com empresa não encontrado para o e-mail: {$targetEmail}");

            return;
        }

        $companyId = (int) $user->company_id;

        // Garante os itens padrão da empresa (inclui "Não definido").
        $this->companyDefaultsService->ensureForCompany($companyId);

        $entryType = EntryType::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'não definido'])
            ->first()
            ?? EntryType::query()->where('company_id', $companyId)->first();

        $exitType = ExitType::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'não definido'])
            ->first()
            ?? ExitType::query()->where('company_id', $companyId)->first();

        $collaborator = Collaborator::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'não definido'])
            ->first()
            ?? Collaborator::query()->where('company_id', $companyId)->first();

        if (!$entryType || !$exitType || !$collaborator) {
            $this->command?->error('Tipos padrão e colaborador são obrigatórios para gerar dados fake.');

            return;
        }

        $serviceType = ServiceType::query()->where('company_id', $companyId)->first();
        $today = Carbon::today();

        $entriesCreated = 0;
        $exitsCreated = 0;
        $commissionsCreated = 0;

        for ($dayOffset = 0; $dayOffset < 45; $dayOffset++) {
            $date = $today->copy()->subDays($dayOffset)->toDateString();

            $dailyEntries = random_int(1, 3);
            $dailyExits = random_int(1, 2);
            $dailyCommissions = random_int(0, 2);
            $entryRecordsForDay = [];

            for ($i = 1; $i <= $dailyEntries; $i++) {
                $entry = FinancialRecord::query()->create([
                    'company_id' => $companyId,
                    'type' => 'entry',
                    'entry_type_id' => $entryType->id,
                    'exit_type_id' => null,
                    'collaborator_id' => $collaborator->id,
                    'service_type_id' => $serviceType?->id,
                    'work_shift_id' => null,
                    'amount' => random_int(50, 450),
                    'quantity' => random_int(1, 5),
                    'discount' => random_int(0, 30),
                    'date' => $date,
                    'description' => "Entrada fake {$i} - {$date}",
                ]);

                $entryRecordsForDay[] = $entry;
                $entriesCreated++;
            }

            for ($i = 1; $i <= $dailyExits; $i++) {
                FinancialRecord::query()->create([
                    'company_id' => $companyId,
                    'type' => 'exit',
                    'entry_type_id' => null,
                    'exit_type_id' => $exitType->id,
                    'collaborator_id' => $collaborator->id,
                    'service_type_id' => null,
                    'work_shift_id' => null,
                    'amount' => random_int(20, 260),
                    'quantity' => random_int(1, 3),
                    'discount' => 0,
                    'date' => $date,
                    'description' => "Saída fake {$i} - {$date}",
                ]);

                $exitsCreated++;
            }

            for ($i = 1; $i <= $dailyCommissions; $i++) {
                $linkedEntry = $entryRecordsForDay[array_rand($entryRecordsForDay)] ?? null;

                Commission::query()->create([
                    'company_id' => $companyId,
                    'collaborator_id' => $collaborator->id,
                    'financial_record_id' => $linkedEntry?->id,
                    'date' => $date,
                    'amount' => random_int(10, 120),
                    'paid_at' => null,
                    'notes' => "Comissão fake {$i} - {$date}",
                ]);

                $commissionsCreated++;
            }
        }

        $this->command?->info('Dados fake criados com sucesso.');
        $this->command?->line("E-mail alvo: {$user->email}");
        $this->command?->line("Empresa: {$user->company->name} (#{$companyId})");
        $this->command?->line("Entradas criadas: {$entriesCreated}");
        $this->command?->line("Saídas criadas: {$exitsCreated}");
        $this->command?->line("Comissões criadas: {$commissionsCreated}");
    }
}
