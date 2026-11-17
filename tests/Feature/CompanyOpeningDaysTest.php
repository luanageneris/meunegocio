<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyOpeningDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_owner_can_save_opening_days(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/company', [
            'name' => 'Empresa com dias configurados',
            'opening_days' => [1, 3, 5],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.opening_days', [1, 3, 5]);

        $this->assertSame([1, 3, 5], $company->refresh()->opening_days);
    }

    public function test_company_opening_days_must_contain_valid_unique_weekdays(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->root()->create(['company_id' => $company->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/company', [
                'name' => 'Empresa sem dias válidos',
                'opening_days' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.opening_days.0', 'Selecione os dias de funcionamento da empresa.');

        $duplicateResponse = $this->putJson('/api/company', [
            'name' => 'Empresa com dia duplicado',
            'opening_days' => [1, 1],
        ]);

        $duplicateResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_days.1']);
        $this->assertSame(
            'Não selecione o mesmo dia mais de uma vez.',
            $duplicateResponse->json('errors')['opening_days.1'][0],
        );
    }
}
