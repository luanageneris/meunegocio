<?php

namespace Tests\Feature;

use App\Models\ExitType;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExitTypeControllerTest extends TestCase
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

    public function test_index_returns_exit_types_for_company()
    {
        ExitType::factory()->count(3)->create(['company_id' => $this->company->id]);
        ExitType::factory()->create(['company_id' => Company::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/personalizar/exit-types');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_store_creates_exit_type()
    {
        $payload = [
            'name' => 'Comissão',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/personalizar/exit-types', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('exit_types', [
            'name' => 'Comissão',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_validates_required_name()
    {
        $response = $this->actingAs($this->user)->postJson('/api/personalizar/exit-types', []);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name', $response->json('errors'));
    }

    public function test_show_returns_exit_type()
    {
        $type = ExitType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/exit-types/{$type->id}");

        $response->assertOk();
        $this->assertEquals($type->id, $response->json('id'));
    }

    public function test_show_denies_access_to_other_company_exit_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ExitType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->getJson("/api/personalizar/exit-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_exit_type()
    {
        $type = ExitType::factory()->create(['company_id' => $this->company->id]);
        $payload = [
            'name' => 'Comissão Atualizada',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/exit-types/{$type->id}", $payload);

        $response->assertOk();
        $this->assertDatabaseHas('exit_types', [
            'id' => $type->id,
            'name' => 'Comissão Atualizada',
        ]);
    }

    public function test_update_denies_access_to_other_company_exit_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ExitType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->putJson("/api/personalizar/exit-types/{$type->id}", [
            'name' => 'Updated',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_exit_type()
    {
        $type = ExitType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/exit-types/{$type->id}");

        $response->assertOk();
        $this->assertSoftDeleted('exit_types', ['id' => $type->id]);
    }

    public function test_destroy_denies_access_to_other_company_exit_type()
    {
        $otherCompany = Company::factory()->create();
        $type = ExitType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/personalizar/exit-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_destroy_requires_root_role()
    {
        $user = User::factory()->create(['company_id' => $this->company->id, 'role' => 'owner']);
        $type = ExitType::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/personalizar/exit-types/{$type->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_access_denied()
    {
        $response = $this->getJson('/api/personalizar/exit-types');

        $response->assertUnauthorized();
    }
}
