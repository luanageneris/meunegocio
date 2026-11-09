<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FinancialRecordInversionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_inversion_audits_every_change_and_each_removed_commission_in_history(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $oldCollaborator = Collaborator::factory()->for($company)->create(['name' => 'Anterior']);
        $newCollaborator = Collaborator::factory()->for($company)->create(['name' => 'Atual']);
        $entryType = EntryType::factory()->for($company)->create(['name' => 'Entrada anterior']);
        $exitType = ExitType::factory()->for($company)->create(['name' => 'Saída escolhida']);
        $service = ServiceType::factory()->for($company)->create(['name' => 'Serviço anterior']);
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-01', 'amount' => 100,
            'quantity' => 1, 'discount' => 0, 'description' => 'Antes',
            'entry_type_id' => $entryType->id, 'service_type_id' => $service->id,
            'collaborator_id' => $oldCollaborator->id,
        ]);
        $commissions = Commission::factory()->count(2)->for($company)->create([
            'financial_record_id' => $record->id, 'collaborator_id' => $oldCollaborator->id,
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 200,
            'quantity' => 2, 'discount' => 10, 'description' => 'Depois',
            'exit_type_id' => $exitType->id, 'collaborator_id' => $newCollaborator->id,
        ])->assertOk();

        $logs = AuditLog::forCompany($company->id)->where('endpoint', "api/registros/financial-records/{$record->id}/invert")->get();
        $this->assertCount(3, $logs);
        $log = $logs->firstWhere('model', FinancialRecord::class);
        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($record->id, $log->model_id);
        foreach (['type', 'date', 'amount', 'quantity', 'discount', 'description', 'entry_type_id', 'exit_type_id', 'service_type_id', 'collaborator_id'] as $field) {
            $this->assertArrayHasKey($field, $log->changes);
        }
        $this->assertSame('100.00', $log->changes['amount']['old']);
        $this->assertSame('200.00', $log->changes['amount']['new']);
        $this->assertSame('Entrada anterior', $log->old_values['entry_type_name']);
        $this->assertSame('Saída escolhida', $log->new_values['exit_type_name']);

        foreach ($commissions as $commission) {
            $deletion = $logs->first(fn ($item) => $item->model === Commission::class && $item->model_id === $commission->id);
            $this->assertNotNull($deletion);
            $this->assertSame('delete', $deletion->action);
            $this->assertSame($record->id, $deletion->old_values['financial_record_id']);
            $this->assertSoftDeleted('commissions', ['id' => $commission->id]);
        }

        $entryType->update(['name' => 'Renomeado']);
        $response = $this->actingAs($user, 'sanctum')->getJson("/api/history/{$log->id}")->assertOk()
            ->assertJsonPath('data.changed_fields_count', 10)
            ->assertJsonPath('data.details.type', 'field_changes');
        $details = collect($response->json('data.details.fields'))->keyBy('field');
        $this->assertSame('Entrada anterior', $details['entry_type_id']['old']);
        $this->assertSame('Saída escolhida', $details['exit_type_id']['new']);
        $this->assertSame('Anterior', $details['collaborator_id']['old']);
        $this->assertSame('Atual', $details['collaborator_id']['new']);
        $this->assertSame('Depois', $details['description']['new']);
    }

    public function test_inversion_rolls_back_when_audit_cannot_be_written(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $exitType = ExitType::factory()->for($company)->create();
        $collaborator = Collaborator::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'entry']);
        $commission = Commission::factory()->for($company)->create([
            'financial_record_id' => $record->id, 'collaborator_id' => $collaborator->id,
        ]);

        AuditLog::creating(fn () => throw new RuntimeException('Falha de auditoria simulada'));
        try {
            $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
                'type' => 'exit', 'date' => '2025-02-01', 'amount' => 30, 'exit_type_id' => $exitType->id,
            ])->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertDatabaseHas('financial_records', ['id' => $record->id, 'type' => 'entry']);
        $this->assertDatabaseHas('commissions', ['id' => $commission->id, 'deleted_at' => null]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_exit_to_entry_records_changed_fields(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $entryType = EntryType::factory()->for($company)->create(['name' => 'Entrada']);
        $exitType = ExitType::factory()->for($company)->create(['name' => 'Saída']);
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'exit', 'exit_type_id' => $exitType->id, 'description' => 'Antes',
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'entry', 'date' => '2025-02-01', 'amount' => 30,
            'entry_type_id' => $entryType->id, 'description' => 'Depois',
        ])->assertOk();

        $log = AuditLog::forCompany($company->id)->where('model', FinancialRecord::class)->firstOrFail();
        $details = collect($this->actingAs($user, 'sanctum')->getJson("/api/history/{$log->id}")
            ->assertOk()->json('data.details.fields'))->keyBy('field');
        $this->assertSame('Saída', $details['exit_type_id']['old']);
        $this->assertSame('Entrada', $details['entry_type_id']['new']);
        $this->assertSame('Depois', $details['description']['new']);
    }

    public function test_existing_inversion_audits_resolve_recorded_categories_only_within_the_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $entryType = EntryType::factory()->for($company)->create(['name' => 'Categoria antiga']);
        $otherType = ExitType::factory()->for($otherCompany)->create(['name' => 'Não deve aparecer']);
        $entryType->delete();
        $log = AuditLog::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'model' => FinancialRecord::class, 'model_id' => 1, 'action' => 'update',
            'endpoint' => 'api/registros/financial-records/1/invert',
            'old_values' => ['entry_type_id' => $entryType->id],
            'new_values' => ['exit_type_id' => $otherType->id],
            'changes' => [
                'entry_type_id' => ['old' => $entryType->id, 'new' => null],
                'exit_type_id' => ['old' => null, 'new' => $otherType->id],
            ],
        ]);

        $details = collect($this->actingAs($user, 'sanctum')->getJson("/api/history/{$log->id}")
            ->assertOk()->json('data.details.fields'))->keyBy('field');
        $this->assertSame('Categoria antiga', $details['entry_type_id']['old']);
        $this->assertSame('Nome indisponível', $details['exit_type_id']['new']);
    }
}
