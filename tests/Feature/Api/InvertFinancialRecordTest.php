<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvertFinancialRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_inverts_entry_to_exit_and_soft_deletes_all_linked_commissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $collaborator = Collaborator::factory()->for($company)->create();
        $entryType = EntryType::factory()->for($company)->create();
        $exitType = ExitType::factory()->for($company)->create();
        $service = ServiceType::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry',
            'entry_type_id' => $entryType->id,
            'service_type_id' => $service->id,
            'collaborator_id' => $collaborator->id,
        ]);
        $commissions = Commission::factory()->count(2)->for($company)->create([
            'collaborator_id' => $collaborator->id,
            'financial_record_id' => $record->id,
        ]);
        $unrelated = Commission::factory()->for($company)->create(['collaborator_id' => $collaborator->id]);

        $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 80,
            'quantity' => 2, 'discount' => 5, 'description' => 'Corrigido',
            'exit_type_id' => $exitType->id, 'collaborator_id' => $collaborator->id,
            'entry_type_id' => $entryType->id, 'service_type_id' => $service->id,
        ])->assertOk()->assertJsonPath('data.type', 'exit')
            ->assertJsonPath('data.entry_type_id', null)
            ->assertJsonPath('data.service_type_id', null)
            ->assertJsonPath('data.exit_type_id', $exitType->id)
            ->assertJsonPath('data.amount', '80.00');

        $this->assertDatabaseHas('financial_records', [
            'id' => $record->id, 'company_id' => $company->id, 'type' => 'exit',
            'quantity' => 2, 'discount' => 5, 'description' => 'Corrigido',
        ]);
        foreach ($commissions as $commission) {
            $this->assertSoftDeleted('commissions', ['id' => $commission->id]);
        }
        $this->assertDatabaseHas('commissions', ['id' => $unrelated->id, 'deleted_at' => null]);
    }

    public function test_inverts_exit_to_entry_and_clears_old_category(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $entryType = EntryType::factory()->for($company)->create();
        $exitType = ExitType::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'exit', 'exit_type_id' => $exitType->id,
        ]);

        $this->actingAs($user, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'entry', 'date' => '2025-02-01', 'amount' => 30,
            'entry_type_id' => $entryType->id, 'exit_type_id' => $exitType->id,
        ])->assertOk()->assertJsonPath('data.type', 'entry')
            ->assertJsonPath('data.entry_type_id', $entryType->id)
            ->assertJsonPath('data.exit_type_id', null);
    }

    public function test_inversion_rejects_other_company_records_and_categories_and_non_root_users(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $root = User::factory()->root()->for($company)->create();
        $owner = User::factory()->for($company)->create();
        $company->update(['owner_id' => $root->id]);
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'entry']);
        $otherRecord = FinancialRecord::factory()->for($otherCompany)->create(['type' => 'entry']);
        $otherCategory = ExitType::factory()->for($otherCompany)->create();
        $payload = ['type' => 'exit', 'date' => '2025-02-01', 'amount' => 30, 'exit_type_id' => $otherCategory->id];

        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$otherRecord->id}/invert", $payload)->assertNotFound();
        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", $payload)->assertUnprocessable()->assertJsonValidationErrors('exit_type_id');
        $this->actingAs($owner, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", $payload)->assertForbidden();
        $this->assertDatabaseHas('financial_records', ['id' => $record->id, 'type' => 'entry']);
    }

    public function test_inversion_validates_destination_and_normal_update_cannot_bypass_inversion(): void
    {
        $company = Company::factory()->create();
        $root = User::factory()->root()->for($company)->create();
        $exitType = ExitType::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'entry']);
        $payload = ['type' => 'exit', 'date' => '2025-02-01', 'amount' => 30];

        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('exit_type_id');
        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}", $payload)
            ->assertUnprocessable();
        $exitType->delete();
        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", $payload + ['exit_type_id' => $exitType->id])
            ->assertUnprocessable()->assertJsonValidationErrors('exit_type_id');
        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'entry', 'date' => '2025-02-01', 'amount' => 30,
            'entry_type_id' => EntryType::factory()->for($company)->create()->id,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('financial_records', ['id' => $record->id, 'type' => 'entry']);
    }

    public function test_inversion_cannot_modify_a_removed_record(): void
    {
        $company = Company::factory()->create();
        $root = User::factory()->root()->for($company)->create();
        $exitType = ExitType::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'entry']);
        $record->delete();

        $this->actingAs($root, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 30, 'exit_type_id' => $exitType->id,
        ])->assertNotFound();

        $this->assertSoftDeleted('financial_records', ['id' => $record->id, 'type' => 'entry']);
    }
}
