<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordTotalUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_the_unit_amount_for_entries_and_exits(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        foreach (['entry', 'exit'] as $type) {
            $record = FinancialRecord::factory()->for($company)->create([
                'type' => $type, 'amount' => '10.00', 'quantity' => 3,
                'discount' => '2.00', 'description' => 'Preservar',
            ]);

            $this->actingAs($user, 'sanctum')->patchJson("/api/registros/financial-records/{$record->id}/total", [
                'total_cents' => 5800, 'expected_total_cents' => 2800,
            ])->assertOk()->assertJsonPath('data.amount', '20.00')
                ->assertJsonPath('data.quantity', 3)
                ->assertJsonPath('data.discount', '2.00')
                ->assertJsonPath('data.description', 'Preservar')
                ->assertJsonPath('data.type', $type);
        }
    }

    public function test_rejects_unrepresentable_totals_and_stale_edits(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $record = FinancialRecord::factory()->for($company)->create([
            'amount' => '10.00', 'quantity' => 3, 'discount' => '2.00',
        ]);
        $url = "/api/registros/financial-records/{$record->id}/total";

        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'total_cents' => 5801, 'expected_total_cents' => 2800,
        ])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'total_cents' => 5800, 'expected_total_cents' => 2700,
        ])->assertStatus(409);
        $this->actingAs($user, 'sanctum')->patchJson($url, [
            'total_cents' => -1, 'expected_total_cents' => 2800,
        ])->assertUnprocessable();
        $this->assertSame('10.00', $record->fresh()->amount);
    }

    public function test_protects_company_and_permissions_and_removed_records(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $otherRecord = FinancialRecord::factory()->for($otherCompany)->create();
        $payload = ['total_cents' => 1000, 'expected_total_cents' => 1000];

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/registros/financial-records/{$otherRecord->id}/total", $payload)
            ->assertNotFound();

        $record = FinancialRecord::factory()->for($company)->create();
        $company->update(['owner_id' => null]);
        $owner = User::factory()->for($company)->create(['role' => 'owner']);
        User::factory()->for($company)->create(['role' => 'owner']);
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/registros/financial-records/{$record->id}/total", $payload)
            ->assertForbidden();

        $record->delete();
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/registros/financial-records/{$record->id}/total", $payload)
            ->assertNotFound();
    }
}
