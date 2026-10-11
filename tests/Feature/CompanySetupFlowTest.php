<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanySetupFlowTest extends TestCase
{
    #[Test]
    public function user_without_company_can_create_company_via_setup_endpoint(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'company_id' => null,
            'google_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/company', [
            'name' => 'Empresa Setup Teste',
            'email' => 'setup.teste@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Empresa Setup Teste');

        $user->refresh();
        $this->assertNotNull($user->company_id);

        $company = Company::query()->find($user->company_id);
        $this->assertNotNull($company);
        $this->assertSame($user->id, $company->owner_id);

        $this->assertDatabaseHas('entry_types', [
            'company_id' => $company->id,
            'name' => 'Não definido',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('exit_types', [
            'company_id' => $company->id,
            'name' => 'Não definido',
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('collaborators', [
            'company_id' => $company->id,
            'name' => 'Não definido',
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function user_without_company_is_still_blocked_from_other_tenant_scoped_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'company_id' => null,
            'google_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/history');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Usuário deve estar associado a uma empresa.');
    }
}
