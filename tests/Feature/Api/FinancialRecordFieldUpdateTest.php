<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordFieldUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_category_and_collaborator_without_changing_other_fields(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $collaborator = Collaborator::factory()->for($company)->create();

        foreach (['entry', 'exit'] as $type) {
            $category = $type === 'entry'
                ? EntryType::factory()->for($company)->create()
                : ExitType::factory()->for($company)->create();
            $field = $type.'_type_id';
            $record = FinancialRecord::factory()->for($company)->create([
                'type' => $type, 'entry_type_id' => null, 'exit_type_id' => null,
                'collaborator_id' => null, 'amount' => '20.00', 'quantity' => 3,
                'discount' => '2.00', 'description' => 'Preservar',
            ]);
            $url = "/api/registros/financial-records/{$record->id}/field";
            $this->actingAs($user, 'sanctum')->patchJson($url, [
                'field' => $field, 'value' => $category->id, 'expected_value' => null, 'expected_type' => $type,
            ])->assertOk()->assertJsonPath("data.$field", $category->id);
            $this->actingAs($user, 'sanctum')->patchJson($url, [
                'field' => 'collaborator_id', 'value' => $collaborator->id, 'expected_value' => null, 'expected_type' => $type,
            ])->assertOk()->assertJsonPath('data.collaborator_id', $collaborator->id)
                ->assertJsonPath('data.amount', '20.00')
                ->assertJsonPath('data.quantity', 3)
                ->assertJsonPath('data.discount', '2.00')
                ->assertJsonPath('data.description', 'Preservar');
            $this->actingAs($user, 'sanctum')->patchJson($url, [
                'field' => 'collaborator_id', 'value' => null, 'expected_value' => $collaborator->id, 'expected_type' => $type,
            ])->assertOk()->assertJsonPath('data.collaborator_id', null);
        }
    }

    public function test_rejects_foreign_or_deleted_options_invalid_fields_and_stale_changes(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'entry_type_id' => null, 'exit_type_id' => null, 'collaborator_id' => null,
        ]);
        $otherType = EntryType::factory()->for($other)->create();
        $deleted = Collaborator::factory()->for($company)->create();
        $deleted->delete();
        $exitType = ExitType::factory()->for($company)->create();
        $url = "/api/registros/financial-records/{$record->id}/field";
        $base = ['expected_value' => null, 'expected_type' => 'entry'];

        $this->actingAs($user, 'sanctum')->patchJson($url, $base + [
            'field' => 'entry_type_id', 'value' => $otherType->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + [
            'field' => 'collaborator_id', 'value' => $deleted->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + [
            'field' => 'exit_type_id', 'value' => $exitType->id,
        ])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + [
            'field' => 'amount', 'value' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('field');
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'field' => 'collaborator_id', 'value' => null, 'expected_value' => 123, 'expected_type' => 'entry',
        ])->assertStatus(409);
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'field' => 'collaborator_id', 'value' => null, 'expected_value' => null, 'expected_type' => 'exit',
        ])->assertStatus(409);
        $this->assertNull($record->fresh()->collaborator_id);
    }

    public function test_changes_only_movement_date_and_rejects_invalid_or_stale_dates(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-15', 'amount' => '20.00',
            'description' => 'Preservar',
        ]);
        $originalCreatedAt = $record->created_at;
        $url = "/api/registros/financial-records/{$record->id}/field";
        $base = ['field' => 'date', 'expected_value' => '2025-01-15', 'expected_type' => 'entry'];

        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => '2025-02-31'])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => '2025-02-10'])
            ->assertOk()->assertJsonPath('data.amount', '20.00')
            ->assertJsonPath('data.description', 'Preservar');
        $this->assertSame('2025-02-10', $record->fresh()->date->format('Y-m-d'));
        $this->assertTrue($originalCreatedAt->equalTo($record->fresh()->created_at));
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => '2025-03-10'])
            ->assertStatus(409);
    }

    public function test_updates_or_clears_description_and_rejects_invalid_or_stale_values(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'exit', 'description' => null, 'amount' => '20.00',
        ]);
        $url = "/api/registros/financial-records/{$record->id}/field";
        $base = ['field' => 'description', 'expected_value' => null, 'expected_type' => 'exit'];

        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => 'Nova descrição'])
            ->assertOk()->assertJsonPath('data.description', 'Nova descrição')->assertJsonPath('data.amount', '20.00');
        $this->actingAs($user, 'sanctum')->patchJson($url, $base + ['value' => 'Conflito'])
            ->assertStatus(409);
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'field' => 'description', 'value' => null, 'expected_value' => 'Nova descrição', 'expected_type' => 'exit',
        ])->assertOk()->assertJsonPath('data.description', null);

        $record->update(['description' => '  descrição antiga  ']);
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'field' => 'description', 'value' => 'Corrigida', 'expected_value' => '  descrição antiga  ', 'expected_type' => 'exit',
        ])->assertOk()->assertJsonPath('data.description', 'Corrigida');
    }

    public function test_rejects_unauthorized_other_company_and_deleted_records(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create();
        $otherRecord = FinancialRecord::factory()->for($other)->create();
        $payload = ['field' => 'collaborator_id', 'value' => null, 'expected_value' => null, 'expected_type' => 'entry'];

        $this->actingAs($user, 'sanctum')->patchJson("/api/registros/financial-records/{$otherRecord->id}/field", $payload)->assertNotFound();
        $company->update(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        User::factory()->for($company)->create(['role' => 'owner']);
        $this->actingAs($owner, 'sanctum')->patchJson("/api/registros/financial-records/{$record->id}/field", $payload)->assertForbidden();
        $record->delete();
        $this->actingAs($user, 'sanctum')->patchJson("/api/registros/financial-records/{$record->id}/field", $payload)->assertNotFound();
    }
}
