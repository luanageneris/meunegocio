<?php

namespace Tests\Feature;

use App\Models\ServiceType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->root()->create(['company_id' => $this->company->id]);
    }

    public function test_index_returns_service_types_for_company()
    {
        ServiceType::factory()->count(3)->create(['company_id' => $this->company->id]);
        ServiceType::factory()->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/personalizar/service-types');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_store_creates_service_type()
    {
        $payload = [
            'name' => 'Digitação',
            'unit' => 'Folha',
            'service_value' => '50.00',
            'commission_percentage' => '10.00',
            'commission_value' => '5.00',
            'resource_value' => '2.00',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/personalizar/service-types', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('service_types', [
            'name' => 'Digitação',
            'unit' => 'Folha',
            'service_value' => 50.00,
            'commission_percentage' => 10.00,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_validates_required_unit()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/service-types', [
            'name' => 'Digitação',
            'service_value' => 50,
            'commission_percentage' => 10,
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('unit', $response->json('errors'));
    }

    public function test_store_validates_required_service_value()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/service-types', [
            'name' => 'Digitação',
            'unit' => 'Folha',
            'commission_percentage' => 10,
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('service_value', $response->json('errors'));
    }

    public function test_store_validates_required_commission_percentage()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/service-types', [
            'name' => 'Digitação',
            'unit' => 'Folha',
            'service_value' => 50,
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('commission_percentage', $response->json('errors'));
    }

    public function test_store_validates_commission_percentage_range()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/service-types', [
            'name' => 'Digitação',
            'unit' => 'Folha',
            'service_value' => 50,
            'commission_percentage' => 150,
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('commission_percentage', $response->json('errors'));
    }

    public function test_show_returns_service_type()
    {
        $type = ServiceType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/service-types/{$type->id}");

        $response->assertOk();
        $this->assertEquals($type->id, $response->json('id'));
    }

    public function test_show_denies_access_to_other_company_service_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ServiceType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/service-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_service_type()
    {
        $type = ServiceType::factory()->create(['company_id' => $this->company->id]);
        $payload = [
            'name' => 'Digitação Atualizada',
            'unit' => 'Folha A4',
            'service_value' => '75.00',
            'commission_percentage' => '15.50',
            'commission_value' => '11.63',
            'resource_value' => '3.00',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/service-types/{$type->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('service_types', [
            'id' => $type->id,
            'name' => 'Digitação Atualizada',
            'unit' => 'Folha A4',
            'service_value' => 75.00,
            'commission_percentage' => 15.50,
        ]);
    }

    public function test_update_denies_access_to_other_company_service_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ServiceType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/service-types/{$type->id}", [
            'name' => 'Updated',
            'unit' => 'Folha',
            'service_value' => 50,
            'commission_percentage' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_service_type()
    {
        $type = ServiceType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/service-types/{$type->id}");

        $response->assertOk();
        $this->assertSoftDeleted('service_types', ['id' => $type->id]);
    }

    public function test_destroy_denies_access_to_other_company_service_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ServiceType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/service-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_destroy_requires_root_role()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'role' => 'owner']);
        $type = ServiceType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/personalizar/service-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_access_denied()
    {
        $response = $this->getJson('/api/personalizar/service-types');

        $response->assertUnauthorized();
    }
}
