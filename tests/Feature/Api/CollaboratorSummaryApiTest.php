<?php

namespace Tests\Feature\Api;

use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\Company;
use App\Models\EntryType;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollaboratorSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;
    protected Collaborator $collaborator;

    protected function setUp(): void
    {
        parent::setUp();

        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);

        $this->company = Company::create([
            'name' => 'Summary Company ' . $ts,
            'slug' => 'summary-company-' . $ts,
            'email' => 'summary+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-97',
            'address' => 'Test Address',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root Summary',
            'email' => 'root+summary+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-summary-' . $ts,
            'role' => 'root',
        ]);

        $this->collaborator = Collaborator::create([
            'name' => 'Colaborador Resumo',
            'cpf' => '123.456.789-11',
            'active' => true,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_get_collaborator_summary_for_last_7_days(): void
    {
        $entryType = EntryType::create([
            'company_id' => $this->company->id,
            'name' => 'Serviço',
        ]);

        FinancialRecord::create([
            'company_id' => $this->company->id,
            'type' => 'entry',
            'entry_type_id' => $entryType->id,
            'collaborator_id' => $this->collaborator->id,
            'amount' => 100,
            'quantity' => 1,
            'discount' => 0,
            'date' => now()->subDays(2)->toDateString(),
            'description' => 'Venda recente',
        ]);

        FinancialRecord::create([
            'company_id' => $this->company->id,
            'type' => 'entry',
            'entry_type_id' => $entryType->id,
            'collaborator_id' => $this->collaborator->id,
            'amount' => 90,
            'quantity' => 1,
            'discount' => 0,
            'date' => now()->subDays(15)->toDateString(),
            'description' => 'Venda antiga',
        ]);

        Commission::create([
            'company_id' => $this->company->id,
            'collaborator_id' => $this->collaborator->id,
            'date' => now()->subDay()->toDateString(),
            'amount' => 30,
            'notes' => 'Comissão recente',
        ]);

        Commission::create([
            'company_id' => $this->company->id,
            'collaborator_id' => $this->collaborator->id,
            'date' => now()->subDays(20)->toDateString(),
            'amount' => 50,
            'notes' => 'Comissão antiga',
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/personalizar/collaborators/{$this->collaborator->id}/summary?period=ultimos7dias");

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'ultimos7dias')
            ->assertJsonPath('data.sales_total', 100)
            ->assertJsonPath('data.commission_total', 30);
    }

    public function test_summary_defaults_to_last_7_days_when_period_is_invalid(): void
    {
        $entryType = EntryType::create([
            'company_id' => $this->company->id,
            'name' => 'Serviço 2',
        ]);

        FinancialRecord::create([
            'company_id' => $this->company->id,
            'type' => 'entry',
            'entry_type_id' => $entryType->id,
            'collaborator_id' => $this->collaborator->id,
            'amount' => 80,
            'quantity' => 1,
            'discount' => 0,
            'date' => now()->subDay()->toDateString(),
            'description' => 'Venda dia anterior',
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/personalizar/collaborators/{$this->collaborator->id}/summary?period=invalido");

        $response->assertStatus(200)
            ->assertJsonPath('data.period', 'ultimos7dias')
            ->assertJsonPath('data.sales_total', 80)
            ->assertJsonPath('data.commission_total', 0);
    }

    public function test_cannot_get_summary_for_other_company_collaborator(): void
    {
        $otherCompany = Company::factory()->create();

        $otherCollaborator = Collaborator::create([
            'name' => 'Outro Colaborador',
            'cpf' => '123.456.789-55',
            'active' => true,
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/personalizar/collaborators/{$otherCollaborator->id}/summary?period=todos");

        $response->assertStatus(403);
    }
}
