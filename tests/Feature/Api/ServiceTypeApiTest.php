<?php

namespace Tests\Feature\Api;

use App\Models\ServiceType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $rootUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        usleep(10000); // 10ms para garantir timestamp único
        $ts = time() . intval((microtime(true) - floor(microtime(true))) * 10000);
        
        $this->company = Company::create([
            'name' => 'Service Company ' . $ts,
            'slug' => 'service-company-' . $ts,
            'email' => 'service+' . $ts . '@test.com',
            'phone' => '123456789',
            'document' => $ts . '.345.678/0001-93',
            'address' => 'Test Address'
        ]);

        $this->rootUser = User::create([
            'name' => 'Root User Service',
            'email' => 'root+service+' . $ts . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $this->company->id,
            'google_id' => 'google-root-service-' . $ts,
            'role' => 'root'
        ]);
    }

    public function test_can_list_service_types()
    {
        ServiceType::create(['name' => 'Impressão', 'commission_percentage' => 10, 'company_id' => $this->company->id]);
        ServiceType::create(['name' => 'Cópia', 'commission_percentage' => 5, 'company_id' => $this->company->id]);
        ServiceType::create(['name' => 'Web', 'commission_percentage' => 20, 'company_id' => $this->company->id]);
        
        // Outras empresas
        $ts = time();
        $otherCompany = Company::create([
            'name' => 'Other Service Company ' . $ts,
            'slug' => 'other-service-' . $ts,
            'email' => 'other-service+' . $ts . '@test.com',
            'phone' => '987654321',
            'document' => $ts . '.765.432/0001-13',
            'address' => 'Other Address'
        ]);
        ServiceType::create(['name' => 'Outro 1', 'commission_percentage' => 15, 'company_id' => $otherCompany->id]);
        ServiceType::create(['name' => 'Outro 2', 'commission_percentage' => 25, 'company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->getJson('/api/personalizar/service-types');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_create_service_type()
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/service-types', [
                'name' => 'Impressão Colorida',
                'commission_percentage' => 15.50,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'commission_percentage', 'company_id']);

        $this->assertDatabaseHas('service_types', [
            'name' => 'Impressão Colorida',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_can_update_service_type()
    {
        $serviceType = ServiceType::create([
            'name' => 'Impressão Colorida',
            'commission_percentage' => 15.50,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->putJson("/api/personalizar/service-types/{$serviceType->id}", [
                'name' => 'Impressão Premium',
                'commission_percentage' => 20.00,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('service_types', [
            'id' => $serviceType->id,
            'name' => 'Impressão Premium',
            'commission_percentage' => 20.00,
        ]);
    }

    public function test_can_delete_service_type()
    {
        $serviceType = ServiceType::create([
            'name' => 'Impressão Colorida',
            'commission_percentage' => 15.50,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->deleteJson("/api/personalizar/service-types/{$serviceType->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('service_types', ['id' => $serviceType->id]);
    }

    public function test_service_type_name_must_be_unique()
    {
        ServiceType::create([
            'name' => 'Impressão',
            'commission_percentage' => 10,
            'company_id' => $this->company->id
        ]);

        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/service-types', [
                'name' => 'Impressão',
                'commission_percentage' => 10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_commission_percentage_must_be_between_0_and_100()
    {
        $response = $this->actingAs($this->rootUser, 'sanctum')
            ->postJson('/api/personalizar/service-types', [
                'name' => 'Impressão',
                'commission_percentage' => 150,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('commission_percentage');
    }
}
