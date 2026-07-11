<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_google_creates_required_defaults_for_new_company(): void
    {
        $pending = [
            'name' => 'Novo Usuario',
            'email' => 'novo.usuario@example.com',
            'google_id' => 'google-new-user-123',
        ];

        $response = $this->withSession([
            'pending_registration' => $pending,
        ])->postJson('/api/auth/register', [
            'company_name' => 'Empresa Teste Defaults',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'novo.usuario@example.com')
            ->assertJsonPath('data.user.role', 'root');

        $company = Company::firstWhere('slug', 'empresa-teste-defaults');

        $this->assertNotNull($company);
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
        $this->assertDatabaseHas('exit_types', [
            'company_id' => $company->id,
            'name' => 'Comissão',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('collaborators', [
            'company_id' => $company->id,
            'name' => 'Não definido',
            'deleted_at' => null,
        ]);
    }
}
