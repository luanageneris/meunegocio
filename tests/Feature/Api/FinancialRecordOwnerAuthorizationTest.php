<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordOwnerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_commission_exit_and_update_it(): void
    {
        $company = Company::factory()->create();
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);
        $exitType = ExitType::factory()->for($company)->create(['name' => 'Comissão']);

        $payload = [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 30,
            'exit_type_id' => $exitType->id, 'description' => 'Comissão',
        ];
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/registros/financial-records', $payload)
            ->assertCreated()->assertJsonPath('data.type', 'exit')
            ->assertJsonPath('data.exit_type_id', $exitType->id);

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/registros/financial-records/'.$response->json('data.id'), array_merge($payload, ['amount' => 40]))
            ->assertOk()->assertJsonPath('data.amount', '40.00');
    }

    public function test_sole_owner_of_legacy_company_can_create_records(): void
    {
        $company = Company::factory()->create(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        User::factory()->root()->for($company)->create();

        $this->actingAs($owner, 'sanctum')->postJson('/api/registros/financial-records', [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 30,
        ])->assertCreated();
    }

    public function test_company_owner_can_invert_a_record(): void
    {
        $company = Company::factory()->create(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $company->update(['owner_id' => $owner->id]);
        $entryType = EntryType::factory()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'exit']);

        $this->actingAs($owner, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}/invert", [
            'type' => 'entry', 'date' => '2025-02-01', 'amount' => 30, 'entry_type_id' => $entryType->id,
        ])->assertOk()->assertJsonPath('data.type', 'entry');
    }

    public function test_owner_access_is_denied_when_ownership_is_ambiguous_or_belongs_to_another_user(): void
    {
        $company = Company::factory()->create(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $other = User::factory()->for($company)->create(['role' => 'owner']);
        $record = FinancialRecord::factory()->for($company)->create(['type' => 'exit']);
        $payload = ['type' => 'exit', 'date' => '2025-02-01', 'amount' => 30];

        $this->actingAs($owner, 'sanctum')->postJson('/api/registros/financial-records', $payload)
            ->assertForbidden()->assertJsonPath('message', 'Apenas o proprietário da empresa pode criar registros financeiros.');
        $this->actingAs($owner, 'sanctum')->putJson("/api/registros/financial-records/{$record->id}", $payload)
            ->assertForbidden()->assertJsonPath('message', 'Apenas o proprietário da empresa pode alterar registros financeiros.');

        $company->update(['owner_id' => $other->id]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/registros/financial-records', $payload)->assertForbidden();
    }

    public function test_owner_cannot_create_record_with_other_company_category(): void
    {
        $company = Company::factory()->create(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        $otherCompany = Company::factory()->create();
        $otherType = ExitType::factory()->for($otherCompany)->create();

        $this->actingAs($owner, 'sanctum')->postJson('/api/registros/financial-records', [
            'type' => 'exit', 'date' => '2025-02-01', 'amount' => 30, 'exit_type_id' => $otherType->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('exit_type_id');
    }
}
