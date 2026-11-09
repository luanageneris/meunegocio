<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialRecordSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_responsible_across_pages_and_respects_company_and_period(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $collaborator = Collaborator::factory()->for($company)->create(['name' => 'Hilbert Quigley']);
        FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-03', 'collaborator_id' => $collaborator->id, 'amount' => 100,
        ]);
        FinancialRecord::factory(55)->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-20', 'collaborator_id' => null, 'amount' => 1,
        ]);
        $otherCollaborator = Collaborator::factory()->for($other)->create(['name' => 'Hilbert Quigley']);
        FinancialRecord::factory()->for($other)->create([
            'type' => 'entry', 'date' => '2025-01-03', 'collaborator_id' => $otherCollaborator->id,
        ]);
        $deleted = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-03', 'collaborator_id' => $collaborator->id,
        ]);
        $deleted->delete();

        $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?search=quigley')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.collaborator.name', 'Hilbert Quigley')
            ->assertJsonPath('totals.entries', 155);
        $this->actingAs($user, 'sanctum')->postJson('/api/registros/financial-records/between-dates?search=quigley', [
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('pagination.total', 1);
        $this->actingAs($user, 'sanctum')->postJson('/api/registros/financial-records/between-dates?search=quigley', [
            'start_date' => '2025-02-01', 'end_date' => '2025-02-28',
        ])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_searches_displayed_fields_and_respects_privacy_for_amounts(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $entryType = EntryType::factory()->for($company)->create(['name' => 'Impressão']);
        $service = ServiceType::factory()->for($company)->create(['name' => 'Xerografia']);
        $record = FinancialRecord::factory()->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-03', 'entry_type_id' => $entryType->id,
            'service_type_id' => $service->id, 'description' => 'Reparo especial',
            'amount' => '125.50', 'quantity' => 2, 'discount' => '1.00',
        ]);
        $record->forceFill(['created_at' => '2025-01-04 12:38:00'])->save();

        foreach (['impressão', 'Xerografia', 'reparo', '03/01/2025', '2025-01-03', '04/01/2025', 'entrada', 'R$ 250,00', '250,00'] as $term) {
            $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?'.http_build_query(['search' => $term]))
                ->assertOk()->assertJsonPath('pagination.total', 1);
        }
        $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?search=250%2C00&search_amount=0')
            ->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_filtering_happens_before_pagination_and_keeps_period_totals(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $category = ExitType::factory()->for($company)->create(['name' => 'Comissão']);
        FinancialRecord::factory()->for($company)->create([
            'type' => 'exit', 'date' => '2025-01-01', 'exit_type_id' => $category->id,
            'description' => 'Pagamento específico', 'amount' => 10,
        ]);
        FinancialRecord::factory(55)->for($company)->create([
            'type' => 'entry', 'date' => '2025-01-20', 'amount' => 1,
        ]);
        $url = '/api/registros/financial-records/between-dates?search=espec%C3%ADfico&financial_type=comissao';
        $this->actingAs($user, 'sanctum')->postJson($url, [
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('totals.entries', 55)->assertJsonPath('totals.exits', 10);
        $this->actingAs($user, 'sanctum')->postJson(str_replace('comissao', 'entrada', $url), [
            'start_date' => '2025-01-01', 'end_date' => '2025-01-31',
        ])->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_search_treats_wildcards_as_literal_characters(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        FinancialRecord::factory()->for($company)->create(['type' => 'entry', 'description' => 'Taxa 10%']);
        FinancialRecord::factory()->for($company)->create(['type' => 'entry', 'description' => 'Taxa simples']);

        $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?search=%25')
            ->assertOk()->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.description', 'Taxa 10%');
    }

    public function test_search_results_paginate(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->for($company)->create();
        $collaborator = Collaborator::factory()->for($company)->create(['name' => 'Marcela']);
        FinancialRecord::factory(20)->for($company)->create([
            'type' => 'exit', 'collaborator_id' => $collaborator->id, 'description' => 'Viagem',
        ]);
        $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?search=Marcela&page=1')
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('pagination.total', 20);
        $this->actingAs($user, 'sanctum')->getJson('/api/registros/financial-records?search=Marcela&page=2')
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('pagination.total', 20);
    }
}
