<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use App\Models\Collaborator;
use App\Models\ServiceType;
use App\Models\FinancialRecord;
use App\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;
    protected Collaborator $collaborator;
    protected ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();
        usleep(10000);
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);

        $this->company = Company::create([
            'name' => 'Test Company ' . $ts,
            'slug' => 'test-company-' . $ts,
            'email' => 'test+' . $ts . '@example.com',
            'document' => $ts . '.345.678/0001-90',
            'website' => 'https://example.com',
            'address' => 'Rua Teste, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567',
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User',
            'email' => 'root+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'role' => 'root',
        ]);

        $this->collaborator = Collaborator::factory()->for($this->company)->create();
        $this->serviceType = ServiceType::factory()->for($this->company)->create([
            'commission_percentage' => 10.0,
        ]);
    }

    public function test_can_list_commissions(): void
    {
        Commission::factory(5)->for($this->company)->for($this->collaborator, 'collaborator')->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/registros/commissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'company_id', 'collaborator_id', 'amount', 'date']
                ]
            ]);
    }

    public function test_can_create_commission(): void
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/commissions', [
                'date' => '2025-01-15',
                'amount' => 50.00,
                'collaborator_id' => $this->collaborator->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '50.00');

        $this->assertDatabaseHas('commissions', [
            'collaborator_id' => $this->collaborator->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_update_commission(): void
    {
        $commission = Commission::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create([
                'amount' => 50.00,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/registros/commissions/{$commission->id}", [
                'date' => $commission->date,
                'amount' => 75.00,
                'collaborator_id' => $this->collaborator->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', '75.00');
    }

    public function test_can_delete_commission(): void
    {
        $commission = Commission::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/registros/commissions/{$commission->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Comissão removida com sucesso');

        $this->assertSoftDeleted('commissions', ['id' => $commission->id]);
    }

    public function test_can_generate_commissions_from_date(): void
    {
        $date = '2025-01-15';

        FinancialRecord::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create([
                'date' => $date,
                'value' => 100.00,
                'service_type_id' => $this->serviceType->id,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/commissions/generate-from-date', [
                'date' => $date,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.count', 1);

        // Commission should be 100 * 10% = 10.00
        $this->assertDatabaseHas('commissions', [
            'collaborator_id' => $this->collaborator->id,
            'amount' => 10.00,
        ]);
    }

    public function test_can_get_collaborator_commissions(): void
    {
        Commission::factory(5)
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create();

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/commissions/collaborator/{$this->collaborator->id}");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_can_get_unpaid_commissions(): void
    {
        Commission::factory(3)
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create([
                'paid_at' => null,
                'amount' => 50.00,
            ]);

        Commission::factory()
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create([
                'paid_at' => now(),
                'amount' => 25.00,
            ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson("/api/registros/commissions/collaborator/{$this->collaborator->id}/unpaid");

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 150.00)
            ->assertJsonCount(3, 'data.commissions');
    }

    public function test_can_mark_commissions_as_paid(): void
    {
        $commissions = Commission::factory(3)
            ->for($this->company)
            ->for($this->collaborator, 'collaborator')
            ->create(['paid_at' => null]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/registros/commissions/mark-as-paid', [
                'commission_ids' => $commissions->pluck('id')->toArray(),
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated', 3);

        foreach ($commissions as $commission) {
            $this->assertDatabaseHas('commissions', [
                'id' => $commission->id,
                'paid_at' => now()->format('Y-m-d'),
            ]);
        }
    }
}
