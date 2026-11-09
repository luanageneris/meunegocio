<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_only_update_is_visible_in_history_with_original_names(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $oldType = EntryType::factory()->for($company)->create(['name' => 'Entrada antiga']);
        $newType = EntryType::factory()->for($company)->create(['name' => 'Entrada nova']);
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'entry_type_id' => $oldType->id, 'date' => '2025-01-15', 'amount' => 100,
            'quantity' => 1, 'discount' => 0, 'description' => 'Descrição',
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}", [
            'type' => 'entry', 'entry_type_id' => $newType->id, 'date' => '2025-01-15',
            'amount' => 100, 'quantity' => 1, 'discount' => 0, 'description' => 'Descrição',
        ])->assertOk();

        $log = AuditLog::forCompany($company->id)->where('model', FinancialRecord::class)->firstOrFail();
        $this->assertSame(['entry_type_id'], array_keys($log->changes));
        $this->assertSame('Entrada antiga', $log->old_values['entry_type_name']);
        $this->assertSame('Entrada nova', $log->new_values['entry_type_name']);
        $oldType->update(['name' => 'Renomeada']);
        $newType->delete();

        $history = $this->actingAs($user, 'sanctum')->getJson("/api/history/{$log->id}")
            ->assertOk()->assertJsonPath('data.changed_fields_count', 1)
            ->assertJsonPath('data.details.type', 'field_changes');
        $details = collect($history->json('data.details.fields'))->keyBy('field');
        $this->assertSame('Entrada antiga', $details['entry_type_id']['old']);
        $this->assertSame('Entrada nova', $details['entry_type_id']['new']);
    }

    public function test_existing_financial_history_resolves_categories_with_tenant_isolation(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $oldType = ExitType::factory()->for($company)->create(['name' => 'Saída antiga']);
        $otherType = ExitType::factory()->for($other)->create(['name' => 'Outra empresa']);
        $oldType->delete();
        $log = AuditLog::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'action' => 'update',
            'model' => FinancialRecord::class, 'model_id' => 1,
            'endpoint' => 'api/registros/financial-records/1',
            'old_values' => ['exit_type_id' => $oldType->id],
            'new_values' => ['exit_type_id' => $otherType->id],
            'changes' => ['exit_type_id' => ['old' => $oldType->id, 'new' => $otherType->id]],
        ]);

        $history = $this->actingAs($user, 'sanctum')->getJson("/api/history/{$log->id}")->assertOk();
        $details = collect($history->json('data.details.fields'))->keyBy('field');
        $this->assertSame('Saída antiga', $details['exit_type_id']['old']);
        $this->assertSame('Nome indisponível', $details['exit_type_id']['new']);
    }
}
